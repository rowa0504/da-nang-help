<?php

namespace Tests\Unit;

use App\Actions\Admin\HideReviewAction;
use App\Exceptions\InvalidReviewTransitionException;
use App\Exceptions\ProviderProfileMissingForReviewException;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HideReviewActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_hides_the_review_and_recalculates_the_avg_rating(): void
    {
        $job = ServiceJob::factory()->completed()->create();
        ProviderProfile::factory()->forUser($job->provider)->approved()->create();
        $review = Review::factory()->forJob($job)->create(['rating' => 5]);
        $admin = User::factory()->admin()->create();

        $hidden = app(HideReviewAction::class)->handle($admin, $review);

        $this->assertTrue($hidden->is_hidden);
        $this->assertNotNull($hidden->hidden_at);
        $this->assertSame($admin->id, $hidden->hidden_by);
        $this->assertSame('0.00', $job->provider->providerProfile->fresh()->avg_rating);
    }

    public function test_already_hidden_review_cannot_be_hidden_again(): void
    {
        $job = ServiceJob::factory()->completed()->create();
        ProviderProfile::factory()->forUser($job->provider)->approved()->create();
        $review = Review::factory()->forJob($job)->hidden()->create();
        $admin = User::factory()->admin()->create();

        $this->expectException(InvalidReviewTransitionException::class);
        app(HideReviewAction::class)->handle($admin, $review);
    }

    public function test_non_admin_cannot_hide_a_review(): void
    {
        $job = ServiceJob::factory()->completed()->create();
        ProviderProfile::factory()->forUser($job->provider)->approved()->create();
        $review = Review::factory()->forJob($job)->create();

        $this->expectException(InvalidReviewTransitionException::class);
        app(HideReviewAction::class)->handle($job->customer, $review);

        $this->assertFalse($review->fresh()->is_hidden);
    }

    public function test_missing_provider_profile_throws_and_the_review_stays_visible(): void
    {
        $job = ServiceJob::factory()->completed()->create();
        // Deliberately no ProviderProfile created for $job->provider.
        $review = Review::factory()->forJob($job)->create();
        $admin = User::factory()->admin()->create();

        try {
            app(HideReviewAction::class)->handle($admin, $review);
            $this->fail('Expected ProviderProfileMissingForReviewException.');
        } catch (ProviderProfileMissingForReviewException $e) {
            $this->assertSame($job->provider_id, $e->providerId);
        }

        $this->assertFalse($review->fresh()->is_hidden);
    }
}
