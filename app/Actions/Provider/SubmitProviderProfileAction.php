<?php

namespace App\Actions\Provider;

use App\Enums\ProviderVerificationStatus;
use App\Enums\UserRole;
use App\Exceptions\InvalidProviderVerificationTransitionException;
use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubmitProviderProfileAction
{
    /**
     * Create a new provider_profiles row, or resubmit a rejected one.
     *
     * @param  array{business_name: string, bio: ?string, category_ids: array<int>, area_ids: array<int>, other_service_details: ?string}  $data
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
            // Never trust the submitted other_service_details value on its
            // own: whether it is kept or forced to null is decided from the
            // *validated* category_ids, not from whether the field itself
            // was present in $data.
            $profile->other_service_details = $this->isOtherCategorySelected($data['category_ids'])
                ? $data['other_service_details']
                : null;
            // verification_note is deliberately left untouched: on
            // resubmission it retains the previous rejection reason as
            // Admin-internal history until the next reject overwrites it.
            $profile->save();

            $profile->categories()->sync($data['category_ids']);
            $profile->areas()->sync($data['area_ids']);

            return $profile;
        });
    }

    /**
     * @param  array<int>  $categoryIds
     */
    private function isOtherCategorySelected(array $categoryIds): bool
    {
        $otherCategoryId = Category::query()->where('slug', 'other')->value('id');
        if ($otherCategoryId === null) {
            return false;
        }

        $normalizedCategoryIds = array_map('intval', $categoryIds);

        return in_array((int) $otherCategoryId, $normalizedCategoryIds, true);
    }
}
