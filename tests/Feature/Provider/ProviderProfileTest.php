<?php

namespace Tests\Feature\Provider;

use App\Actions\Provider\SubmitProviderProfileAction;
use App\Enums\ProviderVerificationStatus;
use App\Exceptions\InvalidProviderVerificationTransitionException;
use App\Models\Area;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProviderProfileTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();

        return array_merge([
            'business_name' => 'Da Nang Fixit Co.',
            'bio' => 'We fix things.',
            'category_ids' => [$category->id],
            'area_ids' => [$area->id],
        ], $overrides);
    }

    public function test_provider_can_create_a_profile(): void
    {
        $provider = User::factory()->provider()->create();
        $category = Category::factory()->create();
        $area = Area::factory()->create();

        $response = $this->actingAs($provider)->post('/provider/profile', [
            'business_name' => 'Da Nang Fixit Co.',
            'bio' => 'We fix things.',
            'category_ids' => [$category->id],
            'area_ids' => [$area->id],
        ]);

        $response->assertRedirect(route('dashboard'));
        $profile = ProviderProfile::query()->where('user_id', $provider->id)->firstOrFail();
        $this->assertSame(ProviderVerificationStatus::Pending, $profile->verification_status);
        $this->assertTrue($profile->categories->contains($category));
        $this->assertTrue($profile->areas->contains($area));
    }

    public function test_customer_cannot_view_or_submit_provider_profile(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get('/provider/profile')->assertForbidden();
        $this->actingAs($customer)->post('/provider/profile', $this->payload())->assertForbidden();
    }

    public function test_admin_cannot_view_or_submit_provider_profile(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/provider/profile')->assertForbidden();
        $this->actingAs($admin)->post('/provider/profile', $this->payload())->assertForbidden();
    }

    public function test_business_name_categories_and_areas_are_required(): void
    {
        $provider = User::factory()->provider()->create();

        $response = $this->actingAs($provider)->post('/provider/profile', [
            'business_name' => '',
            'category_ids' => [],
            'area_ids' => [],
        ]);

        $response->assertInvalid(['business_name', 'category_ids', 'area_ids']);
    }

    public function test_inactive_category_cannot_be_selected(): void
    {
        $provider = User::factory()->provider()->create();
        $category = Category::factory()->inactive()->create();
        $area = Area::factory()->create();

        $response = $this->actingAs($provider)->post('/provider/profile', [
            'business_name' => 'Da Nang Fixit Co.',
            'category_ids' => [$category->id],
            'area_ids' => [$area->id],
        ]);

        $response->assertInvalid(['category_ids.0']);
    }

    public function test_inactive_area_cannot_be_selected(): void
    {
        $provider = User::factory()->provider()->create();
        $category = Category::factory()->create();
        $area = Area::factory()->inactive()->create();

        $response = $this->actingAs($provider)->post('/provider/profile', [
            'business_name' => 'Da Nang Fixit Co.',
            'category_ids' => [$category->id],
            'area_ids' => [$area->id],
        ]);

        $response->assertInvalid(['area_ids.0']);
    }

    public function test_pending_profile_cannot_be_resubmitted(): void
    {
        $provider = User::factory()->provider()->create();
        ProviderProfile::factory()->forUser($provider)->create();

        $response = $this->actingAs($provider)->post('/provider/profile', $this->payload());

        $response->assertSessionHasErrors('business_name');
    }

    public function test_approved_profile_cannot_be_resubmitted(): void
    {
        $provider = User::factory()->provider()->create();
        ProviderProfile::factory()->forUser($provider)->approved()->create();

        $response = $this->actingAs($provider)->post('/provider/profile', $this->payload());

        $response->assertSessionHasErrors('business_name');
    }

    public function test_rejected_profile_can_be_resubmitted_and_returns_to_pending(): void
    {
        $provider = User::factory()->provider()->create();
        $profile = ProviderProfile::factory()->forUser($provider)->rejected()->create();
        $profile->verification_note = 'Please add more detail.';
        $profile->save();

        $response = $this->actingAs($provider)->post('/provider/profile', $this->payload());

        $response->assertRedirect(route('dashboard'));
        $fresh = $profile->fresh();
        $this->assertSame(ProviderVerificationStatus::Pending, $fresh->verification_status);
        $this->assertNull($fresh->rejected_at);
        // The prior rejection reason is retained as Admin-internal history
        // until the next reject overwrites it.
        $this->assertSame('Please add more detail.', $fresh->verification_note);
    }

    public function test_other_providers_profile_is_not_reachable_by_id(): void
    {
        // /provider/profile takes no {id} parameter at all: there is no URL
        // that could point at another Provider's profile.
        $routes = array_map(fn ($route) => $route->uri(), \Illuminate\Support\Facades\Route::getRoutes()->getRoutesByName());
        $providerProfileRoutes = array_filter($routes, fn ($uri) => str_starts_with($uri, 'provider/'));

        foreach ($providerProfileRoutes as $uri) {
            $this->assertStringNotContainsString('{', $uri);
        }
    }

    public function test_action_rejects_non_provider_users_even_called_directly(): void
    {
        $customer = User::factory()->create();

        $this->expectException(InvalidProviderVerificationTransitionException::class);
        app(SubmitProviderProfileAction::class)->handle($customer, $this->payload());

        $this->assertDatabaseCount('provider_profiles', 0);
    }

    public function test_consecutive_submit_calls_are_rejected_once_pending(): void
    {
        $provider = User::factory()->provider()->create();
        $action = app(SubmitProviderProfileAction::class);

        $action->handle($provider, $this->payload());

        $this->expectException(InvalidProviderVerificationTransitionException::class);
        $action->handle($provider, $this->payload());
    }

    public function test_show_response_does_not_include_verification_note(): void
    {
        $provider = User::factory()->provider()->create();
        $profile = ProviderProfile::factory()->forUser($provider)->rejected()->create();
        $profile->verification_note = 'Secret internal note';
        $profile->save();

        $this->actingAs($provider)->get('/provider/profile')->assertInertia(
            fn (Assert $page) => $page->missing('profile.verification_note')
        );
    }

    public function test_provider_without_profile_sees_empty_form_not_an_error(): void
    {
        $provider = User::factory()->provider()->create();

        $this->actingAs($provider)->get('/provider/profile')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Provider/Profile')->where('profile', null)
        );
    }

    public function test_deactivated_category_and_area_disappear_from_the_picker_list(): void
    {
        $provider = User::factory()->provider()->create();
        $category = Category::factory()->create();
        $area = Area::factory()->create();

        $category->is_active = false;
        $category->save();
        $area->is_active = false;
        $area->save();

        $this->actingAs($provider)->get('/provider/profile')->assertInertia(
            fn (Assert $page) => $page
                ->where('categories', fn ($categories) => ! collect($categories)->pluck('id')->contains($category->id))
                ->where('areas', fn ($areas) => ! collect($areas)->pluck('id')->contains($area->id))
        );
    }

    public function test_an_already_approved_categorys_deactivation_does_not_break_the_read_only_summary(): void
    {
        $provider = User::factory()->provider()->create();
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        CategoryTranslation::factory()->for($category)->create(['locale' => 'en', 'name' => 'Plumbing']);
        $profile = ProviderProfile::factory()->forUser($provider)->approved()->create();
        $profile->categories()->attach($category);
        $profile->areas()->attach($area);

        $category->is_active = false;
        $category->save();
        $area->is_active = false;
        $area->save();

        // The provider's own already-approved category/area must still
        // resolve correctly on the read-only summary even though it no
        // longer appears in the (active-only) picker list above.
        $this->actingAs($provider)->get('/provider/profile')->assertInertia(
            fn (Assert $page) => $page
                ->where('profile.category_names', ['Plumbing'])
                ->where('profile.area_names', [$area->name])
        );
    }
}
