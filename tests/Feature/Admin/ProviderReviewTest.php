<?php

namespace Tests\Feature\Admin;

use App\Actions\Admin\ApproveProviderAction;
use App\Actions\Admin\RejectProviderAction;
use App\Enums\ProviderVerificationStatus;
use App\Exceptions\InvalidProviderVerificationTransitionException;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProviderReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_pending_list_and_detail(): void
    {
        $admin = User::factory()->admin()->create();
        $profile = ProviderProfile::factory()->create();

        $this->actingAs($admin)->get('/admin/providers')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Admin/Providers/Index')
        );

        $this->actingAs($admin)->get("/admin/providers/{$profile->id}")->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Admin/Providers/Show')
        );
    }

    public function test_customer_and_provider_are_forbidden_from_admin_routes(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->provider()->create();
        $profile = ProviderProfile::factory()->create();

        foreach ([$customer, $provider] as $user) {
            $this->actingAs($user)->get('/admin/providers')->assertForbidden();
            $this->actingAs($user)->get("/admin/providers/{$profile->id}")->assertForbidden();
            $this->actingAs($user)->patch("/admin/providers/{$profile->id}/approve")->assertForbidden();
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $profile = ProviderProfile::factory()->create();

        $this->get('/admin/providers')->assertRedirect(route('login'));
        $this->get("/admin/providers/{$profile->id}")->assertRedirect(route('login'));
    }

    public function test_admin_can_approve_a_pending_profile(): void
    {
        $admin = User::factory()->admin()->create();
        $profile = ProviderProfile::factory()->create();

        $response = $this->actingAs($admin)->patch("/admin/providers/{$profile->id}/approve");

        $response->assertRedirect(route('admin.providers.index'));
        $fresh = $profile->fresh();
        $this->assertSame(ProviderVerificationStatus::Approved, $fresh->verification_status);
        $this->assertNotNull($fresh->approved_at);
        $this->assertNull($fresh->rejected_at);
    }

    public function test_admin_can_reject_a_pending_profile(): void
    {
        $admin = User::factory()->admin()->create();
        $profile = ProviderProfile::factory()->create();

        $response = $this->actingAs($admin)->patch("/admin/providers/{$profile->id}/reject", [
            'note' => 'Business license could not be verified.',
        ]);

        $response->assertRedirect(route('admin.providers.index'));
        $fresh = $profile->fresh();
        $this->assertSame(ProviderVerificationStatus::Rejected, $fresh->verification_status);
        $this->assertNotNull($fresh->rejected_at);
        $this->assertNull($fresh->approved_at);
        $this->assertSame('Business license could not be verified.', $fresh->verification_note);
    }

    public function test_approving_an_already_approved_profile_is_forbidden(): void
    {
        $admin = User::factory()->admin()->create();
        $profile = ProviderProfile::factory()->approved()->create();

        $this->actingAs($admin)->patch("/admin/providers/{$profile->id}/approve")->assertForbidden();
    }

    public function test_rejecting_an_already_rejected_profile_is_forbidden(): void
    {
        $admin = User::factory()->admin()->create();
        $profile = ProviderProfile::factory()->rejected()->create();

        $this->actingAs($admin)->patch("/admin/providers/{$profile->id}/reject", ['note' => 'x'])->assertForbidden();
    }

    public function test_action_reverifies_pending_status_after_lock_even_if_called_twice(): void
    {
        $profile = ProviderProfile::factory()->create();

        app(ApproveProviderAction::class)->handle($profile);

        $this->expectException(InvalidProviderVerificationTransitionException::class);
        app(RejectProviderAction::class)->handle($profile->fresh(), 'too late');
    }

    public function test_consecutive_approve_then_reject_leaves_status_approved(): void
    {
        $admin = User::factory()->admin()->create();
        $profile = ProviderProfile::factory()->create();

        $this->actingAs($admin)->patch("/admin/providers/{$profile->id}/approve")->assertRedirect();
        // The Policy already blocks this (status is no longer pending), so
        // the second decision is a 403, not a state change.
        $this->actingAs($admin)->patch("/admin/providers/{$profile->id}/reject", ['note' => 'x'])->assertForbidden();

        $this->assertSame(ProviderVerificationStatus::Approved, $profile->fresh()->verification_status);
    }

    public function test_admin_detail_response_includes_verification_note(): void
    {
        $admin = User::factory()->admin()->create();
        $profile = ProviderProfile::factory()->rejected()->create();
        $profile->verification_note = 'Visible to admin only';
        $profile->save();

        $this->actingAs($admin)->get("/admin/providers/{$profile->id}")->assertInertia(
            fn (Assert $page) => $page->where('profile.verification_note', 'Visible to admin only')
        );
    }

    public function test_provider_detail_category_names_resolve_to_the_viewers_locale(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create(['slug' => 'aircon-repair']);
        CategoryTranslation::factory()->for($category)->create(['locale' => 'en', 'name' => 'Air-con Repair']);
        CategoryTranslation::factory()->for($category)->create(['locale' => 'ja', 'name' => 'エアコン修理']);
        CategoryTranslation::factory()->for($category)->create(['locale' => 'vi', 'name' => 'Sửa điều hòa']);
        $profile = ProviderProfile::factory()->create();
        $profile->categories()->attach($category);

        $expected = ['en' => 'Air-con Repair', 'ja' => 'エアコン修理', 'vi' => 'Sửa điều hòa'];
        foreach ($expected as $locale => $name) {
            $admin->locale = $locale;
            $admin->save();

            $this->actingAs($admin)->get("/admin/providers/{$profile->id}")->assertInertia(
                fn (Assert $page) => $page->where('profile.categories', [$name])
            );
        }
    }

    public function test_provider_detail_categories_do_not_trigger_n_plus_one_from_translations(): void
    {
        $admin = User::factory()->admin()->create();
        $profile = ProviderProfile::factory()->create();
        $threeCategories = Category::factory()->count(3)->create();
        foreach ($threeCategories as $category) {
            CategoryTranslation::factory()->for($category)->create(['locale' => 'en']);
        }
        $profile->categories()->attach($threeCategories);

        // Warm up first: the very first DB interaction in a test can carry
        // one-off overhead unrelated to the N+1 behavior under test (same
        // approach as the other N+1 regression tests in this codebase).
        $this->actingAs($admin)->get("/admin/providers/{$profile->id}")->assertOk();

        DB::enableQueryLog();
        $this->actingAs($admin)->get("/admin/providers/{$profile->id}")->assertOk();
        $queryCountForThree = count(DB::getQueryLog());
        DB::flushQueryLog();

        $nineMoreCategories = Category::factory()->count(9)->create();
        foreach ($nineMoreCategories as $category) {
            CategoryTranslation::factory()->for($category)->create(['locale' => 'en']);
        }
        $profile->categories()->attach($nineMoreCategories);
        DB::flushQueryLog();

        $this->actingAs($admin)->get("/admin/providers/{$profile->id}")->assertOk();
        $queryCountForTwelve = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Query count must not scale with the number of categories the
        // profile handles — if `translations` were lazy-loaded per
        // category (instead of eager loaded before nameFor() resolves each
        // name), quadrupling the category count would proportionally
        // increase the query count.
        $this->assertSame($queryCountForThree, $queryCountForTwelve);
    }
}
