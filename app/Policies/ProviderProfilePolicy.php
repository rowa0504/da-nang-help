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
     * Gate for /provider/profile (GET + POST): only Provider-role users may
     * reach it at all. This route never takes an {id}, so there is no other
     * Provider's profile it could be pointed at — the URL design itself
     * prevents cross-Provider access; this ability only needs to check role.
     */
    public function create(User $user): bool
    {
        return $user->role === UserRole::Provider;
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
}
