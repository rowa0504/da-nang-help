<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderProfileFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_specifying_a_user_does_not_create_an_extra_user(): void
    {
        $provider = User::factory()->provider()->create();
        $countBeforeProfile = User::count();

        ProviderProfile::factory()->forUser($provider)->create();

        $this->assertSame($countBeforeProfile, User::count());
    }

    public function test_specified_user_id_is_used(): void
    {
        $provider = User::factory()->provider()->create();

        $profile = ProviderProfile::factory()->forUser($provider)->create();

        $this->assertSame($provider->id, $profile->user_id);
    }

    public function test_unspecified_user_creates_exactly_one_provider_role_user(): void
    {
        $countBefore = User::count();

        $profile = ProviderProfile::factory()->create();

        $this->assertSame($countBefore + 1, User::count());
        $this->assertSame(UserRole::Provider, $profile->user->role);
    }
}
