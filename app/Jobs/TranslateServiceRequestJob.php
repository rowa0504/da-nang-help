<?php

namespace App\Jobs;

use App\Contracts\Translator;
use App\Enums\TranslationStatus;
use App\Exceptions\Translation\RetryableTranslationException;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestTranslation;
use App\Support\ServiceRequestHasher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Translates a Service Request's title/description into one non-source
 * locale (FR-18). The external translate() call runs entirely outside any
 * DB transaction/lock; the source_hash carried since dispatch is re-checked
 * both before the call (cheap early exit) and again immediately before
 * writing under a row lock (authoritative, FR-23a) so a stale job never
 * overwrites newer content.
 *
 * Retries up to 3 times total (BLUEPRINT.md's "翻訳ジョブは3回・60秒程度"),
 * but only for a RetryableTranslationException (a connection problem, rate
 * limit, or transient AWS error — see AwsTranslateTranslator::classify()).
 * Any other exception — a PermanentTranslationException, or an unexpected
 * one from a non-AWS Translator — is written as `failed` on the spot,
 * consuming no further attempts. Because AwsTranslateTranslator's own
 * TranslateClient also retries transient errors a few times internally
 * (its 'retries' config in AppServiceProvider), a single sustained outage
 * can see up to 3 (SDK-internal) x 3 (this Job's $tries) = 9 total HTTP
 * attempts before translation_status finally becomes `failed`.
 */
class TranslateServiceRequestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public int $serviceRequestId,
        public string $targetLocale,
        public string $sourceHash,
    ) {}

    /**
     * 10s, then 30s, between the 3 total attempts — deliberately longer
     * than the AWS SDK's own internal retry delays, so this layer only
     * engages for a genuinely sustained problem rather than the brief
     * blips the SDK already absorbs on its own.
     */
    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(Translator $translator): void
    {
        $serviceRequest = ServiceRequest::find($this->serviceRequestId);
        if ($serviceRequest === null) {
            return;
        }

        if (ServiceRequestHasher::hash($serviceRequest->title, $serviceRequest->description) !== $this->sourceHash) {
            return; // stale before we even call the translator
        }

        try {
            $translatedTitle = $translator->translate($serviceRequest->title, $serviceRequest->source_locale, $this->targetLocale);
            $translatedDescription = $translator->translate($serviceRequest->description, $serviceRequest->source_locale, $this->targetLocale);
        } catch (RetryableTranslationException $e) {
            // Propagate uncaught: the queue worker retries per $tries/
            // backoff() above. Only once every attempt is exhausted does
            // failed() below write `failed`.
            throw $e;
        } catch (Throwable $e) {
            // PermanentTranslationException, or any other/unexpected
            // exception (a bug, a non-AWS Translator misbehaving) — never
            // retried, written as `failed` now.
            $this->markFailed($e);

            return;
        }

        $this->markCompleted($translatedTitle, $translatedDescription);
    }

    /**
     * Called by the queue worker once every attempt in $tries has been
     * exhausted on a RetryableTranslationException (or any other exception
     * that somehow propagated past handle() uncaught). Writes the same
     * `failed` outcome the inline permanent-failure path in handle() would
     * have, via the identical hash+status-guarded markFailed().
     */
    public function failed(Throwable $exception): void
    {
        $this->markFailed($exception);
    }

    /**
     * Shared by the inline permanent-failure path in handle() and by
     * failed() above. Re-checks both the current source_hash AND that the
     * row is still `pending` before writing: if a duplicate/overlapping
     * job exists for the same (id, locale, hash), a late failure from one
     * of them must never regress a row another, faster job has already
     * marked `completed`.
     */
    private function markFailed(Throwable $e): void
    {
        Log::error('Service Request translation permanently failed.', [
            'service_request_id' => $this->serviceRequestId,
            'target_locale' => $this->targetLocale,
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);

        DB::transaction(function () {
            $translation = ServiceRequestTranslation::query()
                ->where('service_request_id', $this->serviceRequestId)
                ->where('locale', $this->targetLocale)
                ->lockForUpdate()
                ->first();

            if ($translation === null || $translation->translation_status !== TranslationStatus::Pending) {
                return;
            }

            $current = ServiceRequest::query()->find($this->serviceRequestId);
            if ($current === null) {
                return;
            }

            if (ServiceRequestHasher::hash($current->title, $current->description) !== $this->sourceHash) {
                return; // stale, discard
            }

            $translation->translation_status = TranslationStatus::Failed;
            $translation->save();
        });
    }

    private function markCompleted(string $translatedTitle, string $translatedDescription): void
    {
        DB::transaction(function () use ($translatedTitle, $translatedDescription) {
            $translation = ServiceRequestTranslation::query()
                ->where('service_request_id', $this->serviceRequestId)
                ->where('locale', $this->targetLocale)
                ->lockForUpdate()
                ->first();

            if ($translation === null) {
                return;
            }

            $current = ServiceRequest::query()->find($this->serviceRequestId);
            if ($current === null) {
                return;
            }

            $currentHash = ServiceRequestHasher::hash($current->title, $current->description);
            if ($currentHash !== $this->sourceHash) {
                return; // stale, discard
            }

            $translation->title = $translatedTitle;
            $translation->description = $translatedDescription;
            $translation->source_hash = $currentHash;
            $translation->translation_status = TranslationStatus::Completed;
            $translation->translated_at = now();
            $translation->save();
        });
    }
}
