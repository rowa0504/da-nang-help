<?php

namespace Tests\Unit;

use App\Actions\Job\CompleteJobAction;
use App\Enums\JobCompletionMode;
use App\Enums\ServiceJobStatus;
use App\Models\ProviderProfile;
use App\Models\ServiceJob;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class AutoConfirmJobsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function withProviderProfile(ServiceJob $job): ServiceJob
    {
        ProviderProfile::factory()->forUser($job->provider)->approved()->create();

        return $job;
    }

    public function test_only_past_due_awaiting_confirmation_jobs_are_completed(): void
    {
        $due = $this->withProviderProfile(
            ServiceJob::factory()->awaitingConfirmation()->create(['auto_confirm_at' => now()->subMinute()])
        );
        $notYetDue = $this->withProviderProfile(
            ServiceJob::factory()->awaitingConfirmation()->create(['auto_confirm_at' => now()->addDay()])
        );

        Artisan::call('app:auto-confirm-jobs');

        $this->assertSame(ServiceJobStatus::Completed, $due->fresh()->status);
        $this->assertSame(ServiceJobStatus::AwaitingConfirmation, $notYetDue->fresh()->status);
    }

    public function test_already_completed_or_cancelled_jobs_are_not_touched(): void
    {
        $completed = ServiceJob::factory()->completed()->create();
        $cancelled = ServiceJob::factory()->cancelled()->create();

        Artisan::call('app:auto-confirm-jobs');

        $this->assertSame(ServiceJobStatus::Completed, $completed->fresh()->status);
        $this->assertSame(ServiceJobStatus::Cancelled, $cancelled->fresh()->status);
    }

    public function test_manual_confirmation_immediately_before_the_batch_runs_is_not_double_processed(): void
    {
        $job = $this->withProviderProfile(
            ServiceJob::factory()->awaitingConfirmation()->create(['auto_confirm_at' => now()->subMinute()])
        );

        // Simulates the Customer confirming manually a moment before the
        // scheduled batch picks up the same job.
        app(CompleteJobAction::class)->handle($job, JobCompletionMode::Manual);
        $countAfterManual = $job->provider->providerProfile->fresh()->completed_jobs_count;

        Artisan::call('app:auto-confirm-jobs');

        $fresh = $job->fresh();
        $this->assertSame(ServiceJobStatus::Completed, $fresh->status);
        $this->assertNotNull($fresh->customer_confirmed_at); // still the manual confirmation, not overwritten
        $this->assertSame($countAfterManual, $job->provider->providerProfile->fresh()->completed_jobs_count);
    }

    public function test_more_than_one_chunk_of_jobs_is_fully_processed(): void
    {
        $jobs = ServiceJob::factory()
            ->count(105)
            ->awaitingConfirmation()
            ->create(['auto_confirm_at' => now()->subMinute()])
            ->each(fn (ServiceJob $job) => $this->withProviderProfile($job));

        Artisan::call('app:auto-confirm-jobs');

        $this->assertSame(105, $jobs->fresh()->where('status', ServiceJobStatus::Completed)->count());
    }

    public function test_a_job_whose_provider_has_no_profile_is_skipped_and_logged_while_others_still_complete(): void
    {
        $missingProfile = ServiceJob::factory()->awaitingConfirmation()->create(['auto_confirm_at' => now()->subMinute()]);
        $normal = $this->withProviderProfile(
            ServiceJob::factory()->awaitingConfirmation()->create(['auto_confirm_at' => now()->subMinute()])
        );

        Log::shouldReceive('error')->once()->withArgs(
            fn (string $message, array $context) => $context['job_id'] === $missingProfile->id
        );

        Artisan::call('app:auto-confirm-jobs');

        $this->assertSame(ServiceJobStatus::AwaitingConfirmation, $missingProfile->fresh()->status);
        $this->assertSame(ServiceJobStatus::Completed, $normal->fresh()->status);
    }

    public function test_an_unexpected_database_exception_is_not_swallowed(): void
    {
        $this->withProviderProfile(
            ServiceJob::factory()->awaitingConfirmation()->create(['auto_confirm_at' => now()->subMinute()])
        );

        $this->app->bind(CompleteJobAction::class, function () {
            return new class extends CompleteJobAction
            {
                public function handle(ServiceJob $job, JobCompletionMode $mode): ServiceJob
                {
                    throw new QueryException('mysql', 'select 1', [], new RuntimeException('connection lost'));
                }
            };
        });

        $this->expectException(QueryException::class);

        Artisan::call('app:auto-confirm-jobs');
    }
}
