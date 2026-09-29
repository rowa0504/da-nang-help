<?php

namespace App\Policies;

use App\Enums\ProviderVerificationStatus;
use App\Enums\UserRole;
use App\Models\ProviderProfile;
use App\Models\User;

class ProviderProfilePolicy
{
    /**
     * Admin-only listing of provider applications.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * The profile's own Provider, or an Admin, may view it.
     */
    public function view(User $user, ProviderProfile $profile): bool
    {
        return $user->id === $profile->user_id || $user->role === UserRole::Admin;
    }

    /**
     * Gate for /provider/profile GET (show, any state) and POST (submit — a
     * brand-new profile only; SubmitProviderProfileAction itself rejects
     * the case where one already exists). Only Provider-role users may
     * reach it at all. This route never takes an {id}, so there is no other
     * Provider's profile it could be pointed at — the URL design itself
     * prevents cross-Provider access; this ability only needs to check role.
     */
    public function create(User $user): bool
    {
        return $user->role === UserRole::Provider;
    }

    /**
     * Gate for /provider/profile PATCH (editing an *existing* profile from
     * Pending, Rejected, or Approved). Unlike create() above, this route
     * still takes no {id} — the Controller resolves $request->user()'s own
     * profile before calling $this->authorize('update', $profile) — so this
     * ability is the one place that must explicitly re-check ownership
     * rather than relying on the URL shape alone. UpdateProviderProfileAction
     * re-verifies all three conditions again after acquiring a row lock.
     */
    public function update(User $user, ProviderProfile $profile): bool
    {
        return $user->id === $profile->user_id
            && $user->role === UserRole::Provider
            && $profile->verification_status !== ProviderVerificationStatus::Suspended;
    }

    /**
     * Only an Admin may approve, and only while the profile is pending.
     * The Action re-checks this after acquiring a row lock, so this is a
     * fast, non-authoritative pre-check.
     */
    public function approve(User $user, ProviderProfile $profile): bool
    {
        return $user->role === UserRole::Admin
            && $profile->verification_status === ProviderVerificationStatus::Pending;
    }

    /**
     * Only an Admin may reject, and only while the profile is pending.
     */
    public function reject(User $user, ProviderProfile $profile): bool
    {
        return $user->role === UserRole::Admin
            && $profile->verification_status === ProviderVerificationStatus::Pending;
    }

    /**
     * Only an Admin may suspend, and only while the profile is approved.
     * The Action re-checks this after acquiring a row lock, so this is a
     * fast, non-authoritative pre-check.
     */
    public function suspend(User $user, ProviderProfile $profile): bool
    {
        return $user->role === UserRole::Admin
            && $profile->verification_status === ProviderVerificationStatus::Approved;
    }
}
