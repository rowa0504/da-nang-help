<?php

namespace Tests\Unit;

use App\Models\Offer;
use App\Models\ServiceJob;
use App\Models\User;
use App\Policies\JobPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobPolicyTest extends TestCase
{
    use RefreshDatabase;

    private JobPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new JobPolicy();
    }

    public function test_view_any_allows_customer_provider_and_admin(): void
    {
        $this->assertTrue($this->policy->viewAny(User::factory()->create()));
        $this->assertTrue($this->policy->viewAny(User::factory()->provider()->create()));
        $this->assertTrue($this->policy->viewAny(User::factory()->admin()->create()));
    }

    public function test_view_allows_customer_provider_and_admin_but_not_unrelated_users(): void
    {
        $job = ServiceJob::factory()->create();
        $unrelated = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->assertTrue($this->policy->view($job->customer, $job));
        $this->assertTrue($this->policy->view($job->provider, $job));
        $this->assertTrue($this->policy->view($admin, $job));
        $this->assertFalse($this->policy->view($unrelated, $job));
    }

    public function test_start_requires_provider_owner_and_assigned_status(): void
    {
        $job = ServiceJob::factory()->create();

        $this->assertTrue($this->policy->start($job->provider, $job));
        $this->assertFalse($this->policy->start($job->customer, $job));
        $this->assertFalse($this->policy->start(User::factory()->provider()->create(), $job));
        $this->assertFalse($this->policy->start(User::factory()->admin()->create(), $job));

        $inProgress = ServiceJob::factory()->inProgress()->create();
        $this->assertFalse($this->policy->start($inProgress->provider, $inProgress));
    }

    public function test_report_completion_requires_provider_owner_and_in_progress_status(): void
    {
        $job = ServiceJob::factory()->inProgress()->create();

        $this->assertTrue($this->policy->reportCompletion($job->provider, $job));
        $this->assertFalse($this->policy->reportCompletion($job->customer, $job));
        $this->assertFalse($this->policy->reportCompletion(User::factory()->admin()->create(), $job));

        $assigned = ServiceJob::factory()->create();
        $this->assertFalse($this->policy->reportCompletion($assigned->provider, $assigned));
    }

    public function test_confirm_completion_requires_customer_owner_and_awaiting_confirmation_status(): void
    {
        $job = ServiceJob::factory()->awaitingConfirmation()->create();

        $this->assertTrue($this->policy->confirmCompletion($job->customer, $job));
        $this->assertFalse($this->policy->confirmCompletion($job->provider, $job));
        $this->assertFalse($this->policy->confirmCompletion(User::factory()->admin()->create(), $job));

        $inProgress = ServiceJob::factory()->inProgress()->create();
        $this->assertFalse($this->policy->confirmCompletion($inProgress->customer, $inProgress));
    }

    public function test_cancel_allows_customer_or_provider_only_from_assigned_or_in_progress(): void
    {
        $assigned = ServiceJob::factory()->create();
        $this->assertTrue($this->policy->cancel($assigned->customer, $assigned));
        $this->assertTrue($this->policy->cancel($assigned->provider, $assigned));
        $this->assertFalse($this->policy->cancel(User::factory()->admin()->create(), $assigned));
        $this->assertFalse($this->policy->cancel(User::factory()->create(), $assigned));

        $inProgress = ServiceJob::factory()->inProgress()->create();
        $this->assertTrue($this->policy->cancel($inProgress->customer, $inProgress));

        $awaitingConfirmation = ServiceJob::factory()->awaitingConfirmation()->create();
        $this->assertFalse($this->policy->cancel($awaitingConfirmation->customer, $awaitingConfirmation));
        $this->assertFalse($this->policy->cancel($awaitingConfirmation->provider, $awaitingConfirmation));

        $completed = ServiceJob::factory()->completed()->create();
        $this->assertFalse($this->policy->cancel($completed->customer, $completed));

        $cancelled = ServiceJob::factory()->cancelled()->create();
        $this->assertFalse($this->policy->cancel($cancelled->provider, $cancelled));
    }

    public function test_admin_can_never_perform_state_changing_actions(): void
    {
        $admin = User::factory()->admin()->create();

        $assigned = ServiceJob::factory()->create();
        $inProgress = ServiceJob::factory()->inProgress()->create();
        $awaitingConfirmation = ServiceJob::factory()->awaitingConfirmation()->create();

        $this->assertFalse($this->policy->start($admin, $assigned));
        $this->assertFalse($this->policy->reportCompletion($admin, $inProgress));
        $this->assertFalse($this->policy->confirmCompletion($admin, $awaitingConfirmation));
        $this->assertFalse($this->policy->cancel($admin, $assigned));
    }
}
