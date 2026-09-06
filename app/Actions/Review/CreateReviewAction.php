<?php

namespace App\Actions\Review;

use App\Enums\ServiceJobStatus;
use App\Enums\UserRole;
use App\Exceptions\DuplicateReviewException;
use App\Exceptions\InvalidReviewTransitionException;
use App\Exceptions\ProviderProfileMissingForReviewException;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CreateReviewAction
{
    public function __construct(private readonly RecalculateProviderRatingAction $recalculate)
    {
    }

    /**
     * @param  array{rating: int, comment: ?string}  $data
     */
    public function handle(User $customer, ServiceJob $job, array $data): Review
    {
        return DB::transaction(function () use ($customer, $job, $data) {
            $lockedJob = ServiceJob::query()->lockForUpdate()->findOrFail($job->id);

            if ($customer->role !== UserRole::Customer
                || $lockedJob->customer_id !== $customer->id
                || $lockedJob->status !== ServiceJobStatus::Completed) {
                throw new InvalidReviewTransitionException('This job cannot be reviewed.');
            }

            if (Review::where('job_id', $lockedJob->id)->exists()) {
                throw new DuplicateReviewException('You have already reviewed this job.');
            }

            // Lock ordering: ProviderProfile is always locked before the
            // Review row is created/modified, across both this Action and
            // HideReviewAction, so concurrent rating changes for the same
            // provider serialize instead of racing on a stale average.
            $profile = ProviderProfile::where('user_id', $lockedJob->provider_id)->lockForUpdate()->first();
            if ($profile === null) {
                throw new ProviderProfileMissingForReviewException($lockedJob->provider_id);
            }

            $review = new Review($data);
            $review->job_id = $lockedJob->id;
            $review->rater_id = $customer->id;
            $review->ratee_id = $lockedJob->provider_id;

            try {
                $review->save();
            } catch (QueryException $e) {
                if (($e->errorInfo[1] ?? null) === 1062
                    && str_contains($e->getMessage(), 'reviews_job_id_unique')) {
                    throw new DuplicateReviewException('You have already reviewed this job.', previous: $e);
                }
                throw $e;
            }

            $this->recalculate->handle($profile);

            return $review;
        });
    }
}
