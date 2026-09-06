<?php

namespace App\Policies;

use App\Enums\ServiceJobStatus;
use App\Enums\UserRole;
use App\Models\Review;
use App\Models\ServiceJob;
use App\Models\User;

class ReviewPolicy
{
    /**
     * Admin-only listing of all reviews for moderation.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function create(User $user, ServiceJob $job): bool
    {
        return $user->role === UserRole::Customer
            && $user->id === $job->customer_id
            && $job->status === ServiceJobStatus::Completed
            && ! Review::where('job_id', $job->id)->exists(); // matches the job_id-only UNIQUE constraint
    }

    public function hide(User $user, Review $review): bool
    {
        return $user->role === UserRole::Admin && ! $review->is_hidden;
    }
}
