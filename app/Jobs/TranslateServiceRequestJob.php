<?php

namespace App\Jobs;

use App\Contracts\Translator;
use App\Enums\TranslationStatus;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestTranslation;
use App\Support\ServiceRequestHasher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Translates a Service Request's title/description into one non-source
 * locale (FR-18) using a Fake translator in local/testing. The external
 * translate() call runs entirely outside any DB transaction/lock; the
 * source_hash carried since dispatch is re-checked both before the call
 * (cheap early exit) and again immediately before writing under a row lock
 * (authoritative, FR-23a) so a stale job never overwrites newer content.
 *
 * MVP does not retry failed translations automatically: a failure is
 * recorded as `failed` and the job ends normally. Viewers fall back to the
 * source text (FR-21) regardless of whether a translation row exists yet.
 */
class TranslateServiceRequestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $serviceRequestId,
        public string $targetLocale,
        public string $sourceHash,
    ) {}

    public function handle(Translator $translator): void
    {
        $serviceRequest = ServiceRequest::find($this->serviceRequestId);
        if ($serviceRequest === null) {
            return;
        }

        if (ServiceRequestHasher::hash($serviceRequest->title, $serviceRequest->description) !== $this->sourceHash) {
            return; // stale before we even call the translator
        }

        $failed = false;
        $translatedTitle = null;
        $translatedDescription = null;
        try {
            $translatedTitle = $translator->translate($serviceRequest->title, $serviceRequest->source_locale, $this->targetLocale);
            $translatedDescription = $translator->translate($serviceRequest->description, $serviceRequest->source_locale, $this->targetLocale);
        } catch (\Throwable) {
            $failed = true;
        }

        DB::transaction(function () use ($serviceRequest, $failed, $translatedTitle, $translatedDescription) {
            $translation = ServiceRequestTranslation::query()
                ->where('service_request_id', $serviceRequest->id)
                ->where('locale', $this->targetLocale)
                ->lockForUpdate()
                ->first();

            if ($translation === null) {
                return;
            }

            $current = ServiceRequest::query()->find($serviceRequest->id);
            if ($current === null) {
                return;
            }

            $currentHash = ServiceRequestHasher::hash($current->title, $current->description);
            if ($currentHash !== $this->sourceHash) {
                return; // stale, discard
            }

            if ($failed) {
                $translation->translation_status = TranslationStatus::Failed;
                $translation->save();

                return;
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
