<?php

namespace Tests\Unit;

use App\Actions\Review\RecalculateProviderRatingAction;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\ServiceJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecalculateProviderRatingActionTest extends TestCase
{
    use RefreshDatabase;

    private function providerProfileFor(ServiceJob $job): ProviderProfile
    {
        return ProviderProfile::factory()->forUser($job->provider)->approved()->create();
    }

    public function test_average_excludes_a_hidden_review(): void
    {
        $provider = \App\Models\User::factory()->provider()->create();
        $profile = ProviderProfile::factory()->forUser($provider)->approved()->create();

        $jobA = ServiceJob::factory()->completed()->create(['provider_id' => $provider->id]);
        $jobB = ServiceJob::factory()->completed()->create(['provider_id' => $provider->id]);
        Review::factory()->forJob($jobA)->create(['rating' => 5]);
        $hidden = Review::factory()->forJob($jobB)->create(['rating' => 1]);

        app(RecalculateProviderRatingAction::class)->handle($profile->fresh());
        $this->assertSame('3.00', $profile->fresh()->avg_rating);

        // is_hidden/hidden_at are not #[Fillable] on Review, so update()
        // would silently no-op them — assign directly instead.
        $hidden->is_hidden = true;
        $hidden->hidden_at = now();
        $hidden->save();
        app(RecalculateProviderRatingAction::class)->handle($profile->fresh());

        $this->assertSame('5.00', $profile->fresh()->avg_rating);
    }

    public function test_average_resets_to_zero_when_all_reviews_are_hidden(): void
    {
        $job = ServiceJob::factory()->completed()->create();
        $profile = $this->providerProfileFor($job);
        Review::factory()->forJob($job)->hidden()->create(['rating' => 4]);

        app(RecalculateProviderRatingAction::class)->handle($profile->fresh());

        $this->assertSame('0.00', $profile->fresh()->avg_rating);
    }

    public function test_completed_jobs_count_is_never_touched(): void
    {
        $job = ServiceJob::factory()->completed()->create();
        $profile = $this->providerProfileFor($job);
        // completed_jobs_count is not #[Fillable] on ProviderProfile either.
        $profile->completed_jobs_count = 7;
        $profile->save();
        Review::factory()->forJob($job)->create(['rating' => 2]);

        app(RecalculateProviderRatingAction::class)->handle($profile->fresh());

        $this->assertSame(7, $profile->fresh()->completed_jobs_count);
    }
}
