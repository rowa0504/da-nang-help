<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertInvalid('email');
        $this->assertGuest();
    }

    public function test_session_is_regenerated_after_login(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $this->get('/');
        $idBefore = session()->getId();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $this->assertNotSame($idBefore, session()->getId());
    }

    public function test_authenticated_user_is_logged_out_via_post(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_get_logout_route_does_not_exist(): void
    {
        // /logout is registered as POST-only, so GET correctly resolves to
        // "405 Method Not Allowed" (the path exists, the verb doesn't) —
        // there is no GET handler that would log the user out.
        $user = User::factory()->create();

        $this->actingAs($user)->get('/logout')->assertMethodNotAllowed();
    }

    public function test_session_is_invalidated_after_logout(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->post('/logout');

        // The now-guest session must no longer grant access to /dashboard.
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_login_is_rate_limited_after_too_many_attempts(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertInvalid('email');
        $this->assertGuest();
    }

    public function test_successful_login_clears_the_rate_limiter(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $key = mb_strtolower($user->email).'|127.0.0.1';
        $this->assertSame(0, RateLimiter::attempts($key));
    }

    public function test_rate_limit_key_is_case_insensitive_on_email(): void
    {
        $user = User::factory()->create(['email' => 'Jane@Example.com', 'password' => 'password123']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'JANE@EXAMPLE.COM',
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->post('/login', [
            'email' => 'jane@example.com',
            'password' => 'password123',
        ]);

        $response->assertInvalid('email');
        $this->assertGuest();
    }

    public function test_guest_is_redirected_away_from_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_authenticated_user_is_redirected_away_from_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/login')->assertRedirect(route('dashboard'));
    }

    public function test_guest_has_null_shared_auth_user(): void
    {
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('auth.user', null));
    }

    public function test_authenticated_shared_props_do_not_expose_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')->assertInertia(
            fn (Assert $page) => $page
                ->where('auth.user.id', $user->id)
                ->missing('auth.user.password')
                ->missing('auth.user.remember_token')
        );
    }
}
