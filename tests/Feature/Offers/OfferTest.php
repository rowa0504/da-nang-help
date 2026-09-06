<?php

namespace Tests\Feature\Offers;

use App\Actions\Offer\AcceptOfferAction;
use App\Actions\Offer\RejectOfferAction;
use App\Actions\Offer\UpdateOfferAction;
use App\Actions\Offer\WithdrawOfferAction;
use App\Enums\OfferStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\TranslationStatus;
use App\Enums\UserRole;
use App\Exceptions\InvalidOfferTransitionException;
use App\Models\Area;
use App\Models\Category;
use App\Models\Offer;
use App\Models\OfferTranslation;
use App\Enums\ServiceRequestModerationStatus;
use App\Models\ProviderProfile;
use App\Models\ServiceJob;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Policies\OfferPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OfferTest extends TestCase
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

    private function offerPayload(array $overrides = []): array
    {
        return array_merge([
            'price' => '150.00',
            'currency' => 'USD',
            'message' => 'I can help with this.',
            'available_at' => null,
            'source_locale' => 'en',
        ], $overrides);
    }

    public function test_approved_matching_provider_can_send_an_offer(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);

        $response = $this->actingAs($provider)->post("/requests/{$serviceRequest->id}/offers", $this->offerPayload());

        $response->assertRedirect(route('requests.show', $serviceRequest));
        $offer = Offer::where('service_request_id', $serviceRequest->id)->where('provider_id', $provider->id)->firstOrFail();
        $this->assertSame(OfferStatus::Pending, $offer->status);
    }

    public function test_unapproved_provider_cannot_send_an_offer(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = User::factory()->provider()->create();
        ProviderProfile::factory()->forUser($provider)->create(); // pending

        $this->actingAs($provider)->post("/requests/{$serviceRequest->id}/offers", $this->offerPayload())->assertForbidden();
    }

    public function test_category_mismatched_provider_cannot_send_an_offer(): void
    {
        $category = Category::factory()->create();
        $otherCategory = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($otherCategory, $area);

        $this->actingAs($provider)->post("/requests/{$serviceRequest->id}/offers", $this->offerPayload())->assertForbidden();
    }

    public function test_area_mismatched_provider_cannot_send_an_offer(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $otherArea = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $otherArea);

        $this->actingAs($provider)->post("/requests/{$serviceRequest->id}/offers", $this->offerPayload())->assertForbidden();
    }

    public function test_hidden_request_cannot_receive_offers(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->hidden()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);

        $this->actingAs($provider)->post("/requests/{$serviceRequest->id}/offers", $this->offerPayload())->assertForbidden();
    }

    public function test_non_open_request_cannot_receive_offers(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->cancelled()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);

        $this->actingAs($provider)->post("/requests/{$serviceRequest->id}/offers", $this->offerPayload())->assertForbidden();
    }

    public function test_duplicate_offer_is_rejected_by_policy(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);

        $this->actingAs($provider)->post("/requests/{$serviceRequest->id}/offers", $this->offerPayload())->assertRedirect();
        $this->actingAs($provider)->post("/requests/{$serviceRequest->id}/offers", $this->offerPayload())->assertForbidden();
        $this->assertSame(1, Offer::where('service_request_id', $serviceRequest->id)->where('provider_id', $provider->id)->count());
    }

    public function test_duplicate_offer_action_direct_call_is_rejected_after_lock(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);

        $action = app(\App\Actions\Offer\CreateOfferAction::class);
        $action->handle($provider, $serviceRequest, $this->offerPayload());

        $this->expectException(\App\Exceptions\DuplicateOfferException::class);
        $action->handle($provider, $serviceRequest, $this->offerPayload());
    }

    public function test_customer_cannot_offer_on_own_request_defensive_check(): void
    {
        $customer = User::factory()->create();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create();
        // The app's single-role-per-user design makes a Provider sharing a
        // Customer's id unreachable in real data; this constructs an
        // in-memory User to exercise the defensive check directly (FR
        // explicitly requires it, see OfferPolicy::create()).
        $providerWithSameId = User::factory()->provider()->make(['id' => $customer->id]);

        $this->assertFalse((new OfferPolicy())->create($providerWithSameId, $serviceRequest));
    }

    public function test_price_boundary_values(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $provider = $this->approvedProviderFor($category, $area);

        $atBoundary = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $this->actingAs($provider)
            ->post("/requests/{$atBoundary->id}/offers", $this->offerPayload(['price' => '9999999999.99']))
            ->assertRedirect();

        $provider2 = $this->approvedProviderFor($category, $area);
        $overBoundary = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $this->actingAs($provider2)
            ->post("/requests/{$overBoundary->id}/offers", $this->offerPayload(['price' => '10000000000.00']))
            ->assertInvalid(['price']);

        $provider3 = $this->approvedProviderFor($category, $area);
        $threeDecimals = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $this->actingAs($provider3)
            ->post("/requests/{$threeDecimals->id}/offers", $this->offerPayload(['price' => '10.123']))
            ->assertInvalid(['price']);
    }

    public function test_price_is_returned_as_string_not_number(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);

        $this->actingAs($provider)->post("/requests/{$serviceRequest->id}/offers", $this->offerPayload(['price' => '99.90']));

        $this->actingAs($serviceRequest->customer)->get("/requests/{$serviceRequest->id}/offers")->assertInertia(
            fn (Assert $page) => $page->where('offers.data.0.price', fn ($price) => is_string($price) && $price === '99.90')
        );
    }

    public function test_available_at_round_trips_as_utc_without_timezone_conversion(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);

        $utcIso = '2026-09-10T05:00:00.000Z';
        $this->actingAs($provider)->post("/requests/{$serviceRequest->id}/offers", $this->offerPayload(['available_at' => $utcIso]));

        $offer = Offer::where('service_request_id', $serviceRequest->id)->firstOrFail();
        $this->assertSame('2026-09-10T05:00:00+00:00', $offer->fresh()->available_at->toIso8601String());
    }

    public function test_offers_index_is_customer_and_admin_only(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);
        $admin = User::factory()->admin()->create();
        $otherCustomer = User::factory()->create();

        $this->actingAs($serviceRequest->customer)->get("/requests/{$serviceRequest->id}/offers")->assertOk();
        $this->actingAs($admin)->get("/requests/{$serviceRequest->id}/offers")->assertOk();
        $this->actingAs($provider)->get("/requests/{$serviceRequest->id}/offers")->assertForbidden();
        $this->actingAs($otherCustomer)->get("/requests/{$serviceRequest->id}/offers")->assertForbidden();
    }

    public function test_provider_can_edit_pending_offer_and_translations_reset_on_message_change(): void
    {
        Queue::fake();
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);
        $offer = Offer::factory()->forServiceRequest($serviceRequest)->forProvider($provider)->create(['message' => 'Old message', 'source_locale' => 'en']);
        OfferTranslation::factory()->completed()->create(['offer_id' => $offer->id, 'locale' => 'ja']);
        OfferTranslation::factory()->completed()->create(['offer_id' => $offer->id, 'locale' => 'vi']);

        $response = $this->actingAs($provider)->patch("/offers/{$offer->id}", $this->offerPayload(['message' => 'New message']));

        $response->assertRedirect(route('requests.show', $serviceRequest));
        $fresh = $offer->fresh();
        $this->assertSame('New message', $fresh->message);
        $this->assertTrue($fresh->translations->every(fn ($t) => $t->translation_status === TranslationStatus::Pending));
    }

    public function test_source_locale_change_rebuilds_the_translation_target_set(): void
    {
        Queue::fake();
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);
        $offer = Offer::factory()->forServiceRequest($serviceRequest)->forProvider($provider)->create(['message' => 'Same message', 'source_locale' => 'en']);
        OfferTranslation::factory()->completed()->create(['offer_id' => $offer->id, 'locale' => 'ja']);
        OfferTranslation::factory()->completed()->create(['offer_id' => $offer->id, 'locale' => 'vi']);

        $this->actingAs($provider)->patch("/offers/{$offer->id}", $this->offerPayload(['message' => 'Same message', 'source_locale' => 'ja']));

        $fresh = $offer->fresh();
        $locales = $fresh->translations->pluck('locale')->sort()->values()->all();
        // 'ja' is now the source locale, so its old translation row must be
        // gone; 'en' (the old source) and 'vi' (already a target) remain.
        $this->assertSame(['en', 'vi'], $locales);
        $this->assertTrue($fresh->translations->every(fn ($t) => $t->translation_status === TranslationStatus::Pending));
    }

    public function test_non_pending_offer_cannot_be_edited(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);
        $offer = Offer::factory()->forServiceRequest($serviceRequest)->forProvider($provider)->withdrawn()->create();

        $this->actingAs($provider)->patch("/offers/{$offer->id}", $this->offerPayload())->assertForbidden();
    }

    public function test_other_provider_cannot_edit_offer(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);
        $otherProvider = $this->approvedProviderFor($category, $area);
        $offer = Offer::factory()->forServiceRequest($serviceRequest)->forProvider($provider)->create();

        $this->actingAs($otherProvider)->patch("/offers/{$offer->id}", $this->offerPayload())->assertForbidden();
    }

    public function test_provider_can_withdraw_pending_offer(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);
        $offer = Offer::factory()->forServiceRequest($serviceRequest)->forProvider($provider)->create();

        $response = $this->actingAs($provider)->patch("/offers/{$offer->id}/withdraw");

        $response->assertRedirect();
        $fresh = $offer->fresh();
        $this->assertSame(OfferStatus::Withdrawn, $fresh->status);
        $this->assertNotNull($fresh->withdrawn_at);
    }

    public function test_customer_can_reject_a_pending_offer(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);
        $offer = Offer::factory()->forServiceRequest($serviceRequest)->forProvider($provider)->create();

        $response = $this->actingAs($serviceRequest->customer)->patch("/offers/{$offer->id}/reject");

        $response->assertRedirect(route('requests.offers.index', $serviceRequest));
        $this->assertSame(OfferStatus::Rejected, $offer->fresh()->status);
        $this->assertSame(ServiceRequestStatus::Open, $serviceRequest->fresh()->status);
    }

    public function test_accept_flow_rejects_siblings_assigns_request_and_creates_job(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $providerA = $this->approvedProviderFor($category, $area);
        $providerB = $this->approvedProviderFor($category, $area);
        $offerA = Offer::factory()->forServiceRequest($serviceRequest)->forProvider($providerA)->create(['price' => '200.00', 'currency' => 'USD']);
        $offerB = Offer::factory()->forServiceRequest($serviceRequest)->forProvider($providerB)->create();

        $response = $this->actingAs($serviceRequest->customer)->patch("/offers/{$offerA->id}/accept");

        $response->assertRedirect(route('requests.show', $serviceRequest));
        $this->assertSame(OfferStatus::Accepted, $offerA->fresh()->status);
        $this->assertSame(OfferStatus::Rejected, $offerB->fresh()->status);
        $this->assertSame(ServiceRequestStatus::Assigned, $serviceRequest->fresh()->status);

        $job = ServiceJob::where('service_request_id', $serviceRequest->id)->firstOrFail();
        $this->assertSame($offerA->id, $job->offer_id);
        $this->assertSame($providerA->id, $job->provider_id);
        $this->assertSame($serviceRequest->customer_id, $job->customer_id);
        $this->assertSame('200.00', $job->agreed_price);
        $this->assertSame('USD', $job->currency);
    }

    public function test_accept_on_non_open_request_is_forbidden(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->cancelled()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);
        $offer = Offer::factory()->forServiceRequest($serviceRequest)->forProvider($provider)->cancelled()->create();

        $this->actingAs($serviceRequest->customer)->patch("/offers/{$offer->id}/accept")->assertForbidden();
    }

    public function test_accept_on_non_pending_offer_is_forbidden(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);
        $offer = Offer::factory()->forServiceRequest($serviceRequest)->forProvider($provider)->rejected()->create();

        $this->actingAs($serviceRequest->customer)->patch("/offers/{$offer->id}/accept")->assertForbidden();
    }

    public function test_accept_action_direct_call_rejects_wrong_role(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);
        $offer = Offer::factory()->forServiceRequest($serviceRequest)->forProvider($provider)->create();
        $notCustomer = User::factory()->admin()->create();

        $this->expectException(InvalidOfferTransitionException::class);
        app(AcceptOfferAction::class)->handle($notCustomer, $offer);
    }

    public function test_accept_action_direct_call_rejects_wrong_customer(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);
        $offer = Offer::factory()->forServiceRequest($serviceRequest)->forProvider($provider)->create();
        $otherCustomer = User::factory()->create();

        $this->expectException(InvalidOfferTransitionException::class);
        app(AcceptOfferAction::class)->handle($otherCustomer, $offer);

        $this->assertSame(OfferStatus::Pending, $offer->fresh()->status);
    }

    public function test_update_action_direct_call_rejects_wrong_role(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);
        $offer = Offer::factory()->forServiceRequest($serviceRequest)->forProvider($provider)->create();
        $notProvider = User::factory()->admin()->create();

        $this->expectException(InvalidOfferTransitionException::class);
        app(UpdateOfferAction::class)->handle($notProvider, $offer, $this->offerPayload());
    }

    public function test_withdraw_action_direct_call_rejects_wrong_role(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);
        $offer = Offer::factory()->forServiceRequest($serviceRequest)->forProvider($provider)->create();
        $notProvider = User::factory()->admin()->create();

        $this->expectException(InvalidOfferTransitionException::class);
        app(WithdrawOfferAction::class)->handle($notProvider, $offer);

        $this->assertSame(OfferStatus::Pending, $offer->fresh()->status);
    }

    public function test_withdraw_action_direct_call_rejects_wrong_owner(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);
        $otherProvider = $this->approvedProviderFor($category, $area);
        $offer = Offer::factory()->forServiceRequest($serviceRequest)->forProvider($provider)->create();

        $this->expectException(InvalidOfferTransitionException::class);
        app(WithdrawOfferAction::class)->handle($otherProvider, $offer);

        $this->assertSame(OfferStatus::Pending, $offer->fresh()->status);
    }

    public function test_reject_action_direct_call_rejects_wrong_role(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);
        $offer = Offer::factory()->forServiceRequest($serviceRequest)->forProvider($provider)->create();
        $notCustomer = User::factory()->admin()->create();

        $this->expectException(InvalidOfferTransitionException::class);
        app(RejectOfferAction::class)->handle($notCustomer, $offer);

        $this->assertSame(OfferStatus::Pending, $offer->fresh()->status);
    }

    public function test_reject_action_direct_call_rejects_wrong_customer(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);
        $offer = Offer::factory()->forServiceRequest($serviceRequest)->forProvider($provider)->create();
        $otherCustomer = User::factory()->create();

        $this->expectException(InvalidOfferTransitionException::class);
        app(RejectOfferAction::class)->handle($otherCustomer, $offer);

        $this->assertSame(OfferStatus::Pending, $offer->fresh()->status);
    }

    public function test_consecutive_accept_of_the_same_offer_does_not_create_a_second_job(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);
        $offer = Offer::factory()->forServiceRequest($serviceRequest)->forProvider($provider)->create();

        app(AcceptOfferAction::class)->handle($serviceRequest->customer, $offer);

        $this->expectException(InvalidOfferTransitionException::class);
        app(AcceptOfferAction::class)->handle($serviceRequest->customer, $offer->fresh());

        $this->assertSame(1, ServiceJob::where('service_request_id', $serviceRequest->id)->count());
    }

    public function test_accepting_an_already_rejected_sibling_offer_does_not_create_a_second_job(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $providerA = $this->approvedProviderFor($category, $area);
        $providerB = $this->approvedProviderFor($category, $area);
        $offerA = Offer::factory()->forServiceRequest($serviceRequest)->forProvider($providerA)->create();
        $offerB = Offer::factory()->forServiceRequest($serviceRequest)->forProvider($providerB)->create();

        app(AcceptOfferAction::class)->handle($serviceRequest->customer, $offerA);
        // offerB is now auto-rejected as a sibling.

        $this->expectException(InvalidOfferTransitionException::class);
        app(AcceptOfferAction::class)->handle($serviceRequest->customer, $offerB->fresh());

        $this->assertSame(1, ServiceJob::where('service_request_id', $serviceRequest->id)->count());
    }

    public function test_service_request_cancellation_cascades_to_pending_offers(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);
        $offer = Offer::factory()->forServiceRequest($serviceRequest)->forProvider($provider)->create();

        $this->actingAs($serviceRequest->customer)->patch("/requests/{$serviceRequest->id}/cancel");

        $this->assertSame(OfferStatus::Cancelled, $offer->fresh()->status);
        $this->assertNotNull($offer->fresh()->cancelled_at);
    }

    public function test_my_offer_is_included_in_show_response_with_original_message_and_source_locale(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);
        Offer::factory()->forServiceRequest($serviceRequest)->forProvider($provider)->create([
            'message' => 'My original text',
            'source_locale' => 'ja',
        ]);

        $this->actingAs($provider)->get("/requests/{$serviceRequest->id}")->assertInertia(
            fn (Assert $page) => $page
                ->where('myOffer.original_message', 'My original text')
                ->where('myOffer.source_locale', 'ja')
        );
    }

    public function test_offer_holder_can_still_view_assigned_request_without_private_fields(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $providerA = $this->approvedProviderFor($category, $area);
        $providerB = $this->approvedProviderFor($category, $area);
        $offerA = Offer::factory()->forServiceRequest($serviceRequest)->forProvider($providerA)->create();
        Offer::factory()->forServiceRequest($serviceRequest)->forProvider($providerB)->create();

        app(AcceptOfferAction::class)->handle($serviceRequest->customer, $offerA);

        $this->actingAs($providerB)->get("/requests/{$serviceRequest->id}")->assertOk()->assertInertia(
            fn (Assert $page) => $page->missing('request.address_text')->missing('request.customer')
        );
    }

    public function test_offer_holder_can_still_view_cancelled_request_without_private_fields(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);
        Offer::factory()->forServiceRequest($serviceRequest)->forProvider($provider)->create();

        $this->actingAs($serviceRequest->customer)->patch("/requests/{$serviceRequest->id}/cancel");

        $this->actingAs($provider)->get("/requests/{$serviceRequest->id}")->assertOk()->assertInertia(
            fn (Assert $page) => $page->missing('request.address_text')->missing('request.customer')
        );
    }

    public function test_offer_message_translation_metadata_reflects_the_original_when_the_viewer_shares_the_source_locale(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $serviceRequest->customer->locale = 'en';
        $serviceRequest->customer->save();
        $provider = $this->approvedProviderFor($category, $area);
        Offer::factory()->forServiceRequest($serviceRequest)->forProvider($provider)->create([
            'message' => 'I can help with this.',
            'source_locale' => 'en',
        ]);

        $this->actingAs($serviceRequest->customer)->get("/requests/{$serviceRequest->id}/offers")->assertInertia(
            fn (Assert $page) => $page
                ->where('offers.data.0.message_translation.is_translated', false)
                ->where('offers.data.0.message_translation.source_locale', 'en')
                ->where('offers.data.0.message_translation.original', 'I can help with this.')
        );
    }

    public function test_offer_message_translation_metadata_marks_a_completed_translation_as_translated(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $customer = User::factory()->create();
        $customer->locale = 'ja';
        $customer->save();
        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);
        $offer = Offer::factory()->forServiceRequest($serviceRequest)->forProvider($provider)->create([
            'message' => 'I can help with this.',
            'source_locale' => 'en',
        ]);
        OfferTranslation::factory()->completed()->create([
            'offer_id' => $offer->id,
            'locale' => 'ja',
            'message' => 'お手伝いできます。',
        ]);

        $this->actingAs($customer)->get("/requests/{$serviceRequest->id}/offers")->assertInertia(
            fn (Assert $page) => $page
                ->where('offers.data.0.message', 'お手伝いできます。')
                ->where('offers.data.0.message_translation.is_translated', true)
                ->where('offers.data.0.message_translation.original', 'I can help with this.')
        );
    }

    public function test_hidden_request_is_forbidden_even_for_existing_offer_holder(): void
    {
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $serviceRequest = ServiceRequest::factory()->create(['category_id' => $category->id, 'area_id' => $area->id]);
        $provider = $this->approvedProviderFor($category, $area);
        Offer::factory()->forServiceRequest($serviceRequest)->forProvider($provider)->create();

        // moderation_status is not Fillable, so it must be set via direct
        // attribute assignment (mirroring ServiceRequestFactory::hidden()),
        // not update()/fill(), which would silently discard it.
        $serviceRequest->moderation_status = ServiceRequestModerationStatus::Hidden;
        $serviceRequest->save();

        $this->actingAs($provider)->get("/requests/{$serviceRequest->id}")->assertForbidden();
    }
}
