<?php

namespace App\Actions\Job;

use App\Enums\ServiceJobStatus;
use App\Enums\UserRole;
use App\Exceptions\InvalidJobTransitionException;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReportJobCompletionAction
{
    public function handle(User $provider, ServiceJob $job): ServiceJob
    {
        return DB::transaction(function () use ($provider, $job) {
            $locked = ServiceJob::query()->lockForUpdate()->findOrFail($job->id);

            if ($provider->role !== UserRole::Provider
                || $locked->provider_id !== $provider->id
                || $locked->status !== ServiceJobStatus::InProgress) {
                throw new InvalidJobTransitionException('This job cannot be reported as complete.');
            }

            $locked->status = ServiceJobStatus::AwaitingConfirmation;
            $locked->provider_completed_at = now();
            $locked->auto_confirm_at = now()->addDays(config('services.auto_confirm_days'));
            $locked->save();

            return $locked;
        });
    }
}
