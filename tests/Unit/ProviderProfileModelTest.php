<?php

namespace Tests\Unit;

use App\Enums\ProviderVerificationStatus;
use App\Models\Area;
use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderProfileModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_id_is_unique_one_profile_per_user(): void
    {
        $user = User::factory()->provider()->create();
        ProviderProfile::factory()->forUser($user)->create();

        $this->expectException(QueryException::class);
        ProviderProfile::factory()->forUser($user)->create();
    }

    public function test_customer_cannot_be_authorized_to_create_a_profile(): void
    {
        $customer = User::factory()->create();

        $this->assertFalse($customer->can('create', ProviderProfile::class));
    }

    public function test_admin_cannot_be_authorized_to_create_a_profile(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertFalse($admin->can('create', ProviderProfile::class));
    }

    public function test_provider_role_is_authorized_to_create_a_profile(): void
    {
        $provider = User::factory()->provider()->create();

        $this->assertTrue($provider->can('create', ProviderProfile::class));
    }

    public function test_provider_categories_pivot_is_unique(): void
    {
        $profile = ProviderProfile::factory()->create();
        $category = Category::factory()->create();

        $profile->categories()->attach($category);

        $this->expectException(QueryException::class);
        $profile->categories()->attach($category);
    }

    public function test_provider_areas_pivot_is_unique(): void
    {
        $profile = ProviderProfile::factory()->create();
        $area = Area::factory()->create();

        $profile->areas()->attach($area);

        $this->expectException(QueryException::class);
        $profile->areas()->attach($area);
    }

    public function test_verification_status_casts_to_enum(): void
    {
        $profile = ProviderProfile::factory()->approved()->create();

        $this->assertSame(ProviderVerificationStatus::Approved, $profile->fresh()->verification_status);
    }

    public function test_avg_rating_and_completed_jobs_count_default_to_zero(): void
    {
        $profile = ProviderProfile::factory()->create();
        $fresh = $profile->fresh();

        $this->assertEquals(0, $fresh->avg_rating);
        $this->assertSame(0, $fresh->completed_jobs_count);
    }

    public function test_relations_resolve(): void
    {
        $user = User::factory()->provider()->create();
        $profile = ProviderProfile::factory()->forUser($user)->create();
        $category = Category::factory()->create();
        $area = Area::factory()->create();
        $profile->categories()->attach($category);
        $profile->areas()->attach($area);

        $this->assertTrue($profile->user->is($user));
        $this->assertTrue($user->fresh()->providerProfile->is($profile));
        $this->assertTrue($profile->categories->contains($category));
        $this->assertTrue($profile->areas->contains($area));
    }

    public function test_factory_fillable_excluded_attributes_persist_after_fresh(): void
    {
        $profile = ProviderProfile::factory()->rejected()->create();
        $fresh = $profile->fresh();

        $this->assertNotNull($fresh->user_id);
        $this->assertSame(ProviderVerificationStatus::Rejected, $fresh->verification_status);
        $this->assertNotNull($fresh->rejected_at);
    }

    public function test_verification_note_is_excluded_from_array_serialization(): void
    {
        $profile = ProviderProfile::factory()->create();
        $profile->verification_note = 'Internal-only note';
        $profile->save();

        $this->assertArrayNotHasKey('verification_note', $profile->fresh()->toArray());
    }
}
