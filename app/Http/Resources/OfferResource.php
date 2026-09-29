<?php

namespace App\Http\Resources;

use App\Enums\ProviderVerificationStatus;
use App\Enums\UserRole;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Every consumer of this Resource has already passed OfferPolicy (the
 * posting Customer, the offering Provider, or an Admin), unlike
 * ServiceRequestResource which serves several audiences behind one
 * endpoint. So this class only decides which *additional* editing-related
 * fields (original_message/source_locale) to add for the offering
 * Provider/Admin — it never re-implements the access decision itself.
 */
class OfferResource extends JsonResource
{
    public function toArray($request): array
    {
        $viewer = $request->user();
        $isOwnerOrAdmin = $viewer->id === $this->provider_id || $viewer->role === UserRole::Admin;
        $viewerLocale = $viewer->locale ?? 'en';
        $messageTranslationStatus = $this->translationStatusFor($viewerLocale);
        // Minimized at the response stage, not just hidden by the frontend:
        // a Provider's other_service_details is only relevant (and only
        // sent to the client) when this Offer's own request was posted
        // under the "other" category.
        $isOtherCategoryRequest = $this->serviceRequest->category->slug === 'other';
        $providerVerificationStatus = $this->provider->providerProfile?->verification_status;
        $providerIsApproved = $providerVerificationStatus === ProviderVerificationStatus::Approved;
        // A Provider can edit their own approved profile back to Pending
        // (see UpdateProviderProfileAction) without any snapshot of the
        // previously-approved text — so other_service_details can now hold
        // content an Admin hasn't reviewed yet. Withhold it from the
        // Customer whenever the Provider isn't currently Approved; the
        // Provider (viewing their own Offer) and Admin still see it
        // regardless, since they have legitimate reason to (the Provider
        // wrote it, Admin needs it to review).
        $showOtherServiceDetails = $isOtherCategoryRequest && ($providerIsApproved || $isOwnerOrAdmin);

        return [
            'id' => $this->id,
            'price' => $this->price, // decimal:2 cast string, never floated (see model)
            'currency' => $this->currency,
            'message' => $this->translatedMessageFor($viewerLocale),
            'message_translation' => [
                'status' => $messageTranslationStatus,
                'source_locale' => $this->source_locale,
                'original' => $this->message,
            ],
            'available_at' => $this->available_at?->toIso8601String(),
            'status' => $this->status->value,
            'created_at' => $this->created_at->toIso8601String(),
            'provider' => [
                'id' => $this->provider_id,
                'business_name' => $this->provider->providerProfile?->business_name,
                'avg_rating' => $this->provider->providerProfile?->avg_rating ?? '0.00',
                'completed_jobs_count' => $this->provider->providerProfile?->completed_jobs_count ?? 0,
                'verification_status' => $providerVerificationStatus?->value,
                'other_service_details' => $showOtherServiceDetails ? $this->provider->providerProfile?->other_service_details : null,
            ],
            ...$isOwnerOrAdmin ? [
                'original_message' => $this->message,
                'source_locale' => $this->source_locale,
            ] : [],
        ];
    }

    /**
     * null means "nothing to show beyond the plain text" — either the
     * viewer's own locale already equals the source locale (no translation
     * needed at all), or no translation row exists yet for their locale.
     * Otherwise returns the row's actual status ('pending'/'completed'/
     * 'failed') as a plain string, so the frontend can distinguish all
     * three instead of collapsing them into one boolean.
     */
    private function translationStatusFor(string $viewerLocale): ?string
    {
        if ($viewerLocale === $this->source_locale) {
            return null;
        }

        $translation = $this->translations->firstWhere('locale', $viewerLocale);

        return $translation?->translation_status?->value;
    }
}
