<?php

namespace App\Actions\Review;

use App\Models\ProviderProfile;
use App\Models\Review;

class RecalculateProviderRatingAction
{
    /**
     * $lockedProfile must already be locked (lockForUpdate) by the caller,
     * inside the same transaction, and locked *before* the Review row that
     * triggered this recalculation was created/modified (see
     * CreateReviewAction/HideReviewAction). This method itself never
     * acquires a lock, so it is safe to call from multiple call sites
     * without risking deadlocks from inconsistent lock ordering.
     */
    public function handle(ProviderProfile $lockedProfile): void
    {
        $average = Review::where('ratee_id', $lockedProfile->user_id)->where('is_hidden', false)->avg('rating');
        $lockedProfile->avg_rating = $average !== null ? round((float) $average, 2) : 0;
        $lockedProfile->save();
    }
}
