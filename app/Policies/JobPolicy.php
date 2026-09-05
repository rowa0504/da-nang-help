<?php

namespace App\Policies;

use App\Enums\ServiceJobStatus;
use App\Enums\UserRole;
use App\Models\ServiceJob;
use App\Models\User;

class JobPolicy
{
    /**
     * Customer/Provider see only their own jobs (filtered in the
     * controller); Admin may view the full list. Nobody outside these
     * three roles has any business here.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Customer, UserRole::Provider, UserRole::Admin], true);
    }

    public function view(User $user, ServiceJob $job): bool
    {
        return $user->id === $job->customer_id || $user->id === $job->provider_id || $user->role === UserRole::Admin;
    }

    /**
     * State-changing actions are Customer/Provider only — Admin can view
     * Jobs (FR-45/46 style moderation boundary) but never mutate them here.
     */
    public function start(User $user, ServiceJob $job): bool
    {
        return $user->role === UserRole::Provider
            && $user->id === $job->provider_id
            && $job->status === ServiceJobStatus::Assigned;
    }

    public function reportCompletion(User $user, ServiceJob $job): bool
    {
        return $user->role === UserRole::Provider
            && $user->id === $job->provider_id
            && $job->status === ServiceJobStatus::InProgress;
    }

    public function confirmCompletion(User $user, ServiceJob $job): bool
    {
        return $user->role === UserRole::Customer
            && $user->id === $job->customer_id
            && $job->status === ServiceJobStatus::AwaitingConfirmation;
    }

    public function cancel(User $user, ServiceJob $job): bool
    {
        $isCustomer = $user->role === UserRole::Customer && $user->id === $job->customer_id;
        $isProvider = $user->role === UserRole::Provider && $user->id === $job->provider_id;

        return ($isCustomer || $isProvider)
            && in_array($job->status, [ServiceJobStatus::Assigned, ServiceJobStatus::InProgress], true);
    }
}
