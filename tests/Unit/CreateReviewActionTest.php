<?php

namespace Tests\Unit;

use App\Actions\Review\CreateReviewAction;
use App\Exceptions\DuplicateReviewException;
use App\Exceptions\ProviderProfileMissingForReviewException;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\ServiceJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateReviewActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_review_and_updates_the_providers_avg_rating(): void
    {
        $job = ServiceJob::factory()->completed()->create();
        ProviderProfile::factory()->forUser($job->provider)->approved()->create();

        $review = app(CreateReviewAction::class)->handle($job->customer, $job, ['rating' => 4, 'comment' => 'Great work']);

        $this->assertSame($job->id, $review->job_id);
        $this->assertSame($job->customer_id, $review->rater_id);
        $this->assertSame($job->provider_id, $review->ratee_id);
        $this->assertSame('4.00', $job->provider->providerProfile->fresh()->avg_rating);
    }

    public function test_duplicate_review_is_rejected_after_lock(): void
    {
        $job = ServiceJob::factory()->completed()->create();
        ProviderProfile::factory()->forUser($job->provider)->approved()->create();
        $action = app(CreateReviewAction::class);
        $action->handle($job->customer, $job, ['rating' => 5, 'comment' => null]);

        $this->expectException(DuplicateReviewException::class);
        $action->handle($job->customer, $job, ['rating' => 3, 'comment' => null]);
    }

    public function test_missing_provider_profile_throws_and_no_review_is_created(): void
    {
        $job = ServiceJob::factory()->completed()->create();
        // Deliberately no ProviderProfile created for $job->provider.

        try {
            app(CreateReviewAction::class)->handle($job->customer, $job, ['rating' => 5, 'comment' => null]);
            $this->fail('Expected ProviderProfileMissingForReviewException.');
        } catch (ProviderProfileMissingForReviewException $e) {
            $this->assertSame($job->provider_id, $e->providerId);
        }

        $this->assertSame(0, Review::where('job_id', $job->id)->count());
    }
}
