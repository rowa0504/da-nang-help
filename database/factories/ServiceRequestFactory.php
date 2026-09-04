<?php

namespace Database\Factories;

use App\Enums\ServiceRequestModerationStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\Area;
use App\Models\Category;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRequest>
 */
class ServiceRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'area_id' => Area::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'source_locale' => 'en',
            'address_text' => fake()->address(),
            'lat' => fake()->latitude(-90, 90),
            'lng' => fake()->longitude(-180, 180),
            'urgency' => 'normal',
        ];
    }

    /**
     * `customer_id`/`status`/`moderation_status` are excluded from the
     * model's Fillable attribute, so — as with ProviderProfileFactory — they
     * are assigned directly on the instance here rather than through the
     * definition() array.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (ServiceRequest $serviceRequest) {
            $serviceRequest->customer_id ??= User::factory()->create()->id;
            $serviceRequest->status ??= ServiceRequestStatus::Open;
            $serviceRequest->moderation_status ??= ServiceRequestModerationStatus::Visible;
        });
    }

    /**
     * Attach this request to a specific, already-created Customer instead of
     * the random one `configure()` creates by default.
     *
     * Uses Laravel's for() rather than an afterMaking() override: for()
     * resolves during raw-attribute building, which always completes before
     * afterMaking() runs, so configure()'s `customer_id ??= ...` correctly
     * sees customer_id already set and skips creating a throwaway default
     * customer. An afterMaking()-based override would still be evaluated
     * *after* configure()'s default-creation callback (afterMaking
     * callbacks fire in registration order, and configure() always
     * registers first), so the default customer would be created and then
     * immediately discarded on every call regardless of chaining order.
     *
     * Does not validate that $customer actually has the Customer role;
     * callers are responsible for passing a suitable User (mirrors
     * ProviderProfileFactory::forUser()).
     */
    public function forCustomer(User $customer): static
    {
        return $this->for($customer, 'customer');
    }

    public function cancelled(): static
    {
        return $this->afterMaking(function (ServiceRequest $serviceRequest) {
            $serviceRequest->status = ServiceRequestStatus::Cancelled;
            $serviceRequest->cancelled_at = now();
        });
    }

    public function hidden(): static
    {
        return $this->afterMaking(function (ServiceRequest $serviceRequest) {
            $serviceRequest->moderation_status = ServiceRequestModerationStatus::Hidden;
        });
    }
}
