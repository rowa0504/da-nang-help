<?php

namespace App\Policies;

use App\Enums\ProviderVerificationStatus;
use App\Enums\ServiceRequestModerationStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\UserRole;
use App\Models\ServiceRequest;
use App\Models\User;

class ServiceRequestPolicy
{
    /**
     * Only Customers may see their own list of submitted requests.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Customer;
    }

    /**
     * Gate for /requests/create and POST /requests: Customer role only.
     */
    public function create(User $user): bool
    {
        return $user->role === UserRole::Customer;
    }

    /**
     * The posting Customer and Admin may always view, regardless of
     * moderation_status. An approved Provider whose category/area match may
     * view only while the request is open and visible.
     */
    public function view(User $user, ServiceRequest $serviceRequest): bool
    {
        if ($user->id === $serviceRequest->customer_id || $user->role === UserRole::Admin) {
            return true;
        }

        return $user->role === UserRole::Provider
            && $serviceRequest->status === ServiceRequestStatus::Open
            && $serviceRequest->moderation_status === ServiceRequestModerationStatus::Visible
            && $this->providerMatches($user, $serviceRequest);
    }

    /**
     * Only the posting Customer, only while the request is still open
     * (before any Offer has been accepted).
     */
    public function cancel(User $user, ServiceRequest $serviceRequest): bool
    {
        return $user->id === $serviceRequest->customer_id
            && $serviceRequest->status === ServiceRequestStatus::Open;
    }

    /**
     * Gate for GET /provider/requests: an approved Provider only.
     */
    public function viewFeed(User $user): bool
    {
        return $user->role === UserRole::Provider
            && $user->providerProfile?->verification_status === ProviderVerificationStatus::Approved;
    }

    private function providerMatches(User $user, ServiceRequest $serviceRequest): bool
    {
        $profile = $user->providerProfile;
        if ($profile === null || $profile->verification_status !== ProviderVerificationStatus::Approved) {
            return false;
        }

        return $profile->categories()->where('categories.id', $serviceRequest->category_id)->exists()
            && $profile->areas()->where('areas.id', $serviceRequest->area_id)->exists();
    }
}
