<?php

namespace Database\Seeders;

use App\Enums\ProviderVerificationStatus;
use App\Models\Area;
use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Attaches a `pending` provider_profiles row to DemoUserSeeder's
 * `provider@example.test`, for manually exercising the Admin approve/reject
 * flow locally. Depends on CategorySeeder/AreaSeeder/DemoUserSeeder having
 * already run.
 *
 * Idempotent: re-running updates the same row (matched by user_id) rather
 * than creating a duplicate, and uses syncWithoutDetaching() for the
 * category/area pivots.
 */
class DemoProviderProfileSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $provider = User::query()->where('email', 'provider@example.test')->first();
        $category = Category::query()->where('slug', 'aircon-repair')->first();
        $area = Area::query()->where('slug', 'hai-chau')->first();

        if (! $provider || ! $category || ! $area) {
            return;
        }

        // ProviderProfile::updateOrCreate() cannot be used here: `user_id`
        // is not in the model's Fillable attribute, so it would be silently
        // dropped on the create() path. Find-then-assign explicitly instead
        // (same reasoning as SubmitProviderProfileAction).
        $profile = ProviderProfile::query()->where('user_id', $provider->id)->first() ?? new ProviderProfile();
        $profile->user_id = $provider->id;
        $profile->business_name = 'Demo Provider Co.';
        $profile->bio = 'A demo provider profile for exercising the Admin review flow locally.';
        $profile->verification_status ??= ProviderVerificationStatus::Pending;
        $profile->save();

        $profile->categories()->syncWithoutDetaching([$category->id]);
        $profile->areas()->syncWithoutDetaching([$area->id]);
    }
}
