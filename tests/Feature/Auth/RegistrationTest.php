<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_empty_string_phone_is_accepted(): void
    {
        $response = $this->post('/register', $this->validPayload(['phone' => '']));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validPhoneProvider(): array
    {
        return [
            'domestic format' => ['0901234567'],
            'international with spaces' => ['+84 90 123 4567'],
            'leading plus with parens and hyphens' => ['+84 (90) 123-4567'],
            'lower boundary: exactly 7 digits' => ['1234567'],
            'upper boundary: exactly 15 digits' => ['123456789012345'],
        ];
    }

    #[DataProvider('validPhoneProvider')]
    public function test_valid_phone_formats_are_accepted(string $phone): void
    {
        $email = 'phone-valid-'.md5($phone).'@example.com';

        $response = $this->post('/register', $this->validPayload([
            'email' => $email,
            'phone' => $phone,
        ]));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $user = User::firstWhere('email', $email);
        $this->assertSame($phone, $user->phone);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidPhoneProvider(): array
    {
        return [
            'below minimum: 6 digits' => ['123456'],
            'above maximum: 16 digits' => ['1234567890123456'],
            'plus in the middle' => ['090-123-456+7'],
            'multiple plus signs' => ['++84901234567'],
            'letters' => ['090abc4567'],
            'full-width digits' => ['０９０１２３４５６７'],
            'emoji' => ["090\u{1F4DE}1234567"],
            'tab character' => ["090\t1234567"],
            'newline character' => ["090\n1234567"],
            'disallowed symbol' => ['090!1234567'],
        ];
    }

    #[DataProvider('invalidPhoneProvider')]
    public function test_invalid_phone_formats_are_rejected(string $phone): void
    {
        $response = $this->post('/register', $this->validPayload([
            'email' => 'phone-invalid-'.md5($phone).'@example.com',
            'phone' => $phone,
        ]));

        $response->assertInvalid('phone');
        $this->assertGuest();
    }

    public function test_session_is_regenerated_after_registration(): void
    {
        $this->get('/');
        $idBefore = session()->getId();

        $this->post('/register', $this->validPayload());

        $this->assertNotSame($idBefore, session()->getId());
    }
}
