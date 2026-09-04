<?php

namespace Tests\Feature\Provider;

use App\Models\Area;
use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        );
    }

    public function test_unapproved_provider_is_forbidden(): void
    {
        $provider = User::factory()->provider()->create();
        ProviderProfile::factory()->forUser($provider)->create(); // pending

        $this->actingAs($provider)->get('/provider/requests')->assertForbidden();
    }

    public function test_category_only_match_is_not_shown(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $otherArea = Area::factory()->create();
        ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $otherArea->id]);
        $provider = $this->approvedProviderFor($category, $area);

        $this->actingAs($provider)->get('/provider/requests')->assertInertia(
            fn (Assert $page) => $page->where('requests.meta.total', 0)
        );
    }

    public function test_area_only_match_is_not_shown(): void
    {
        $category = Category::factory()->create();
        $otherCategory = Category::factory()->create();
        $area = Area::factory()->create();
        ServiceRequest::factory()->create(['category_id' => $otherCategory->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);

        $this->actingAs($provider)->get('/provider/requests')->assertInertia(
            fn (Assert $page) => $page->where('requests.meta.total', 0)
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
}
