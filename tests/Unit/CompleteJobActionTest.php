<?php

namespace Tests\Unit;

use App\Actions\Job\CompleteJobAction;
use App\Enums\JobCompletionMode;
use App\Enums\ServiceJobStatus;
use App\Exceptions\InvalidJobTransitionException;
use App\Exceptions\ProviderProfileMissingForJobException;
use App\Models\ProviderProfile;
use App\Models\ServiceJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompleteJobActionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * OfferFactory (and therefore ServiceJobFactory) only creates a bare
     * Provider User, not a ProviderProfile — in real usage a Job's provider
     * always has an approved profile (CreateOfferAction requires one), but
     * these isolated Unit tests must set that up explicitly wherever
     * CompleteJobAction's completed_jobs_count increment is expected to
     * succeed.
     */
    private function withProviderProfile(ServiceJob $job): ServiceJob
    {
        ProviderProfile::factory()->forUser($job->provider)->approved()->create();

        return $job;
    }

    public function test_manual_mode_sets_completed_and_customer_confirmed_at(): void
    {
        $job = $this->withProviderProfile(ServiceJob::factory()->awaitingConfirmation()->create());

        $completed = app(CompleteJobAction::class)->handle($job, JobCompletionMode::Manual);

        $this->assertSame(ServiceJobStatus::Completed, $completed->status);
        $this->assertNotNull($completed->completed_at);
        $this->assertNotNull($completed->customer_confirmed_at);
    }

    public function test_auto_mode_sets_completed_without_customer_confirmed_at(): void
    {
        $job = $this->withProviderProfile(
            ServiceJob::factory()->awaitingConfirmation()->create(['auto_confirm_at' => now()->subMinute()])
        );

        $completed = app(CompleteJobAction::class)->handle($job, JobCompletionMode::Auto);

        $this->assertSame(ServiceJobStatus::Completed, $completed->status);
        $this->assertNotNull($completed->completed_at);
        $this->assertNull($completed->customer_confirmed_at);
    }

    public function test_completed_jobs_count_increments_by_one(): void
    {
        $job = $this->withProviderProfile(ServiceJob::factory()->awaitingConfirmation()->create());
        $countBefore = $job->provider->providerProfile->completed_jobs_count;

        app(CompleteJobAction::class)->handle($job, JobCompletionMode::Manual);

        $this->assertSame($countBefore + 1, $job->provider->providerProfile->fresh()->completed_jobs_count);
    }

    public function test_calling_handle_twice_does_not_double_complete_or_double_count(): void
    {
        $job = $this->withProviderProfile(ServiceJob::factory()->awaitingConfirmation()->create());

        app(CompleteJobAction::class)->handle($job, JobCompletionMode::Manual);

        try {
            app(CompleteJobAction::class)->handle($job->fresh(), JobCompletionMode::Manual);
            $this->fail('Expected InvalidJobTransitionException on the second call.');
        } catch (InvalidJobTransitionException) {
            // expected
        }

        $this->assertSame(1, $job->provider->providerProfile->fresh()->completed_jobs_count);
    }

    public function test_auto_mode_rejects_a_job_whose_auto_confirm_at_is_still_in_the_future(): void
    {
        $job = $this->withProviderProfile(
            ServiceJob::factory()->awaitingConfirmation()->create(['auto_confirm_at' => now()->addDay()])
        );

        try {
            app(CompleteJobAction::class)->handle($job, JobCompletionMode::Auto);
            $this->fail('Expected InvalidJobTransitionException.');
        } catch (InvalidJobTransitionException) {
            // expected
        }

        $this->assertSame(ServiceJobStatus::AwaitingConfirmation, $job->fresh()->status);
    }

    public function test_missing_provider_profile_throws_and_rolls_back_the_job_status(): void
    {
        // No ProviderProfile is created for this provider at all.
        $job = ServiceJob::factory()->awaitingConfirmation()->create();

        try {
            app(CompleteJobAction::class)->handle($job, JobCompletionMode::Manual);
            $this->fail('Expected ProviderProfileMissingForJobException.');
        } catch (ProviderProfileMissingForJobException $e) {
            $this->assertSame($job->id, $e->jobId);
            $this->assertSame($job->provider_id, $e->providerId);
        }

        $fresh = $job->fresh();
        $this->assertSame(ServiceJobStatus::AwaitingConfirmation, $fresh->status);
        $this->assertNull($fresh->completed_at);
    }

    public function test_non_awaiting_confirmation_job_cannot_be_completed(): void
    {
        $job = $this->withProviderProfile(ServiceJob::factory()->inProgress()->create());

        $this->expectException(InvalidJobTransitionException::class);

        app(CompleteJobAction::class)->handle($job, JobCompletionMode::Manual);
    }
}
