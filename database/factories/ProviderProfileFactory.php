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
     */
    public function forUser(User $user): static
    {
        return $this->afterMaking(function (ProviderProfile $profile) use ($user) {
            $profile->user_id = $user->id;
        });
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
