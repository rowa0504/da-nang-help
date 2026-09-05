<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\Offer;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_specifying_a_provider_does_not_create_an_extra_provider_user(): void
    {
        $provider = User::factory()->provider()->create();
        $providerCountBefore = User::where('role', UserRole::Provider->value)->count();

        // No service_request_id is pinned here, so OfferFactory's default
        // ServiceRequest::factory() still creates its own customer — that's
        // expected. What must NOT happen is a second provider being created
        // instead of reusing the pinned one.
        Offer::factory()->forProvider($provider)->create();

        $this->assertSame($providerCountBefore, User::where('role', UserRole::Provider->value)->count());
    }

    public function test_specifying_a_service_request_does_not_create_an_extra_one(): void
    {
        $serviceRequest = ServiceRequest::factory()->create();
        $countBefore = ServiceRequest::count();

        Offer::factory()->forServiceRequest($serviceRequest)->create();

        $this->assertSame($countBefore, ServiceRequest::count());
    }

    public function test_specified_provider_and_service_request_are_used(): void
    {
        $provider = User::factory()->provider()->create();
        $serviceRequest = ServiceRequest::factory()->create();

        $offer = Offer::factory()->forProvider($provider)->forServiceRequest($serviceRequest)->create();

        $this->assertSame($provider->id, $offer->provider_id);
        $this->assertSame($serviceRequest->id, $offer->service_request_id);
    }
}
