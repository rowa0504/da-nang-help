<?php

namespace Tests\Feature\Provider;

use App\Enums\ProviderVerificationStatus;
use App\Models\Area;
use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RequestFeedTest extends TestCase
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

    public function test_matching_approved_provider_sees_the_request_in_the_feed(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);

        $this->actingAs($provider)->get('/provider/requests')->assertOk()->assertInertia(
            fn (Assert $page) => $page->where('requests.data.0.id', $serviceRequest->id)
                ->where('requests.data.0.match_level', 'full')
        );
    }

    public function test_unapproved_provider_is_forbidden(): void
    {
        $provider = User::factory()->provider()->create();
        ProviderProfile::factory()->forUser($provider)->create(); // pending

        $this->actingAs($provider)->get('/provider/requests')->assertForbidden();
    }

    public function test_suspended_provider_is_forbidden(): void
    {
        $provider = User::factory()->provider()->create();
        $profile = ProviderProfile::factory()->forUser($provider)->approved()->create();
        $profile->verification_status = ProviderVerificationStatus::Suspended;
        $profile->suspended_at = now();
        $profile->save();

        $this->actingAs($provider)->get('/provider/requests')->assertForbidden();
    }

    /**
     * Category/area match is now a ranking/display signal, not an access
     * gate: a category-only match must still appear by default (with
     * match_level=partial), not be excluded the way it was before this
     * phase.
     */
    public function test_category_only_match_is_shown_by_default_as_partial(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $otherArea = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $otherArea->id]);
        $provider = $this->approvedProviderFor($category, $area);

        $this->actingAs($provider)->get('/provider/requests')->assertInertia(
            fn (Assert $page) => $page->where('requests.meta.total', 1)
                ->where('requests.data.0.id', $serviceRequest->id)
                ->where('requests.data.0.match_level', 'partial')
        );
    }

    public function test_area_only_match_is_shown_by_default_as_partial(): void
    {
        $category = Category::factory()->create();
        $otherCategory = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $otherCategory->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);

        $this->actingAs($provider)->get('/provider/requests')->assertInertia(
            fn (Assert $page) => $page->where('requests.meta.total', 1)
                ->where('requests.data.0.id', $serviceRequest->id)
                ->where('requests.data.0.match_level', 'partial')
        );
    }

    public function test_fully_mismatched_request_is_shown_by_default_as_none(): void
    {
        $category = Category::factory()->create();
        $otherCategory = Category::factory()->create();
        $area = Area::factory()->create();
        $otherArea = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $otherCategory->id, 'area_id' => $otherArea->id]);
        $provider = $this->approvedProviderFor($category, $area);

        $this->actingAs($provider)->get('/provider/requests')->assertInertia(
            fn (Assert $page) => $page->where('requests.meta.total', 1)
                ->where('requests.data.0.id', $serviceRequest->id)
                ->where('requests.data.0.match_level', 'none')
        );
    }

    /**
     * The single, decisive test for the ranking rule: full → partial →
     * none, ties within a tier broken by created_at desc. Also confirms
     * the SQL-driven ORDER BY (match_rank) and the PHP-driven match_level
     * field (computed independently, see ServiceRequestResource) agree on
     * every one of these same four boundary conditions at once.
     */
    public function test_ordering_is_full_then_partial_then_none_and_match_level_agrees(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $otherCategory = Category::factory()->create();
        $otherArea = Area::factory()->create();
        $provider = $this->approvedProviderFor($category, $area);

        $none = ServiceRequest::factory()->create([
            'category_id' => $otherCategory->id, 'area_id' => $otherArea->id, 'created_at' => now()->subMinutes(4),
        ]);
        $partialCategory = ServiceRequest::factory()->create([
            'category_id' => $category->id, 'area_id' => $otherArea->id, 'created_at' => now()->subMinutes(3),
        ]);
        $partialArea = ServiceRequest::factory()->create([
            'category_id' => $otherCategory->id, 'area_id' => $area->id, 'created_at' => now()->subMinutes(2),
        ]);
        $full = ServiceRequest::factory()->create([
            'category_id' => $category->id, 'area_id' => $area->id, 'created_at' => now()->subMinute(),
        ]);

        $this->actingAs($provider)->get('/provider/requests')->assertInertia(
            fn (Assert $page) => $page
                ->where('requests.data.0.id', $full->id)->where('requests.data.0.match_level', 'full')
                // partialArea is newer than partialCategory, so it sorts first within the tier.
                ->where('requests.data.1.id', $partialArea->id)->where('requests.data.1.match_level', 'partial')
                ->where('requests.data.2.id', $partialCategory->id)->where('requests.data.2.match_level', 'partial')
                ->where('requests.data.3.id', $none->id)->where('requests.data.3.match_level', 'none')
        );
    }

    public function test_same_tier_and_timestamp_ties_are_broken_by_id_descending(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $provider = $this->approvedProviderFor($category, $area);

        $now = now();
        $first = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id, 'created_at' => $now]);
        $second = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id, 'created_at' => $now]);

        $this->actingAs($provider)->get('/provider/requests')->assertInertia(
            fn (Assert $page) => $page->where('requests.data.0.id', $second->id)->where('requests.data.1.id', $first->id)
        );
    }

    public function test_recommended_filter_excludes_none_but_keeps_full_and_partial(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $otherCategory = Category::factory()->create();
        $otherArea = Area::factory()->create();
        $provider = $this->approvedProviderFor($category, $area);

        ServiceRequest::factory()->create(['category_id' => $otherCategory->id, 'area_id' => $otherArea->id]); // none — excluded
        $partial = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $otherArea->id]);
        $full = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);

        $this->actingAs($provider)->get('/provider/requests?recommended=1')->assertInertia(
            fn (Assert $page) => $page->where('requests.meta.total', 2)
                ->where('requests.data.0.id', $full->id)
                ->where('requests.data.1.id', $partial->id)
        );
    }

    public function test_category_id_filter_narrows_to_that_category_only(): void
    {
        $category = Category::factory()->create();
        $otherCategory = Category::factory()->create();
        $area = Area::factory()->create();
        $provider = $this->approvedProviderFor($category, $area);

        $matching = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        ServiceRequest::factory()->create(['category_id' => $otherCategory->id, 'area_id' => $area->id]);

        $this->actingAs($provider)->get("/provider/requests?category_id={$category->id}")->assertInertia(
            fn (Assert $page) => $page->where('requests.meta.total', 1)->where('requests.data.0.id', $matching->id)
        );
    }

    public function test_area_id_filter_narrows_to_that_area_only(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $otherArea = Area::factory()->create();
        $provider = $this->approvedProviderFor($category, $area);

        $matching = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $otherArea->id]);

        $this->actingAs($provider)->get("/provider/requests?area_id={$area->id}")->assertInertia(
            fn (Assert $page) => $page->where('requests.meta.total', 1)->where('requests.data.0.id', $matching->id)
        );
    }

    public function test_invalid_category_id_filter_is_rejected(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $provider = $this->approvedProviderFor($category, $area);

        $this->actingAs($provider)->get('/provider/requests?category_id=999999')->assertInvalid(['category_id']);
    }

    public function test_invalid_area_id_filter_is_rejected(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $provider = $this->approvedProviderFor($category, $area);

        $this->actingAs($provider)->get('/provider/requests?area_id=999999')->assertInvalid(['area_id']);
    }

    public function test_pagination_links_preserve_the_active_filter(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $provider = $this->approvedProviderFor($category, $area);

        ServiceRequest::factory()->count(25)->create(['category_id' => $category->id, 'area_id' => $area->id]);

        $this->actingAs($provider)->get('/provider/requests?recommended=1')->assertInertia(
            fn (Assert $page) => $page->where(
                'requests.links.next',
                fn ($url) => $url !== null && str_contains($url, 'recommended=1')
            )
        );
    }

    public function test_cancelled_request_is_excluded_from_the_feed(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        ServiceRequest::factory()->cancelled()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);

        $this->actingAs($provider)->get('/provider/requests')->assertInertia(
            fn (Assert $page) => $page->where('requests.meta.total', 0)
        );
    }

    public function test_hidden_request_is_excluded_from_the_feed(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        ServiceRequest::factory()->hidden()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);

        $this->actingAs($provider)->get('/provider/requests')->assertInertia(
            fn (Assert $page) => $page->where('requests.meta.total', 0)
        );
    }

    public function test_customer_and_admin_are_forbidden_from_the_feed(): void
    {
        $customer = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($customer)->get('/provider/requests')->assertForbidden();
        $this->actingAs($admin)->get('/provider/requests')->assertForbidden();
    }

    public function test_feed_does_not_trigger_n_plus_one_as_request_count_grows(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $provider = $this->approvedProviderFor($category, $area);

        ServiceRequest::factory()->count(3)->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $this->actingAs($provider)->get('/provider/requests')->assertOk();

        DB::enableQueryLog();
        $this->actingAs($provider)->get('/provider/requests')->assertOk();
        $queryCountForThree = count(DB::getQueryLog());
        DB::flushQueryLog();

        ServiceRequest::factory()->count(9)->create(['category_id' => $category->id, 'area_id' => $area->id]);
        DB::flushQueryLog();

        $this->actingAs($provider)->get('/provider/requests')->assertOk();
        $queryCountForTwelve = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($queryCountForThree, $queryCountForTwelve);
    }
}
