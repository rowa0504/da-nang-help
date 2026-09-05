<?php

namespace App\Actions\Job;

use App\Enums\ServiceJobStatus;
use App\Enums\UserRole;
use App\Exceptions\InvalidJobTransitionException;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StartJobAction
{
    public function handle(User $provider, ServiceJob $job): ServiceJob
    {
        return DB::transaction(function () use ($provider, $job) {
            $locked = ServiceJob::query()->lockForUpdate()->findOrFail($job->id);

            if ($provider->role !== UserRole::Provider
                || $locked->provider_id !== $provider->id
                || $locked->status !== ServiceJobStatus::Assigned) {
                throw new InvalidJobTransitionException('This job cannot be started.');
            }

            $locked->status = ServiceJobStatus::InProgress;
            $locked->save();

            return $locked;
        });
    }
}
