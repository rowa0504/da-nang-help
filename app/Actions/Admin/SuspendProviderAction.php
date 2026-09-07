<?php

namespace App\Actions\Admin;

use App\Enums\ProviderVerificationStatus;
use App\Enums\UserRole;
use App\Exceptions\InvalidProviderVerificationTransitionException;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SuspendProviderAction
{
    /**
     * Suspension is a one-way transition from `approved` only (no path
     * back to `approved`, see the Phase 9 plan §2#2/#3). Existing pending
     * Offers and in-progress Jobs are left untouched: CreateOfferAction and
     * RequestFeedController/ServiceRequestPolicy::providerMatches() already
     * reject a non-`approved` provider, so new Offers/Feed access are
     * blocked as a side effect of this status change alone. JobPolicy never
     * reads verification_status, so a suspended provider's existing Jobs
     * continue normally.
     */
    public function handle(User $admin, ProviderProfile $providerProfile, string $note): ProviderProfile
    {
        return DB::transaction(function () use ($admin, $providerProfile, $note) {
            $locked = ProviderProfile::query()->lockForUpdate()->findOrFail($providerProfile->id);

            if ($admin->role !== UserRole::Admin
                || $locked->verification_status !== ProviderVerificationStatus::Approved) {
                throw new InvalidProviderVerificationTransitionException(
                    'Only an approved provider profile can be suspended.'
                );
            }

            $locked->verification_status = ProviderVerificationStatus::Suspended;
            $locked->suspended_at = now();
            $locked->verification_note = $note;
            $locked->save();

            return $locked;
        });
    }
}
