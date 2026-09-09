<?php

namespace Tests\Feature\Admin;

use App\Actions\Admin\HideServiceRequestAction;
use App\Enums\OfferStatus;
use App\Enums\ServiceRequestModerationStatus;
use App\Enums\ServiceRequestStatus;
use App\Exceptions\InvalidServiceRequestTransitionException;
use App\Models\Area;
use App\Models\Category;
use App\Models\Offer;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ServiceRequestModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_moderation_list(): void
    {
        $admin = User::factory()->admin()->create();
        ServiceRequest::factory()->create();

        $this->actingAs($admin)->get('/admin/requests')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Admin/Requests/Index')->has('requests.data', 1)
        );
    }

    public function test_customer_and_provider_are_forbidden(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->provider()->create();

        foreach ([$customer, $provider] as $user) {
            $this->actingAs($user)->get('/admin/requests')->assertForbidden();
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/requests')->assertRedirect(route('login'));
    }

    public function test_list_is_newest_first_paginated_and_includes_both_visible_and_hidden(): void
    {
        $admin = User::factory()->admin()->create();
        // created_at is not #[Fillable], so it must be backdated via direct
        // assignment after creation (same pitfall as moderation_status
        // elsewhere) — save() on an already-existing model does not touch
        // created_at, only updated_at.
        $older = ServiceRequest::factory()->create();
        $older->created_at = now()->subDay();
        $older->save();
        $newer = ServiceRequest::factory()->hidden()->create();

        $this->actingAs($admin)->get('/admin/requests')->assertInertia(
            fn (Assert $page) => $page
                ->where('requests.data.0.id', $newer->id)
                ->where('requests.data.0.moderation_status', 'hidden')
                ->where('requests.data.1.id', $older->id)
                ->where('requests.data.1.moderation_status', 'visible')
                ->where('requests.meta.per_page', 20)
        );
    }

    public function test_admin_can_hide_a_visible_request(): void
    {
        $admin = User::factory()->admin()->create();
        $serviceRequest = ServiceRequest::factory()->create();

        $response = $this->actingAs($admin)->patch("/admin/requests/{$serviceRequest->id}/hide");

        $response->assertRedirect(route('admin.requests.index'));
        $fresh = $serviceRequest->fresh();
        $this->assertSame(ServiceRequestModerationStatus::Hidden, $fresh->moderation_status);
        $this->assertNotNull($fresh->hidden_at);
        $this->assertSame($admin->id, $fresh->hidden_by);
    }

    public function test_hide_flash_message_is_localized(): void
    {
        $expected = [
            'en' => 'Request hidden.',
            'ja' => '依頼を非表示にしました。',
            'vi' => 'Đã ẩn yêu cầu.',
        ];

        foreach ($expected as $locale => $message) {
            $admin = User::factory()->admin()->create(['locale' => $locale]);
            $serviceRequest = ServiceRequest::factory()->create();

            $this->actingAs($admin)
                ->patch("/admin/requests/{$serviceRequest->id}/hide")
                ->assertSessionHas('status', $message);
        }
    }

    public function test_hiding_an_already_hidden_request_is_forbidden(): void
    {
        $admin = User::factory()->admin()->create();
        $serviceRequest = ServiceRequest::factory()->hidden()->create();

        $this->actingAs($admin)->patch("/admin/requests/{$serviceRequest->id}/hide")->assertForbidden();
    }

    public function test_customer_and_provider_cannot_hide(): void
    {
        $customer = User::factory()->create();
        $provider = User::factory()->provider()->create();
        $serviceRequest = ServiceRequest::factory()->create();

        foreach ([$customer, $provider] as $user) {
            $this->actingAs($user)->patch("/admin/requests/{$serviceRequest->id}/hide")->assertForbidden();
        }
    }

    public function test_hide_action_reverifies_status_after_lock_even_if_called_twice(): void
    {
        $admin = User::factory()->admin()->create();
        $serviceRequest = ServiceRequest::factory()->create();

        app(HideServiceRequestAction::class)->handle($admin, $serviceRequest);

        $this->expectException(InvalidServiceRequestTransitionException::class);
        app(HideServiceRequestAction::class)->handle($admin, $serviceRequest->fresh());
    }

    public function test_hiding_a_request_does_not_change_its_own_status_or_any_existing_offer_or_job(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = User::factory()->provider()->create();
        $profile = ProviderProfile::factory()->forUser($provider)->approved()->create();
        $profile->categories()->attach($category);
        $profile->areas()->attach($area);
        $admin = User::factory()->admin()->create();

        $offer = Offer::factory()->forServiceRequest($serviceRequest)->forProvider($provider)->create();

        app(HideServiceRequestAction::class)->handle($admin, $serviceRequest);

        $this->assertSame(ServiceRequestStatus::Open, $serviceRequest->fresh()->status);
        $this->assertSame(OfferStatus::Pending, $offer->fresh()->status);
    }

    public function test_a_hidden_request_remains_visible_to_its_owner_and_to_admin(): void
    {
        $customer = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->hidden()->create();

        $this->actingAs($customer)->get("/requests/{$serviceRequest->id}")->assertOk();
        $this->actingAs($admin)->get("/requests/{$serviceRequest->id}")->assertOk();
    }

    public function test_list_does_not_trigger_n_plus_one_as_request_count_grows(): void
    {
        $admin = User::factory()->admin()->create();
        ServiceRequest::factory()->count(3)->create();

        // Warm up first: the very first DB interaction in a test can carry
        // one-off overhead unrelated to the N+1 behavior under test.
        $this->actingAs($admin)->get('/admin/requests')->assertOk();

        DB::enableQueryLog();
        $this->actingAs($admin)->get('/admin/requests')->assertOk();
        $queryCountForThree = count(DB::getQueryLog());
        DB::flushQueryLog();

        ServiceRequest::factory()->count(9)->create();
        DB::flushQueryLog();

        $this->actingAs($admin)->get('/admin/requests')->assertOk();
        $queryCountForTwelve = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($queryCountForThree, $queryCountForTwelve);
    }
}
