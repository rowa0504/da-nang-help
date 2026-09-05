<?php

namespace App\Actions\Job;

use App\Enums\ServiceJobStatus;
use App\Enums\UserRole;
use App\Exceptions\InvalidJobTransitionException;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CancelJobAction
{
    public function handle(User $user, ServiceJob $job): ServiceJob
    {
        return DB::transaction(function () use ($user, $job) {
            $locked = ServiceJob::query()->lockForUpdate()->findOrFail($job->id);

            $isCustomer = $user->role === UserRole::Customer && $locked->customer_id === $user->id;
            $isProvider = $user->role === UserRole::Provider && $locked->provider_id === $user->id;

            if ((! $isCustomer && ! $isProvider)
                || ! in_array($locked->status, [ServiceJobStatus::Assigned, ServiceJobStatus::InProgress], true)) {
                throw new InvalidJobTransitionException('This job cannot be cancelled.');
            }

            $locked->status = ServiceJobStatus::Cancelled;
            $locked->cancelled_at = now();
            $locked->save();
            // The Service Request is intentionally not reopened — it stays
            // `assigned` forever once a Job has existed for it (see Phase 6
            // plan, section 1).

            return $locked;
        });
    }
}
