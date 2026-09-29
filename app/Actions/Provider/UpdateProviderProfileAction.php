<?php

namespace App\Actions\Provider;

use App\Enums\ProviderVerificationStatus;
use App\Enums\UserRole;
use App\Exceptions\InvalidProviderVerificationTransitionException;
use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateProviderProfileAction
{
    /**
     * Edit an existing provider_profiles row — from Pending, Rejected, or
     * Approved — always transitioning it back to Pending. Editing while
     * Suspended is forbidden. Unlike SubmitProviderProfileAction, this never
     * creates a row: the Controller resolves the caller's existing profile
     * before calling this.
     *
     * @param  array{business_name: string, bio: ?string, category_ids: array<int>, area_ids: array<int>, other_service_details: ?string}  $data
     */
    public function handle(User $user, ProviderProfile $profile, array $data): ProviderProfile
    {
        return DB::transaction(function () use ($user, $profile, $data) {
            // Lock the row directly (not the User row): Admin's
            // Approve/Reject/Suspend actions all lock this same
            // ProviderProfile row, so this is what actually serializes a
            // concurrent Provider edit against an Admin decision. Whichever
            // side's transaction commits first wins; the other re-reads the
            // now-current state below and fails as an invalid transition —
            // both are not expected to succeed.
            $locked = ProviderProfile::query()->lockForUpdate()->findOrFail($profile->id);

            if ($locked->user_id !== $user->id) {
                // Defence in depth: reachable only if this Action is ever
                // called outside the normal Policy-guarded HTTP path, where
                // the Controller always resolves $request->user()'s own
                // profile before calling this.
                throw new InvalidProviderVerificationTransitionException(
                    'A provider profile can only be edited by its own Provider.'
                );
            }

            if ($user->role !== UserRole::Provider) {
                throw new InvalidProviderVerificationTransitionException(
                    'Only Provider-role users may edit a provider profile.'
                );
            }

            if ($locked->verification_status === ProviderVerificationStatus::Suspended) {
                throw new InvalidProviderVerificationTransitionException(
                    'A provider profile cannot be edited while suspended.'
                );
            }

            $locked->business_name = $data['business_name'];
            $locked->bio = $data['bio'] ?? null;
            $locked->verification_status = ProviderVerificationStatus::Pending;
            // Every edit — regardless of the state it started from — moves
            // to Pending and clears the full review history. No new column
            // records "this was previously approved": Admin sees it exactly
            // like a first-time application (deliberate — see plan notes).
            $locked->approved_at = null;
            $locked->rejected_at = null;
            $locked->suspended_at = null;
            $locked->verification_note = null;
            $locked->other_service_details = $this->isOtherCategorySelected($data['category_ids'])
                ? $data['other_service_details']
                : null;
            $locked->save();

            $locked->categories()->sync($data['category_ids']);
            $locked->areas()->sync($data['area_ids']);

            return $locked;
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
