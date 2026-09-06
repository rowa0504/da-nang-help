<?php

namespace Tests\Unit;

use App\Models\Review;
use App\Models\ServiceJob;
use App\Models\User;
use App\Policies\ReviewPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewPolicyTest extends TestCase
{
    use RefreshDatabase;

    private ReviewPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ReviewPolicy();
    }

    public function test_view_any_allows_admin_only(): void
    {
        $this->assertTrue($this->policy->viewAny(User::factory()->admin()->create()));
        $this->assertFalse($this->policy->viewAny(User::factory()->create()));
        $this->assertFalse($this->policy->viewAny(User::factory()->provider()->create()));
    }

    public function test_create_allows_the_jobs_own_customer_on_a_completed_unreviewed_job(): void
    {
        $job = ServiceJob::factory()->completed()->create();

        $this->assertTrue($this->policy->create($job->customer, $job));
    }

    public function test_create_denies_the_provider_and_unrelated_users(): void
    {
        $job = ServiceJob::factory()->completed()->create();

        $this->assertFalse($this->policy->create($job->provider, $job));
        $this->assertFalse($this->policy->create(User::factory()->create(), $job));
        $this->assertFalse($this->policy->create(User::factory()->admin()->create(), $job));
    }

    public function test_create_denies_when_the_job_is_not_completed(): void
    {
        $assigned = ServiceJob::factory()->create();
        $inProgress = ServiceJob::factory()->inProgress()->create();
        $awaitingConfirmation = ServiceJob::factory()->awaitingConfirmation()->create();
        $cancelled = ServiceJob::factory()->cancelled()->create();

        $this->assertFalse($this->policy->create($assigned->customer, $assigned));
        $this->assertFalse($this->policy->create($inProgress->customer, $inProgress));
        $this->assertFalse($this->policy->create($awaitingConfirmation->customer, $awaitingConfirmation));
        $this->assertFalse($this->policy->create($cancelled->customer, $cancelled));
    }

    public function test_create_denies_when_a_review_already_exists(): void
    {
        $job = ServiceJob::factory()->completed()->create();
        Review::factory()->forJob($job)->create();

        $this->assertFalse($this->policy->create($job->customer, $job));
    }

    public function test_hide_allows_admin_on_a_visible_review(): void
    {
        $job = ServiceJob::factory()->completed()->create();
        $review = Review::factory()->forJob($job)->create();

        $this->assertTrue($this->policy->hide(User::factory()->admin()->create(), $review));
    }

    public function test_hide_denies_non_admin_and_already_hidden_reviews(): void
    {
        $job = ServiceJob::factory()->completed()->create();
        $visible = Review::factory()->forJob($job)->create();
        $hiddenJob = ServiceJob::factory()->completed()->create();
        $hidden = Review::factory()->forJob($hiddenJob)->hidden()->create();

        $this->assertFalse($this->policy->hide($job->customer, $visible));
        $this->assertFalse($this->policy->hide($job->provider, $visible));
        $this->assertFalse($this->policy->hide(User::factory()->admin()->create(), $hidden));
    }
}
