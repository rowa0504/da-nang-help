<?php

namespace Database\Factories;

use App\Enums\OfferStatus;
use App\Models\Offer;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'service_request_id' => ServiceRequest::factory(),
            'provider_id' => User::factory()->provider(),
            'price' => fake()->randomFloat(2, 10, 500),
            'currency' => 'USD',
            'message' => fake()->paragraph(),
            'source_locale' => 'en',
            'available_at' => null,
        ];
    }

    /**
     * `status` is excluded from the model's Fillable attribute, so it is
     * assigned directly on the instance here rather than through the
     * definition() array.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Offer $offer) {
            $offer->status ??= OfferStatus::Pending;
        });
    }

    /**
     * Attach this offer to a specific, already-created Service Request.
     *
     * Uses for() (not an afterMaking() override) so definition()'s default
     * ServiceRequest::factory() is never even evaluated for this field,
     * avoiding the throwaway-row problem found in Phase 4's
     * ProviderProfileFactory/ServiceRequestFactory.
     */
    public function forServiceRequest(ServiceRequest $serviceRequest): static
    {
        return $this->for($serviceRequest, 'serviceRequest');
    }

    /**
     * Attach this offer to a specific, already-created Provider user.
     */
    public function forProvider(User $provider): static
    {
        return $this->for($provider, 'provider');
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['status' => OfferStatus::Accepted]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => OfferStatus::Rejected]);
    }

    public function withdrawn(): static
    {
        return $this->afterMaking(function (Offer $offer) {
            $offer->status = OfferStatus::Withdrawn;
            $offer->withdrawn_at = now();
        });
    }

    public function cancelled(): static
    {
        return $this->afterMaking(function (Offer $offer) {
            $offer->status = OfferStatus::Cancelled;
            $offer->cancelled_at = now();
        });
    }
}
