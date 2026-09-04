<?php

namespace App\Actions\Admin;

use App\Enums\ProviderVerificationStatus;
use App\Exceptions\InvalidProviderVerificationTransitionException;
use App\Models\ProviderProfile;
use Illuminate\Support\Facades\DB;

class ApproveProviderAction
{
    public function handle(ProviderProfile $providerProfile): ProviderProfile
    {
        return DB::transaction(function () use ($providerProfile) {
            $locked = ProviderProfile::query()->lockForUpdate()->findOrFail($providerProfile->id);

            // Re-verified after acquiring the lock: the Policy check that
            // ran before this Action only guarantees the status was
            // `pending` at request time, not at the moment the lock is
            // granted. This is what protects against two Admins (or a
            // double-submitted request) racing approve/reject on the same
            // application.
            if ($locked->verification_status !== ProviderVerificationStatus::Pending) {
                throw new InvalidProviderVerificationTransitionException(
                    'Only a pending provider profile can be approved.'
                );
            }

            $locked->verification_status = ProviderVerificationStatus::Approved;
            $locked->approved_at = now();
            $locked->rejected_at = null;
            $locked->save();

            return $locked;
        });
    }
}
