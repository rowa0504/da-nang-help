<?php

namespace App\Actions\Admin;

use App\Actions\Review\RecalculateProviderRatingAction;
use App\Enums\UserRole;
use App\Exceptions\InvalidReviewTransitionException;
use App\Exceptions\ProviderProfileMissingForReviewException;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class HideReviewAction
{
    public function __construct(private readonly RecalculateProviderRatingAction $recalculate)
    {
    }

    public function handle(User $admin, Review $review): Review
    {
        return DB::transaction(function () use ($admin, $review) {
            // Lock ordering: ProviderProfile before Review, same as
            // CreateReviewAction. ratee_id is fixed at creation and never
            // changes, so reading it from the unlocked $review is safe.
            $profile = ProviderProfile::where('user_id', $review->ratee_id)->lockForUpdate()->first();
            if ($profile === null) {
                throw new ProviderProfileMissingForReviewException($review->ratee_id);
            }

            $locked = Review::query()->lockForUpdate()->findOrFail($review->id);

            if ($admin->role !== UserRole::Admin || $locked->is_hidden) {
                throw new InvalidReviewTransitionException('This review cannot be hidden.');
            }

            $locked->is_hidden = true;
            $locked->hidden_at = now();
            $locked->hidden_by = $admin->id;
            $locked->save();

            $this->recalculate->handle($profile);

            return $locked;
        });
    }
}
