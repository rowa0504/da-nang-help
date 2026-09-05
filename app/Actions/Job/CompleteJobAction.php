<?php

namespace App\Actions\Job;

use App\Enums\JobCompletionMode;
use App\Enums\ServiceJobStatus;
use App\Exceptions\InvalidJobTransitionException;
use App\Exceptions\ProviderProfileMissingForJobException;
use App\Models\ProviderProfile;
use App\Models\ServiceJob;
use Illuminate\Support\Facades\DB;

class CompleteJobAction
{
    /**
     * Locks the job itself, re-verifies it is still awaiting_confirmation
     * (and, for the auto-confirm path, that auto_confirm_at has actually
     * arrived), then completes it and increments the provider's completed
     * job count — all inside one transaction. Safe to call repeatedly or
     * concurrently: a second call always finds a non-awaiting_confirmation
     * status after acquiring the lock and throws instead of double-counting.
     * Callers do not need to hold their own lock on $job first.
     */
    public function handle(ServiceJob $job, JobCompletionMode $mode): ServiceJob
    {
        return DB::transaction(function () use ($job, $mode) {
            $locked = ServiceJob::query()->lockForUpdate()->findOrFail($job->id);

            if ($locked->status !== ServiceJobStatus::AwaitingConfirmation) {
                throw new InvalidJobTransitionException('This job cannot be completed.');
            }

            if ($mode === JobCompletionMode::Auto
                && ($locked->auto_confirm_at === null || $locked->auto_confirm_at->isFuture())) {
                throw new InvalidJobTransitionException('This job is not yet due for auto-confirmation.');
            }

            $profile = ProviderProfile::where('user_id', $locked->provider_id)->lockForUpdate()->first();
            if ($profile === null) {
                throw new ProviderProfileMissingForJobException($locked->id, $locked->provider_id);
            }

            $locked->status = ServiceJobStatus::Completed;
            $locked->completed_at = now();
            if ($mode === JobCompletionMode::Manual) {
                $locked->customer_confirmed_at = now();
            }
            $locked->save();

            $profile->increment('completed_jobs_count');

            return $locked;
        });
    }
}
