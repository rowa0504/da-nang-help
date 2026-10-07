<?php

namespace App\Jobs;

use App\Contracts\Translator;
use App\Enums\TranslationStatus;
use App\Exceptions\Translation\RetryableTranslationException;
use App\Models\Offer;
use App\Models\OfferTranslation;
use App\Support\OfferHasher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Translates an Offer's message into one non-source locale (FR-19).
 * Mirrors TranslateServiceRequestJob exactly (see its docblock for the
 * retry/classification rationale), except there is only one field to
 * translate (message) and the staleness hash covers (message,
 * source_locale) via OfferHasher, since a source_locale change alone
 * changes what the text is translated from.
 */
class TranslateOfferJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public int $offerId,
        public string $targetLocale,
        public string $sourceHash,
    ) {}

    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(Translator $translator): void
    {
        $offer = Offer::find($this->offerId);
        if ($offer === null) {
            return;
        }

        if (OfferHasher::hash($offer->message, $offer->source_locale) !== $this->sourceHash) {
            return; // stale before we even call the translator
        }

        try {
            $translatedMessage = $translator->translate($offer->message, $offer->source_locale, $this->targetLocale);
        } catch (RetryableTranslationException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->markFailed($e);

            return;
        }

        $this->markCompleted($translatedMessage);
    }

    public function failed(Throwable $exception): void
    {
        $this->markFailed($exception);
    }

    /**
     * See TranslateServiceRequestJob::markFailed() for why both the hash
     * AND the current `pending` status are re-checked here.
     */
    private function markFailed(Throwable $e): void
    {
        Log::error('Offer translation permanently failed.', [
            'offer_id' => $this->offerId,
            'target_locale' => $this->targetLocale,
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);

        DB::transaction(function () {
            $translation = OfferTranslation::query()
                ->where('offer_id', $this->offerId)
                ->where('locale', $this->targetLocale)
                ->lockForUpdate()
                ->first();

            if ($translation === null || $translation->translation_status !== TranslationStatus::Pending) {
                return;
            }

            $current = Offer::query()->find($this->offerId);
            if ($current === null) {
                return;
            }

            if (OfferHasher::hash($current->message, $current->source_locale) !== $this->sourceHash) {
                return; // stale, discard
            }

            $translation->translation_status = TranslationStatus::Failed;
            $translation->save();
        });
    }

    private function markCompleted(string $translatedMessage): void
    {
        DB::transaction(function () use ($translatedMessage) {
            $translation = OfferTranslation::query()
                ->where('offer_id', $this->offerId)
                ->where('locale', $this->targetLocale)
                ->lockForUpdate()
                ->first();

            if ($translation === null) {
                return;
            }

            $current = Offer::query()->find($this->offerId);
            if ($current === null) {
                return;
            }

            $currentHash = OfferHasher::hash($current->message, $current->source_locale);
            if ($currentHash !== $this->sourceHash) {
                return; // stale, discard
            }

            $translation->message = $translatedMessage;
            $translation->source_hash = $currentHash;
            $translation->translation_status = TranslationStatus::Completed;
            $translation->translated_at = now();
            $translation->save();
        });
    }
}
