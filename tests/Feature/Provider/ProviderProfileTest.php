<?php

namespace Tests\Feature\Provider;

use App\Actions\Admin\SuspendProviderAction;
use App\Actions\Provider\SubmitProviderProfileAction;
use App\Actions\Provider\UpdateProviderProfileAction;
use App\Enums\ProviderVerificationStatus;
use App\Exceptions\InvalidProviderVerificationTransitionException;
use App\Models\Area;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Offer;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
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
            'ja' => 'プロバイダープロフィールを審査のために送信しました。',
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
        // SubmitProviderProfileRequest::authorize() (shared by submit and
        // update) rejects non-Providers before the controller method body
        // ever runs, so this is 403, not 404 — the same as GET/POST above.
        $this->actingAs($customer)->patch('/provider/profile', $this->payload())->assertForbidden();
    }

    public function test_admin_cannot_view_or_submit_provider_profile(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/provider/profile')->assertForbidden();
        $this->actingAs($admin)->post('/provider/profile', $this->payload())->assertForbidden();
        $this->actingAs($admin)->patch('/provider/profile', $this->payload())->assertForbidden();
    }

    public function test_provider_gets_not_found_when_patching_without_a_profile_of_their_own(): void
    {
        // A Provider passes the Form Request's role check but has no
        // provider_profiles row yet to resolve — update() aborts 404 before
        // Policy::update() ever runs, since there's nothing to authorize
        // against. This route takes no {id}, so this can't leak any other
        // Provider's data.
        $provider = User::factory()->provider()->create();

        $this->actingAs($provider)->patch('/provider/profile', $this->payload())->assertNotFound();
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

    /**
     * @return array<string, array{string}>
     */
    public static function existingProfileStatusProvider(): array
    {
        return [
            'pending' => ['pending'],
            'rejected' => ['rejected'],
            'approved' => ['approved'],
            'suspended' => ['suspended'],
        ];
    }

    /**
     * POST /provider/profile now creates a brand-new profile only —
     * regardless of the existing profile's status, once one exists at all,
     * editing it is PATCH's job (UpdateProviderProfileAction), not POST's.
     */
    #[DataProvider('existingProfileStatusProvider')]
    public function test_post_is_rejected_once_any_profile_exists_regardless_of_its_status(string $status): void
    {
        $provider = User::factory()->provider()->create();
        $profile = ProviderProfile::factory()->forUser($provider)->create(['verification_status' => $status]);

        $response = $this->actingAs($provider)->post('/provider/profile', $this->payload());

        $response->assertSessionHasErrors('business_name');
        $this->assertSame($status, $profile->fresh()->verification_status->value);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function editableProfileStatusProvider(): array
    {
        return [
            'pending' => ['pending'],
            'rejected' => ['rejected'],
            'approved' => ['approved'],
        ];
    }

    /**
     * PATCH succeeds from every non-suspended status and, uniformly, moves
     * the profile back to Pending with the full review history cleared —
     * there is no special case for "was previously approved": Admin sees
     * this exactly like a first-time application (see plan notes on why no
     * new history column was added).
     */
    #[DataProvider('editableProfileStatusProvider')]
    public function test_patch_edits_an_existing_profile_and_clears_review_history(string $status): void
    {
        $provider = User::factory()->provider()->create();
        $profile = ProviderProfile::factory()->forUser($provider)->create([
            'verification_status' => $status,
            'approved_at' => now(),
            'rejected_at' => now(),
            'suspended_at' => now(),
            'verification_note' => 'Some prior review note.',
        ]);

        $response = $this->actingAs($provider)->patch('/provider/profile', $this->payload([
            'business_name' => 'Updated Business Name',
        ]));

        $response->assertRedirect(route('provider.profile.show'));
        $fresh = $profile->fresh();
        $this->assertSame(ProviderVerificationStatus::Pending, $fresh->verification_status);
        $this->assertSame('Updated Business Name', $fresh->business_name);
        $this->assertNull($fresh->approved_at);
        $this->assertNull($fresh->rejected_at);
        $this->assertNull($fresh->suspended_at);
        $this->assertNull($fresh->verification_note);
    }

    public function test_patch_update_flash_message_is_localized(): void
    {
        $expected = [
            'en' => 'Your changes have been submitted for review.',
            'ja' => '変更内容を審査のために送信しました。',
            'vi' => 'Các thay đổi của bạn đã được gửi để xét duyệt.',
        ];

        foreach ($expected as $locale => $message) {
            $provider = User::factory()->provider()->create(['locale' => $locale]);
            ProviderProfile::factory()->forUser($provider)->create();

            $this->actingAs($provider)
                ->patch('/provider/profile', $this->payload())
                ->assertSessionHas('status', $message);
        }
    }

    public function test_suspended_profile_cannot_be_patched(): void
    {
        $provider = User::factory()->provider()->create();
        $profile = ProviderProfile::factory()->forUser($provider)->create(['verification_status' => 'suspended']);

        $response = $this->actingAs($provider)->patch('/provider/profile', $this->payload());

        $response->assertForbidden();
        $this->assertSame(ProviderVerificationStatus::Suspended, $profile->fresh()->verification_status);
    }

    public function test_other_provider_cannot_patch_a_profile_that_is_not_their_own_even_called_directly(): void
    {
        // The route itself takes no {id} — it always resolves and
        // authorizes $request->user()'s own profile — so this exercises the
        // Action's own defence-in-depth ownership check directly, the one
        // place that would matter if this Action were ever invoked outside
        // that HTTP path.
        $owner = User::factory()->provider()->create();
        $profile = ProviderProfile::factory()->forUser($owner)->approved()->create();
        $otherProvider = User::factory()->provider()->create();

        $this->expectException(InvalidProviderVerificationTransitionException::class);
        app(UpdateProviderProfileAction::class)->handle($otherProvider, $profile, $this->payload());
    }

    public function test_update_action_rejects_non_provider_users_even_called_directly(): void
    {
        $customer = User::factory()->create();
        $profile = ProviderProfile::factory()->forUser($customer)->approved()->create();

        $this->expectException(InvalidProviderVerificationTransitionException::class);
        app(UpdateProviderProfileAction::class)->handle($customer, $profile, $this->payload());
    }

    /**
     * A sequential direct-call pair, not a true parallel-transaction test:
     * PHPUnit runs single-threaded, so this cannot reproduce two requests
     * racing for the same row lock. What it does verify is the half that
     * matters — that whichever Action runs *second* re-reads the
     * now-committed state and correctly refuses to act on stale
     * assumptions, which is exactly what the row lock is there to
     * guarantee once two real concurrent requests are serialized by it.
     */
    public function test_edit_after_suspend_has_already_committed_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = User::factory()->provider()->create();
        $profile = ProviderProfile::factory()->forUser($provider)->approved()->create();

        app(SuspendProviderAction::class)->handle($admin, $profile, 'suspended first');

        $this->expectException(InvalidProviderVerificationTransitionException::class);
        app(UpdateProviderProfileAction::class)->handle($provider, $profile->fresh(), $this->payload());
    }

    public function test_suspend_after_edit_has_already_committed_is_rejected(): void
    {
        // SuspendProviderAction is intentionally unmodified: it already
        // only allows Approved -> Suspended, so once the edit below has
        // already moved the profile to Pending, Suspend's own existing
        // guard rejects it with no changes needed on that side.
        $admin = User::factory()->admin()->create();
        $provider = User::factory()->provider()->create();
        $profile = ProviderProfile::factory()->forUser($provider)->approved()->create();

        app(UpdateProviderProfileAction::class)->handle($provider, $profile, $this->payload());

        $this->expectException(InvalidProviderVerificationTransitionException::class);
        app(SuspendProviderAction::class)->handle($admin, $profile->fresh(), 'attempted after edit');
    }

    public function test_edit_keeps_an_already_attached_but_now_inactive_category_and_area(): void
    {
        $provider = User::factory()->provider()->create();
        $activeCategory = Category::factory()->create();
        $inactiveCategory = Category::factory()->create();
        $activeArea = Area::factory()->create();
        $inactiveArea = Area::factory()->create();
        $profile = ProviderProfile::factory()->forUser($provider)->approved()->create();
        $profile->categories()->attach([$activeCategory->id, $inactiveCategory->id]);
        $profile->areas()->attach([$activeArea->id, $inactiveArea->id]);
        $inactiveCategory->update(['is_active' => false]);
        $inactiveArea->update(['is_active' => false]);

        $response = $this->actingAs($provider)->patch('/provider/profile', [
            'business_name' => 'Da Nang Fixit Co.',
            'category_ids' => [$activeCategory->id, $inactiveCategory->id],
            'area_ids' => [$activeArea->id, $inactiveArea->id],
        ]);

        $response->assertRedirect(route('provider.profile.show'));
        $fresh = $profile->fresh();
        $this->assertTrue($fresh->categories->contains($inactiveCategory));
        $this->assertTrue($fresh->areas->contains($inactiveArea));
    }

    public function test_edit_cannot_add_a_new_category_or_area_that_was_never_attached_and_is_inactive(): void
    {
        $provider = User::factory()->provider()->create();
        $activeCategory = Category::factory()->create();
        $activeArea = Area::factory()->create();
        $neverAttachedInactiveCategory = Category::factory()->inactive()->create();
        $neverAttachedInactiveArea = Area::factory()->inactive()->create();
        $profile = ProviderProfile::factory()->forUser($provider)->approved()->create();
        $profile->categories()->attach($activeCategory);
        $profile->areas()->attach($activeArea);

        $responseA = $this->actingAs($provider)->patch('/provider/profile', [
            'business_name' => 'Da Nang Fixit Co.',
            'category_ids' => [$activeCategory->id, $neverAttachedInactiveCategory->id],
            'area_ids' => [$activeArea->id],
        ]);
        $responseA->assertInvalid(['category_ids.1']);

        $responseB = $this->actingAs($provider)->patch('/provider/profile', [
            'business_name' => 'Da Nang Fixit Co.',
            'category_ids' => [$activeCategory->id],
            'area_ids' => [$activeArea->id, $neverAttachedInactiveArea->id],
        ]);
        $responseB->assertInvalid(['area_ids.1']);
    }

    public function test_a_removed_inactive_category_cannot_be_re_added_on_a_later_edit(): void
    {
        $provider = User::factory()->provider()->create();
        $activeCategory = Category::factory()->create();
        $inactiveCategory = Category::factory()->create();
        $activeArea = Area::factory()->create();
        $profile = ProviderProfile::factory()->forUser($provider)->approved()->create();
        $profile->categories()->attach([$activeCategory->id, $inactiveCategory->id]);
        $profile->areas()->attach($activeArea);
        $inactiveCategory->update(['is_active' => false]);

        // First edit: drop the inactive category (don't include it).
        $this->actingAs($provider)->patch('/provider/profile', [
            'business_name' => 'Da Nang Fixit Co.',
            'category_ids' => [$activeCategory->id],
            'area_ids' => [$activeArea->id],
        ])->assertRedirect(route('provider.profile.show'));
        $this->assertFalse($profile->fresh()->categories->contains($inactiveCategory));

        // Second edit: trying to bring it back now fails — the "already
        // attached" carve-out is read fresh from the DB's pre-update pivot
        // each time, and it's no longer there.
        $this->actingAs($provider)->patch('/provider/profile', [
            'business_name' => 'Da Nang Fixit Co.',
            'category_ids' => [$activeCategory->id, $inactiveCategory->id],
            'area_ids' => [$activeArea->id],
        ])->assertInvalid(['category_ids.1']);
    }

    public function test_editing_an_approved_profile_back_to_pending_blocks_feed_and_new_offers(): void
    {
        $provider = User::factory()->provider()->create();
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $profile = ProviderProfile::factory()->forUser($provider)->approved()->create();
        $profile->categories()->attach($category);
        $profile->areas()->attach($area);
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);

        $this->assertTrue($provider->can('create', [Offer::class, $serviceRequest]));

        $this->actingAs($provider)->patch('/provider/profile', $this->payload([
            'category_ids' => [$category->id],
            'area_ids' => [$area->id],
        ]))->assertRedirect(route('provider.profile.show'));

        // isApprovedProvider() (ServiceRequestPolicy/OfferPolicy) already
        // gates on verification_status === Approved — no new code needed
        // for this, it's a pure regression check that the edit's status
        // change is enough on its own.
        $this->assertFalse($provider->fresh()->can('create', [Offer::class, $serviceRequest]));
        $this->assertFalse($provider->fresh()->can('view', $serviceRequest->fresh()));
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

        $response = $this->actingAs($provider)->patch('/provider/profile', [
            'business_name' => 'Da Nang Fixit Co.',
            'category_ids' => [$newCategory->id],
            'area_ids' => [$area->id],
        ]);

        $response->assertRedirect(route('provider.profile.show'));
        $this->assertNull($profile->fresh()->other_service_details);
    }
}
