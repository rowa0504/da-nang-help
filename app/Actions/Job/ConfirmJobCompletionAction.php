<?php

namespace App\Actions\Job;

use App\Enums\JobCompletionMode;
use App\Enums\UserRole;
use App\Exceptions\InvalidJobTransitionException;
use App\Models\ServiceJob;
use App\Models\User;

class ConfirmJobCompletionAction
{
    public function __construct(private readonly CompleteJobAction $completeJobAction)
    {
    }

    public function handle(User $customer, ServiceJob $job): ServiceJob
    {
        // customer_id is fixed at job creation and never changes, so this
        // can be checked without a lock. The mutable status is re-verified,
        // under lock, inside CompleteJobAction.
        if ($customer->role !== UserRole::Customer || $job->customer_id !== $customer->id) {
            throw new InvalidJobTransitionException('This job cannot be confirmed.');
        }

        return $this->completeJobAction->handle($job, JobCompletionMode::Manual);
    }
}
