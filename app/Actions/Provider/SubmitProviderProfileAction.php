<?php

namespace App\Actions\Provider;

use App\Enums\ProviderVerificationStatus;
use App\Enums\UserRole;
use App\Exceptions\InvalidProviderVerificationTransitionException;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubmitProviderProfileAction
{
    /**
     * Create a new provider_profiles row, or resubmit a rejected one.
     *
     * @param  array{business_name: string, bio: ?string, category_ids: array<int>, area_ids: array<int>}  $data
     */
    public function handle(User $user, array $data): ProviderProfile
    {
        return DB::transaction(function () use ($user, $data) {
            // Lock the User row first so concurrent submissions from the
            // same Provider (double-tab, retried request) are serialized
            // rather than both observing "no profile yet" and racing to
            // create two rows.
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($lockedUser->role !== UserRole::Provider) {
                // Defence in depth: reachable only if this Action is ever
                // called outside the normal Policy-guarded HTTP path (e.g.
                // a future Artisan command or Seeder bug).
                throw new InvalidProviderVerificationTransitionException(
                    'Only Provider-role users may have a provider profile.'
                );
            }

            $profile = $lockedUser->providerProfile()->first();

            $isNew = $profile === null;
            $isResubmission = $profile !== null && $profile->verification_status === ProviderVerificationStatus::Rejected;

            if (! $isNew && ! $isResubmission) {
                throw new InvalidProviderVerificationTransitionException(
                    'A provider profile can only be submitted when none exists yet, or resubmitted while rejected.'
                );
            }

            if ($isNew) {
                $profile = new ProviderProfile();
                $profile->user_id = $lockedUser->id;
            }

            $profile->business_name = $data['business_name'];
            $profile->bio = $data['bio'] ?? null;
            $profile->verification_status = ProviderVerificationStatus::Pending;
            $profile->rejected_at = null;
            // verification_note is deliberately left untouched: on
            // resubmission it retains the previous rejection reason as
            // Admin-internal history until the next reject overwrites it.
            $profile->save();

            $profile->categories()->sync($data['category_ids']);
            $profile->areas()->sync($data['area_ids']);

            return $profile;
        });
    }
}
