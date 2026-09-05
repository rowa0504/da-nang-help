<?php

namespace Tests\Unit;

use App\Enums\ServiceJobStatus;
use App\Models\Offer;
use App\Models\ServiceJob;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceJobModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_casts_are_correct_types(): void
    {
        $job = ServiceJob::factory()->create();
        $fresh = $job->fresh();

        $this->assertSame(ServiceJobStatus::Assigned, $fresh->status);
    }

    public function test_relations_resolve(): void
    {
        $offer = Offer::factory()->accepted()->create();
        $job = ServiceJob::factory()->forOffer($offer)->create();

        $this->assertTrue($job->offer->is($offer));
        $this->assertTrue($job->serviceRequest->is($offer->serviceRequest));
        $this->assertTrue($job->customer->is($offer->serviceRequest->customer));
        $this->assertTrue($job->provider->is($offer->provider));
    }

    public function test_fillable_is_empty_but_direct_assignment_persists(): void
    {
        $job = ServiceJob::factory()->create();
        $fresh = $job->fresh();

        $this->assertNotNull($fresh->service_request_id);
        $this->assertNotNull($fresh->offer_id);
        $this->assertNotNull($fresh->customer_id);
        $this->assertNotNull($fresh->provider_id);
    }

    public function test_service_request_id_is_unique(): void
    {
        $offer = Offer::factory()->accepted()->create();
        ServiceJob::factory()->forOffer($offer)->create();

        $otherOffer = Offer::factory()->forServiceRequest($offer->serviceRequest)->accepted()->create();

        $this->expectException(QueryException::class);
        ServiceJob::factory()->forOffer($otherOffer)->create(['service_request_id' => $offer->service_request_id]);
    }

    public function test_offer_id_is_unique(): void
    {
        $offer = Offer::factory()->accepted()->create();
        ServiceJob::factory()->forOffer($offer)->create();

        $this->expectException(QueryException::class);
        ServiceJob::factory()->create(['offer_id' => $offer->id]);
    }
}
