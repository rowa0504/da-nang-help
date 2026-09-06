<?php

namespace App\Actions\Offer;

use App\Enums\NotificationType;
use App\Enums\OfferStatus;
use App\Enums\ProviderVerificationStatus;
use App\Enums\ServiceRequestModerationStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\TranslationStatus;
use App\Enums\UserRole;
use App\Exceptions\DuplicateOfferException;
use App\Exceptions\InvalidOfferTransitionException;
use App\Jobs\SendNotificationEmailJob;
use App\Jobs\TranslateOfferJob;
use App\Models\Notification;
use App\Models\Offer;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\OfferHasher;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CreateOfferAction
{
    /**
     * @param  array{price: string|float, currency: string, message: string, available_at: ?string, source_locale: string}  $data
     */
    public function handle(User $provider, ServiceRequest $serviceRequest, array $data): Offer
    {
        $sourceHash = OfferHasher::hash($data['message'], $data['source_locale']);
        $targetLocales = array_diff(['en', 'ja', 'vi'], [$data['source_locale']]);

        $offer = DB::transaction(function () use ($provider, $serviceRequest, $data, $sourceHash, $targetLocales) {
            $locked = ServiceRequest::query()->lockForUpdate()->findOrFail($serviceRequest->id);

            if ($provider->role !== UserRole::Provider
                || $locked->customer_id === $provider->id
                || $locked->status !== ServiceRequestStatus::Open
                || $locked->moderation_status !== ServiceRequestModerationStatus::Visible) {
                throw new InvalidOfferTransitionException('This request is not open for offers.');
            }

            $profile = $provider->providerProfile;
            if ($profile === null
                || $profile->verification_status !== ProviderVerificationStatus::Approved
                || ! $profile->categories()->where('categories.id', $locked->category_id)->exists()
                || ! $profile->areas()->where('areas.id', $locked->area_id)->exists()) {
                throw new InvalidOfferTransitionException('This provider is not eligible to offer on this request.');
            }

            if (Offer::where('service_request_id', $locked->id)->where('provider_id', $provider->id)->exists()) {
                throw new DuplicateOfferException('You have already sent an offer for this request.');
            }

            $offer = new Offer($data);
            $offer->service_request_id = $locked->id;
            $offer->provider_id = $provider->id;
            $offer->status = OfferStatus::Pending;

            try {
                $offer->save();
            } catch (QueryException $e) {
                if (str_contains($e->getMessage(), 'offers_request_provider_unique')) {
                    throw new DuplicateOfferException('You have already sent an offer for this request.', previous: $e);
                }
                throw $e;
            }

            foreach ($targetLocales as $locale) {
                $offer->translations()->create([
                    'locale' => $locale,
                    'message' => $data['message'],
                    'source_hash' => $sourceHash,
                    'translation_status' => TranslationStatus::Pending,
                ]);
            }

            $notification = Notification::create([
                'user_id' => $locked->customer_id,
                'type' => NotificationType::OfferReceived,
                'data' => ['offer_id' => $offer->id, 'service_request_id' => $locked->id],
            ]);
            SendNotificationEmailJob::dispatch($notification->id)->afterCommit();

            return $offer;
        });

        foreach ($targetLocales as $locale) {
            TranslateOfferJob::dispatch($offer->id, $locale, $sourceHash);
        }

        return $offer;
    }
}
