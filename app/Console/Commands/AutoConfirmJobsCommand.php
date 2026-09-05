<?php

namespace App\Console\Commands;

use App\Actions\Job\CompleteJobAction;
use App\Enums\JobCompletionMode;
use App\Enums\ServiceJobStatus;
use App\Exceptions\InvalidJobTransitionException;
use App\Exceptions\ProviderProfileMissingForJobException;
use App\Models\ServiceJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoConfirmJobsCommand extends Command
{
    protected $signature = 'app:auto-confirm-jobs';

    protected $description = 'Auto-complete jobs whose auto_confirm_at has passed without customer confirmation.';

    public function handle(CompleteJobAction $completeJobAction): int
    {
        ServiceJob::query()
            ->where('status', ServiceJobStatus::AwaitingConfirmation->value)
            ->where('auto_confirm_at', '<=', now())
            ->chunkById(100, function ($jobs) use ($completeJobAction) {
                foreach ($jobs as $job) {
                    try {
                        $completeJobAction->handle($job, JobCompletionMode::Auto);
                    } catch (InvalidJobTransitionException) {
                        // Already completed/cancelled by the time we got to
                        // it, or not actually due yet — CompleteJobAction's
                        // own lock-and-recheck already handled the race.
                        continue;
                    } catch (ProviderProfileMissingForJobException $e) {
                        // Skip only this job; a real data-integrity problem
                        // worth surfacing, but not worth losing the rest of
                        // the batch over.
                        Log::error($e->getMessage(), ['job_id' => $job->id, 'provider_id' => $job->provider_id]);
                        continue;
                    }
                }
            });

        return self::SUCCESS;
    }
}
