<?php

namespace Tests\Unit;

use App\Models\Offer;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_job_make_does_not_create_extra_related_records(): void
    {
        $job = ServiceJob::factory()->completed()->create();
        $countsBefore = [
            ServiceJob::count(), Offer::count(), User::count(), ProviderProfile::count(),
        ];

        Review::factory()->forJob($job)->make();

        $this->assertSame($countsBefore, [
            ServiceJob::count(), Offer::count(), User::count(), ProviderProfile::count(),
        ]);
    }

    public function test_for_job_create_makes_exactly_one_review_consistent_with_the_job(): void
    {
        $job = ServiceJob::factory()->completed()->create();
        $countBefore = ServiceJob::count();

        $review = Review::factory()->forJob($job)->create();

        $this->assertSame($countBefore, ServiceJob::count());
        $this->assertSame(1, Review::where('job_id', $job->id)->count());
        $this->assertSame($job->customer_id, $review->rater_id);
        $this->assertSame($job->provider_id, $review->ratee_id);
    }

    public function test_bare_make_does_not_persist_the_review_itself(): void
    {
        // Note: this only asserts that the Review row itself is not saved.
        // A bare (non-forJob) call still resolves 'job_id' via a nested
        // ServiceJob factory relation, which Laravel persists regardless of
        // make() vs create() (true of every FK-bearing factory in this
        // codebase) — that side effect is explicitly out of scope here.
        $countBefore = Review::count();

        Review::factory()->make();

        $this->assertSame($countBefore, Review::count());
    }
}
