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

    public function test_submit_flash_message_is_localized(): void
    {
        $expected = [
            'en' => 'Your provider profile has been submitted for review.',
            'ja' => 'Providerプロフィールを審査のために送信しました。',
            'vi' => 'Hồ sơ nhà cung cấp của bạn đã được gửi để xét duyệt.',
        ];

        foreach ($expected as $locale => $message) {
            $provider = User::factory()->provider()->create(['locale' => $locale]);

            $this->actingAs($provider)
                ->post('/provider/profile', $this->payload())
                ->assertSessionHas('status', $message);
        }
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

    public function test_other_service_details_is_required_when_other_category_is_selected(): void
    {
        $provider = User::factory()->provider()->create();
        $other = Category::query()->where('slug', 'other')->firstOrFail();
        $area = Area::factory()->create();

        $response = $this->actingAs($provider)->post('/provider/profile', [
            'business_name' => 'Da Nang Fixit Co.',
            'category_ids' => [$other->id],
            'area_ids' => [$area->id],
        ]);

        $response->assertInvalid(['other_service_details']);
    }

    public function test_whitespace_only_other_service_details_is_rejected_when_other_is_selected(): void
    {
        // Laravel's default global TrimStrings + ConvertEmptyStringsToNull
        // middleware trims this to '' then converts it to null before
        // validation runs, so 'required' correctly fails — no extra rule
        // is needed to reject whitespace-only input.
        $provider = User::factory()->provider()->create();
        $other = Category::query()->where('slug', 'other')->firstOrFail();
        $area = Area::factory()->create();

        $response = $this->actingAs($provider)->post('/provider/profile', [
            'business_name' => 'Da Nang Fixit Co.',
            'category_ids' => [$other->id],
            'area_ids' => [$area->id],
            'other_service_details' => '   ',
        ]);

        $response->assertInvalid(['other_service_details']);
    }

    public function test_other_service_details_rejects_input_over_500_characters(): void
    {
        $provider = User::factory()->provider()->create();
        $other = Category::query()->where('slug', 'other')->firstOrFail();
        $area = Area::factory()->create();

        $response = $this->actingAs($provider)->post('/provider/profile', [
            'business_name' => 'Da Nang Fixit Co.',
            'category_ids' => [$other->id],
            'area_ids' => [$area->id],
            'other_service_details' => str_repeat('a', 501),
        ]);

        $response->assertInvalid(['other_service_details']);
    }

    public function test_other_service_details_is_saved_when_other_category_is_selected(): void
    {
        $provider = User::factory()->provider()->create();
        $other = Category::query()->where('slug', 'other')->firstOrFail();
        $area = Area::factory()->create();

        $this->actingAs($provider)->post('/provider/profile', [
            'business_name' => 'Da Nang Fixit Co.',
            'category_ids' => [$other->id],
            'area_ids' => [$area->id],
            'other_service_details' => 'Custom furniture assembly',
        ])->assertRedirect(route('dashboard'));

        $profile = ProviderProfile::query()->where('user_id', $provider->id)->firstOrFail();
        $this->assertSame('Custom furniture assembly', $profile->other_service_details);
    }

    public function test_other_service_details_is_forced_to_null_when_other_category_is_not_selected(): void
    {
        // Submitted even though "other" isn't selected — the Action must
        // ignore this value and force null, based on the validated
        // category_ids rather than the field's mere presence.
        $provider = User::factory()->provider()->create();
        $category = Category::factory()->create();
        $area = Area::factory()->create();

        $this->actingAs($provider)->post('/provider/profile', [
            'business_name' => 'Da Nang Fixit Co.',
            'category_ids' => [$category->id],
            'area_ids' => [$area->id],
            'other_service_details' => 'This should be ignored',
        ])->assertRedirect(route('dashboard'));

        $profile = ProviderProfile::query()->where('user_id', $provider->id)->firstOrFail();
        $this->assertNull($profile->other_service_details);
    }

    public function test_show_response_includes_other_service_details_when_present(): void
    {
        $provider = User::factory()->provider()->create();
        $profile = ProviderProfile::factory()->forUser($provider)->rejected()->create();
        $profile->other_service_details = 'Custom carpentry work';
        $profile->save();

        $this->actingAs($provider)->get('/provider/profile')->assertInertia(
            fn (Assert $page) => $page->where('profile.other_service_details', 'Custom carpentry work')
        );
    }

    public function test_other_service_details_becomes_null_when_a_resubmission_deselects_the_other_category(): void
    {
        $provider = User::factory()->provider()->create();
        $other = Category::query()->where('slug', 'other')->firstOrFail();
        $newCategory = Category::factory()->create();
        $area = Area::factory()->create();

        $profile = ProviderProfile::factory()->forUser($provider)->rejected()->create();
        $profile->other_service_details = 'Old other details';
        $profile->save();
        $profile->categories()->attach($other);
        $profile->areas()->attach($area);

        $response = $this->actingAs($provider)->post('/provider/profile', [
            'business_name' => 'Da Nang Fixit Co.',
            'category_ids' => [$newCategory->id],
            'area_ids' => [$area->id],
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertNull($profile->fresh()->other_service_details);
    }
}
