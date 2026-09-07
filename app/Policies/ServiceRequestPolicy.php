<?php

namespace App\Policies;

use App\Enums\ProviderVerificationStatus;
use App\Enums\ServiceRequestModerationStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\UserRole;
use App\Models\Offer;
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
     * view while the request is open and visible; once the request leaves
     * `open` (assigned/cancelled), a Provider who already has an offer on
     * it may keep viewing the public fields and their own offer's status
     * (Phase 5). A hidden request is never viewable by a Provider, even one
     * with an existing offer.
     */
    public function view(User $user, ServiceRequest $serviceRequest): bool
    {
        if ($user->id === $serviceRequest->customer_id || $user->role === UserRole::Admin) {
            return true;
        }

        if ($user->role !== UserRole::Provider) {
            return false;
        }

        if ($serviceRequest->moderation_status !== ServiceRequestModerationStatus::Visible) {
            return false;
        }

        if ($serviceRequest->status === ServiceRequestStatus::Open) {
            return $this->providerMatches($user, $serviceRequest);
        }

        return Offer::where('service_request_id', $serviceRequest->id)
            ->where('provider_id', $user->id)
            ->exists();
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

    /**
     * Admin-only listing of all requests for moderation (GET
     * /admin/requests) — distinct from viewAny() above, which gates the
     * Customer's own-requests list (GET /requests) and must not be
     * overloaded for this Admin-wide view.
     */
    public function viewAnyForModeration(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * Only an Admin may hide, and only while the request is still visible.
     * No unhide ability exists (matches ReviewPolicy::hide()'s one-way
     * pattern in Phase 7).
     */
    public function hide(User $user, ServiceRequest $serviceRequest): bool
    {
        return $user->role === UserRole::Admin
            && $serviceRequest->moderation_status === ServiceRequestModerationStatus::Visible;
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
