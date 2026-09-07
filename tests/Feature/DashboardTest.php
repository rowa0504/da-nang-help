<?php

namespace Tests\Feature;

use App\Enums\ProviderVerificationStatus;
use App\Enums\ServiceJobStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\Area;
use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\ServiceJob;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_stats_match_the_database(): void
    {
        $admin = User::factory()->admin()->create();

        ServiceRequest::factory()->count(2)->create(); // open
        ServiceRequest::factory()->cancelled()->create();
        $assigned = ServiceRequest::factory()->create();
        $assigned->status = ServiceRequestStatus::Assigned;
        $assigned->save();

        ServiceJob::factory()->create(); // pulls in its own Offer/ServiceRequest chain
        ServiceJob::factory()->completed()->create();

        ProviderProfile::factory()->create(); // pending
        ProviderProfile::factory()->approved()->create();

        Category::factory()->create(['is_active' => true]);
        Category::factory()->create(['is_active' => false]);

        Area::factory()->create(['is_active' => true]);

        $job = ServiceJob::factory()->completed()->create();
        Review::factory()->forJob($job)->hidden()->create();

        // Expected values are read straight from the DB (rather than
        // hand-counted) because ServiceJobFactory's default state pulls in
        // its own throwaway Offer + ServiceRequest chain — hand-counting
        // would silently drift out of sync with that factory's internals.
        $expectedTotalRequests = ServiceRequest::count();
        $expectedOpenRequests = ServiceRequest::where('status', ServiceRequestStatus::Open->value)->count();
        $expectedAssignedRequests = ServiceRequest::where('status', ServiceRequestStatus::Assigned->value)->count();
        $expectedConversionRate = round($expectedAssignedRequests / $expectedTotalRequests * 100, 1);
        $expectedTotalJobs = ServiceJob::count();
        $expectedCompletedJobs = ServiceJob::where('status', ServiceJobStatus::Completed->value)->count();
        $expectedPendingProviders = ProviderProfile::where('verification_status', ProviderVerificationStatus::Pending->value)->count();
        $expectedActiveCategories = Category::where('is_active', true)->count();
        $expectedActiveAreas = Area::where('is_active', true)->count();
        $expectedHiddenReviews = Review::where('is_hidden', true)->count();

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('adminStats.total_requests', $expectedTotalRequests)
            ->where('adminStats.open_requests', $expectedOpenRequests)
            ->where('adminStats.conversion_rate', $expectedConversionRate)
            ->where('adminStats.total_jobs', $expectedTotalJobs)
            ->where('adminStats.completed_jobs', $expectedCompletedJobs)
            ->where('adminStats.pending_providers', $expectedPendingProviders)
            ->where('adminStats.active_categories', $expectedActiveCategories)
            ->where('adminStats.active_areas', $expectedActiveAreas)
            ->where('adminStats.hidden_reviews', $expectedHiddenReviews)
        );

        $this->assertSame(3, $expectedTotalJobs, 'sanity check: two standalone jobs + the reviewed job');
        $this->assertSame(2, $expectedCompletedJobs);
        $this->assertGreaterThanOrEqual(1, $expectedPendingProviders);
        $this->assertGreaterThanOrEqual(1, $expectedHiddenReviews);
    }

    public function test_customer_and_provider_do_not_receive_admin_stats(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->provider()->create();

        foreach ([$customer, $provider] as $user) {
            $this->actingAs($user)->get('/dashboard')->assertInertia(
                fn (Assert $page) => $page->missing('adminStats')
            );
        }
    }

    public function test_admin_dashboard_with_no_data_has_a_zero_conversion_rate(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/dashboard')->assertInertia(
            fn (Assert $page) => $page->where('adminStats.total_requests', 0)->where('adminStats.conversion_rate', 0)
        );
    }
}
