<?php

namespace App\Actions\Admin;

use App\Enums\ProviderVerificationStatus;
use App\Exceptions\InvalidProviderVerificationTransitionException;
use App\Models\ProviderProfile;
use Illuminate\Support\Facades\DB;

class RejectProviderAction
{
    public function handle(ProviderProfile $providerProfile, string $note): ProviderProfile
    {
        return DB::transaction(function () use ($providerProfile, $note) {
            $locked = ProviderProfile::query()->lockForUpdate()->findOrFail($providerProfile->id);

            if ($locked->verification_status !== ProviderVerificationStatus::Pending) {
                throw new InvalidProviderVerificationTransitionException(
                    'Only a pending provider profile can be rejected.'
                );
            }

            $locked->verification_status = ProviderVerificationStatus::Rejected;
            $locked->rejected_at = now();
            $locked->verification_note = $note;
            $locked->save();

            return $locked;
        });
    }
}
