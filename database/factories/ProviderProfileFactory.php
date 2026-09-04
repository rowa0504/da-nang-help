<?php

namespace Database\Factories;

use App\Enums\ProviderVerificationStatus;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderProfile>
 */
class ProviderProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_name' => fake()->company(),
            'bio' => fake()->optional()->paragraph(),
        ];
    }

    /**
     * `user_id`/`verification_status`/`avg_rating`/`completed_jobs_count` are
     * excluded from the model's Fillable attribute (see App\Models\ProviderProfile),
     * so — as with UserFactory's `role` — they are assigned directly on the
     * instance here rather than through the definition() array.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (ProviderProfile $profile) {
            $profile->user_id ??= User::factory()->provider()->create()->id;
            $profile->verification_status ??= ProviderVerificationStatus::Pending;
            $profile->avg_rating ??= 0;
            $profile->completed_jobs_count ??= 0;
        });
    }

    /**
     * Attach this profile to a specific, already-created Provider user
     * instead of the random one `configure()` creates by default.
     *
     * Uses Laravel's for() rather than an afterMaking() override: for()
     * resolves during raw-attribute building, which always completes before
     * afterMaking() runs, so configure()'s `user_id ??= ...` correctly sees
     * user_id already set and skips creating a throwaway default user. An
     * afterMaking()-based override would still be evaluated *after*
     * configure()'s default-creation callback (afterMaking callbacks fire
     * in registration order, and configure() always registers first), so
     * the default user would be created and then immediately discarded on
     * every call regardless of chaining order.
     *
     * Does not validate that $user actually has the Provider role; callers
     * are responsible for passing a suitable User.
     */
    public function forUser(User $user): static
    {
        return $this->for($user, 'user');
    }

    public function approved(): static
    {
        return $this->afterMaking(function (ProviderProfile $profile) {
            $profile->verification_status = ProviderVerificationStatus::Approved;
            $profile->approved_at = now();
            $profile->rejected_at = null;
        });
    }

    public function rejected(): static
    {
        return $this->afterMaking(function (ProviderProfile $profile) {
            $profile->verification_status = ProviderVerificationStatus::Rejected;
            $profile->rejected_at = now();
        });
    }
}
