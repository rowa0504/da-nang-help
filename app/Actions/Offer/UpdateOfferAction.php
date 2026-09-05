<?php

namespace App\Actions\Offer;

use App\Enums\OfferStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\TranslationStatus;
use App\Enums\UserRole;
use App\Exceptions\InvalidOfferTransitionException;
use App\Jobs\TranslateOfferJob;
use App\Models\Offer;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\OfferHasher;
use Illuminate\Support\Facades\DB;

class UpdateOfferAction
{
    /**
     * @param  array{price: string|float, currency: string, message: string, available_at: ?string, source_locale: string}  $data
     */
    public function handle(User $provider, Offer $offer, array $data): Offer
    {
        $dispatchLocales = [];
        $newSourceHash = null;

        $updated = DB::transaction(function () use ($provider, $offer, $data, &$dispatchLocales, &$newSourceHash) {
            // Lock Service Request before Offer, same order as AcceptOfferAction,
            // to keep a single consistent lock ordering across Actions.
            $lockedRequest = ServiceRequest::query()->lockForUpdate()->findOrFail($offer->service_request_id);
            $lockedOffer = Offer::query()->lockForUpdate()->findOrFail($offer->id);

            if ($provider->role !== UserRole::Provider
                || $lockedOffer->service_request_id !== $lockedRequest->id
                || $lockedOffer->provider_id !== $provider->id
                || $lockedOffer->status !== OfferStatus::Pending
                || $lockedRequest->status !== ServiceRequestStatus::Open) {
                throw new InvalidOfferTransitionException('This offer can no longer be edited.');
            }

            $messageChanged = $data['message'] !== $lockedOffer->message;
            $sourceLocaleChanged = $data['source_locale'] !== $lockedOffer->source_locale;

            $lockedOffer->fill($data);
            $lockedOffer->save();

            if ($messageChanged || $sourceLocaleChanged) {
                $newSourceHash = OfferHasher::hash($data['message'], $data['source_locale']);

                if ($sourceLocaleChanged) {
                    // The new source_locale is now the original text itself,
                    // so any translation row for that locale is no longer
                    // needed (FR-20: no self-translation is stored).
                    $lockedOffer->translations()->where('locale', $data['source_locale'])->delete();
                }

                $dispatchLocales = array_diff(['en', 'ja', 'vi'], [$data['source_locale']]);
                foreach ($dispatchLocales as $locale) {
                    // updateOrCreate absorbs both cases uniformly: a locale
                    // that was already a target keeps its row (reset to
                    // pending), and the old source_locale (now a target for
                    // the first time) gets a fresh row.
                    $lockedOffer->translations()->updateOrCreate(
                        ['locale' => $locale],
                        [
                            'message' => $data['message'],
                            'source_hash' => $newSourceHash,
                            'translation_status' => TranslationStatus::Pending->value,
                            'translated_at' => null,
                        ]
                    );
                }
            }

            return $lockedOffer;
        });

        foreach ($dispatchLocales as $locale) {
            TranslateOfferJob::dispatch($updated->id, $locale, $newSourceHash);
        }

        return $updated;
    }
}
