<?php

namespace Tests\Feature\Reviews;

use App\Actions\Admin\HideReviewAction;
use App\Actions\Job\CancelJobAction;
use App\Actions\Job\ReportJobCompletionAction;
use App\Actions\Job\StartJobAction;
use App\Actions\Review\CreateReviewAction;
use App\Enums\ServiceRequestStatus;
use App\Exceptions\InvalidReviewTransitionException;
use App\Models\Area;
use App\Models\Category;
use App\Models\Offer;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\ServiceJob;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private function approvedProviderFor(Category $category, Area $area): User
    {
        $provider = User::factory()->provider()->create();
        $profile = ProviderProfile::factory()->forUser($provider)->approved()->create();
        $profile->categories()->attach($category);
        $profile->areas()->attach($area);

        return $provider;
    }

    private function completedJob(): ServiceJob
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);
        $offer = Offer::factory()->forServiceRequest($serviceRequest)->forProvider($provider)->accepted()->create();
        $job = ServiceJob::factory()->forOffer($offer)->completed()->create();

        $serviceRequest->status = ServiceRequestStatus::Assigned;
        $serviceRequest->save();

        return $job;
    }

    public function test_customer_can_review_a_completed_job_and_avg_rating_is_reflected(): void
    {
        $job = $this->completedJob();

        $response = $this->actingAs($job->customer)->post("/jobs/{$job->id}/review", [
            'rating' => 4,
            'comment' => 'Great work, on time.',
        ]);

        $response->assertRedirect(route('jobs.show', $job));
        $review = Review::where('job_id', $job->id)->firstOrFail();
        $this->assertSame(4, $review->rating);
        $this->assertSame('Great work, on time.', $review->comment);
        $this->assertSame('4.00', $job->provider->providerProfile->fresh()->avg_rating);
    }

    public function test_non_completed_jobs_cannot_be_reviewed(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);
        $offer = Offer::factory()->forServiceRequest($serviceRequest)->forProvider($provider)->accepted()->create();
        $assigned = ServiceJob::factory()->forOffer($offer)->create();

        $this->actingAs($assigned->customer)->post("/jobs/{$assigned->id}/review", ['rating' => 5])->assertForbidden();

        app(StartJobAction::class)->handle($assigned->provider, $assigned);
        $this->actingAs($assigned->customer)->post("/jobs/{$assigned->id}/review", ['rating' => 5])->assertForbidden();

        app(ReportJobCompletionAction::class)->handle($assigned->provider, $assigned->fresh());
        $this->actingAs($assigned->customer)->post("/jobs/{$assigned->id}/review", ['rating' => 5])->assertForbidden();

        $cancelledJob = ServiceJob::factory()->create();
        $this->actingAs($cancelledJob->customer)
            ->post("/jobs/{$cancelledJob->id}/review", ['rating' => 5])
            ->assertForbidden();
        app(CancelJobAction::class)->handle($cancelledJob->customer, $cancelledJob);
        $this->actingAs($cancelledJob->customer)
            ->post("/jobs/{$cancelledJob->id}/review", ['rating' => 5])
            ->assertForbidden();
    }

    public function test_provider_unrelated_customer_and_admin_cannot_review(): void
    {
        $job = $this->completedJob();
        $unrelatedCustomer = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($job->provider)->post("/jobs/{$job->id}/review", ['rating' => 5])->assertForbidden();
        $this->actingAs($unrelatedCustomer)->post("/jobs/{$job->id}/review", ['rating' => 5])->assertForbidden();
        $this->actingAs($admin)->post("/jobs/{$job->id}/review", ['rating' => 5])->assertForbidden();
    }

    public function test_duplicate_review_is_rejected_by_policy_and_by_direct_action_call(): void
    {
        $job = $this->completedJob();

        $this->actingAs($job->customer)->post("/jobs/{$job->id}/review", ['rating' => 5])->assertRedirect();
        $this->actingAs($job->customer)->post("/jobs/{$job->id}/review", ['rating' => 3])->assertForbidden();

        $this->expectException(InvalidReviewTransitionException::class);
        app(CreateReviewAction::class)->handle($job->customer, $job->fresh(), ['rating' => 3, 'comment' => null]);
    }

    public function test_the_job_id_unique_constraint_limits_a_job_to_a_single_review(): void
    {
        $job = $this->completedJob();
        Review::factory()->forJob($job)->create();

        $this->expectException(QueryException::class);
        Review::factory()->forJob($job)->create();
    }

    public function test_rating_out_of_range_is_rejected_by_the_db_check_constraint_via_direct_action_call(): void
    {
        $jobLow = $this->completedJob();
        try {
            app(CreateReviewAction::class)->handle($jobLow->customer, $jobLow, ['rating' => 0, 'comment' => null]);
            $this->fail('Expected a QueryException for rating=0.');
        } catch (QueryException) {
            // expected
        }

        $jobHigh = $this->completedJob();
        try {
            app(CreateReviewAction::class)->handle($jobHigh->customer, $jobHigh, ['rating' => 6, 'comment' => null]);
            $this->fail('Expected a QueryException for rating=6.');
        } catch (QueryException) {
            // expected
        }

        $this->assertSame(0, Review::count());
    }

    public function test_average_rating_is_correct_across_multiple_reviews(): void
    {
        $provider = User::factory()->provider()->create();
        ProviderProfile::factory()->forUser($provider)->approved()->create();

        $jobA = ServiceJob::factory()->completed()->create(['provider_id' => $provider->id]);
        $jobB = ServiceJob::factory()->completed()->create(['provider_id' => $provider->id]);

        app(CreateReviewAction::class)->handle($jobA->customer, $jobA, ['rating' => 5, 'comment' => null]);
        app(CreateReviewAction::class)->handle($jobB->customer, $jobB, ['rating' => 3, 'comment' => null]);

        $this->assertSame('4.00', ProviderProfile::where('user_id', $provider->id)->firstOrFail()->avg_rating);
    }

    public function test_admin_hides_a_review_and_recalculates_average_without_touching_completed_jobs_count(): void
    {
        $job = $this->completedJob();
        $review = Review::factory()->forJob($job)->create(['rating' => 5]);
        $countBefore = $job->provider->providerProfile->fresh()->completed_jobs_count;
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->patch("/admin/reviews/{$review->id}/hide");

        $response->assertRedirect(route('admin.reviews.index'));
        $fresh = $review->fresh();
        $this->assertTrue($fresh->is_hidden);
        $this->assertNotNull($fresh->hidden_at);
        $this->assertSame($admin->id, $fresh->hidden_by);
        $this->assertSame('0.00', $job->provider->providerProfile->fresh()->avg_rating);
        $this->assertSame($countBefore, $job->provider->providerProfile->fresh()->completed_jobs_count);
    }

    public function test_admin_hide_review_flash_message_is_localized(): void
    {
        $expected = [
            'en' => 'Review hidden.',
            'ja' => 'レビューを非表示にしました。',
            'vi' => 'Đã ẩn đánh giá.',
        ];

        foreach ($expected as $locale => $message) {
            $admin = User::factory()->admin()->create(['locale' => $locale]);
            $job = $this->completedJob();
            $review = Review::factory()->forJob($job)->create();

            $this->actingAs($admin)
                ->patch("/admin/reviews/{$review->id}/hide")
                ->assertSessionHas('status', $message);
        }
    }

    public function test_already_hidden_review_cannot_be_hidden_again(): void
    {
        $job = $this->completedJob();
        $review = Review::factory()->forJob($job)->hidden()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch("/admin/reviews/{$review->id}/hide")->assertForbidden();
    }

    public function test_non_admin_cannot_hide_a_review(): void
    {
        $job = $this->completedJob();
        $review = Review::factory()->forJob($job)->create();

        $this->actingAs($job->customer)->patch("/admin/reviews/{$review->id}/hide")->assertForbidden();
    }

    public function test_hidden_review_visibility_excludes_only_the_provider(): void
    {
        $job = $this->completedJob();
        $review = Review::factory()->forJob($job)->create();
        $admin = User::factory()->admin()->create();
        app(HideReviewAction::class)->handle($admin, $review);

        foreach ([$job->customer, $admin] as $viewer) {
            $this->actingAs($viewer)->get("/jobs/{$job->id}")->assertInertia(
                fn (Assert $page) => $page->where('job.review.id', $review->id)->where('job.review.is_hidden', true)
            );
        }

        $this->actingAs($job->provider)->get("/jobs/{$job->id}")->assertInertia(
            fn (Assert $page) => $page->where('job.review', null)
        );
    }

    public function test_admin_reviews_index_is_admin_only_paginated_and_avoids_n_plus_one(): void
    {
        $job = $this->completedJob();
        Review::factory()->forJob($job)->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($job->customer)->get('/admin/reviews')->assertForbidden();
        $this->actingAs($job->provider)->get('/admin/reviews')->assertForbidden();

        DB::enableQueryLog();
        $this->actingAs($admin)->get('/admin/reviews')->assertOk()->assertInertia(
            fn (Assert $page) => $page->has('reviews.data', 1)
        );
        $queryCountForOne = count(DB::getQueryLog());
        DB::flushQueryLog();

        for ($i = 0; $i < 4; $i++) {
            Review::factory()->forJob($this->completedJob())->create();
        }
        DB::flushQueryLog();

        $this->actingAs($admin)->get('/admin/reviews')->assertOk()->assertInertia(
            fn (Assert $page) => $page->has('reviews.data', 5)
        );
        $queryCountForFive = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($queryCountForOne, $queryCountForFive);
    }

    public function test_provider_profile_page_reflects_avg_rating_and_completed_jobs_count(): void
    {
        $job = $this->completedJob();
        app(CreateReviewAction::class)->handle($job->customer, $job, ['rating' => 5, 'comment' => null]);

        $this->actingAs($job->provider)->get('/provider/profile')->assertInertia(
            fn (Assert $page) => $page
                ->where('profile.avg_rating', '5.00')
                ->where('profile.completed_jobs_count', $job->provider->providerProfile->fresh()->completed_jobs_count)
        );
    }
}
