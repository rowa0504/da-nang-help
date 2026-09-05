<?php

namespace Database\Factories;

use App\Enums\ServiceJobStatus;
use App\Models\Offer;
use App\Models\ServiceJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceJob>
 */
class ServiceJobFactory extends Factory
{
    public function definition(): array
    {
        $offer = Offer::factory()->create();

        return array_merge($this->attributesFromOffer($offer), [
            'service_request_id' => $offer->service_request_id,
            'offer_id' => $offer->id,
        ]);
    }

    /**
     * Snapshot the given Offer's identifiers/price/currency, mirroring what
     * AcceptOfferAction does. Uses for() so no extra Offer/ServiceRequest is
     * created when a specific Offer is already provided.
     */
    public function forOffer(Offer $offer): static
    {
        return $this->state(fn () => $this->attributesFromOffer($offer))
            ->for($offer, 'offer')
            ->for($offer->serviceRequest, 'serviceRequest');
    }

    private function attributesFromOffer(Offer $offer): array
    {
        return [
            'customer_id' => $offer->serviceRequest->customer_id,
            'provider_id' => $offer->provider_id,
            'agreed_price' => $offer->price,
            'currency' => $offer->currency,
            'status' => ServiceJobStatus::Assigned->value,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn () => ['status' => ServiceJobStatus::InProgress->value]);
    }

    public function awaitingConfirmation(): static
    {
        return $this->state(fn () => [
            'status' => ServiceJobStatus::AwaitingConfirmation->value,
            'provider_completed_at' => now(),
            'auto_confirm_at' => now()->addDays(3),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => ServiceJobStatus::Completed->value,
            'provider_completed_at' => now()->subDay(),
            'customer_confirmed_at' => now(),
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => ServiceJobStatus::Cancelled->value,
            'cancelled_at' => now(),
        ]);
    }
}
