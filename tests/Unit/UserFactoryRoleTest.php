<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Confirms that the `role` attribute set via UserFactory's afterMaking
 * callbacks (see database/factories/UserFactory.php — `role` is deliberately
 * excluded from User's Fillable, so it cannot flow through the definition()
 * array via mass assignment) is actually persisted to the database, not
 * just held in memory on the unsaved model instance.
 */
class UserFactoryRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_factory_persists_customer_role(): void
    {
        $user = User::factory()->create();

        $this->assertSame(UserRole::Customer, $user->fresh()->role);
    }

    public function test_provider_state_persists_provider_role(): void
    {
        $user = User::factory()->provider()->create();

        $this->assertSame(UserRole::Provider, $user->fresh()->role);
    }

    public function test_admin_state_persists_admin_role(): void
    {
        $user = User::factory()->admin()->create();

        $this->assertSame(UserRole::Admin, $user->fresh()->role);
    }
}
