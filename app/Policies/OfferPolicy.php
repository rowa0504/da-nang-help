<?php

namespace App\Policies;

use App\Enums\OfferStatus;
use App\Enums\ProviderVerificationStatus;
use App\Enums\ServiceRequestModerationStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\UserRole;
use App\Models\Offer;
use App\Models\ServiceRequest;
use App\Models\User;

class OfferPolicy
{
    /**
     * Gate for GET /requests/{id}/offers: the posting Customer or Admin only.
     */
    public function viewAny(User $user, ServiceRequest $serviceRequest): bool
    {
        return $user->id === $serviceRequest->customer_id || $user->role === UserRole::Admin;
    }

    /**
     * Gate for POST /requests/{id}/offers.
     */
    public function create(User $user, ServiceRequest $serviceRequest): bool
    {
        if ($user->role !== UserRole::Provider) {
            return false;
        }

        if ($serviceRequest->customer_id === $user->id) {
            // Currently unreachable given the app's single-role-per-user
            // design, but Blueprint states this constraint explicitly, so
            // it is enforced defensively (see Phase 5 plan 1節差分4).
            return false;
        }

        if ($serviceRequest->status !== ServiceRequestStatus::Open) {
            return false;
        }

        if ($serviceRequest->moderation_status !== ServiceRequestModerationStatus::Visible) {
            return false;
        }

        if (Offer::where('service_request_id', $serviceRequest->id)->where('provider_id', $user->id)->exists()) {
            // FR-25/FR-32: any existing offer (any status) blocks a new one.
            return false;
        }

        $profile = $user->providerProfile;

        return $profile !== null
            && $profile->verification_status === ProviderVerificationStatus::Approved
            && $profile->categories()->where('categories.id', $serviceRequest->category_id)->exists()
            && $profile->areas()->where('areas.id', $serviceRequest->area_id)->exists();
    }

    public function update(User $user, Offer $offer): bool
    {
        return $user->id === $offer->provider_id && $offer->status === OfferStatus::Pending;
    }

    public function withdraw(User $user, Offer $offer): bool
    {
        return $user->id === $offer->provider_id && $offer->status === OfferStatus::Pending;
    }

    public function accept(User $user, Offer $offer): bool
    {
        return $user->id === $offer->serviceRequest->customer_id
            && $offer->status === OfferStatus::Pending
            && $offer->serviceRequest->status === ServiceRequestStatus::Open;
    }

    public function reject(User $user, Offer $offer): bool
    {
        return $user->id === $offer->serviceRequest->customer_id && $offer->status === OfferStatus::Pending;
    }
}
