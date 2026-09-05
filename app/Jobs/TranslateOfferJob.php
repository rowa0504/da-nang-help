<?php

namespace App\Jobs;

use App\Contracts\Translator;
use App\Enums\TranslationStatus;
use App\Models\Offer;
use App\Models\OfferTranslation;
use App\Support\OfferHasher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Translates an Offer's message into one non-source locale (FR-19) using a
 * Fake translator in local/testing. Mirrors TranslateServiceRequestJob
 * exactly, except there is only one field to translate (message) and the
 * staleness hash covers (message, source_locale) via OfferHasher, since a
 * source_locale change alone changes what the text is translated from.
 */
class TranslateOfferJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $offerId,
        public string $targetLocale,
        public string $sourceHash,
    ) {}

    public function handle(Translator $translator): void
    {
        $offer = Offer::find($this->offerId);
        if ($offer === null) {
            return;
        }

        if (OfferHasher::hash($offer->message, $offer->source_locale) !== $this->sourceHash) {
            return; // stale before we even call the translator
        }

        $failed = false;
        $translatedMessage = null;
        try {
            $translatedMessage = $translator->translate($offer->message, $offer->source_locale, $this->targetLocale);
        } catch (\Throwable) {
            $failed = true;
        }

        DB::transaction(function () use ($offer, $failed, $translatedMessage) {
            $translation = OfferTranslation::query()
                ->where('offer_id', $offer->id)
                ->where('locale', $this->targetLocale)
                ->lockForUpdate()
                ->first();

            if ($translation === null) {
                return;
            }

            $current = Offer::query()->find($offer->id);
            if ($current === null) {
                return;
            }

            $currentHash = OfferHasher::hash($current->message, $current->source_locale);
            if ($currentHash !== $this->sourceHash) {
                return; // stale, discard
            }

            if ($failed) {
                $translation->translation_status = TranslationStatus::Failed;
                $translation->save();

                return;
            }

            $translation->message = $translatedMessage;
            $translation->source_hash = $currentHash;
            $translation->translation_status = TranslationStatus::Completed;
            $translation->translated_at = now();
            $translation->save();
        });
    }
}
