<?php

namespace Database\Factories;

use App\Models\Review;
use App\Models\ServiceJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            // Deliberately a lazy Factory instance, not ->create(): when
            // forJob() overrides 'job_id' via for(), this value is never
            // evaluated, so no throwaway ServiceJob gets created (same
            // pattern as OfferFactory's 'provider_id' => User::factory()).
            'job_id' => ServiceJob::factory()->completed(),
            'rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->sentence(),
        ];
    }

    /**
     * rater_id/ratee_id depend on job_id, which is only guaranteed to be
     * its final value (whether from the default factory relation above or
     * from forJob()'s for() override) once attribute resolution + state
     * merging is complete — i.e. by the time afterMaking runs. Mirrors
     * OfferFactory::configure()'s afterMaking timing.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Review $review) {
            $job = ServiceJob::findOrFail($review->job_id);
            $review->rater_id ??= $job->customer_id;
            $review->ratee_id ??= $job->provider_id;
        });
    }

    public function forJob(ServiceJob $job): static
    {
        return $this->for($job, 'job');
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['is_hidden' => true, 'hidden_at' => now()]);
    }
}
