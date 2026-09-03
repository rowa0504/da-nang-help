<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => null,
            'role' => 'customer',
        ], $overrides);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get('/register')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth/Register'));
    }

    public function test_customer_can_register(): void
    {
        $response = $this->post('/register', $this->validPayload(['role' => 'customer']));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $user = User::firstWhere('email', 'jane@example.com');
        $this->assertNotNull($user);
        $this->assertSame(UserRole::Customer, $user->role);
    }

    public function test_provider_can_register(): void
    {
        $response = $this->post('/register', $this->validPayload([
            'email' => 'provider@example.com',
            'role' => 'provider',
        ]));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $user = User::firstWhere('email', 'provider@example.com');
        $this->assertSame(UserRole::Provider, $user->role);
    }

    public function test_registering_as_admin_is_rejected(): void
    {
        $response = $this->post('/register', $this->validPayload(['role' => 'admin']));

        $response->assertInvalid('role');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);
    }

    public function test_role_is_required(): void
    {
        $payload = $this->validPayload();
        unset($payload['role']);

        $response = $this->post('/register', $payload);

        $response->assertInvalid('role');
        $this->assertGuest();
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $response = $this->post('/register', $this->validPayload());

        $response->assertInvalid('email');
    }

    public function test_session_is_regenerated_after_registration(): void
    {
        $this->get('/');
        $idBefore = session()->getId();

        $this->post('/register', $this->validPayload());

        $this->assertNotSame($idBefore, session()->getId());
    }
}
