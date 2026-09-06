<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

/**
 * Exercises App\Http\Middleware\SetLocale through an actual HTTP request
 * (it is registered in bootstrap/app.php's web group ahead of every route,
 * including '/'), rather than constructing Request/Session objects by
 * hand — this keeps the test aligned with how the middleware is actually
 * invoked in production.
 */
class SetLocaleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_locale_takes_priority_over_session(): void
    {
        $user = User::factory()->create();
        $user->locale = 'ja';
        $user->save();

        $this->withSession(['locale' => 'vi'])->actingAs($user)->get('/')->assertOk();

        $this->assertSame('ja', App::getLocale());
    }

    public function test_guest_falls_back_to_session_locale(): void
    {
        $this->withSession(['locale' => 'vi'])->get('/')->assertOk();

        $this->assertSame('vi', App::getLocale());
    }

    public function test_guest_with_no_session_locale_falls_back_to_config_fallback_locale(): void
    {
        config(['app.fallback_locale' => 'ja']);

        $this->get('/')->assertOk();

        $this->assertSame('ja', App::getLocale());
    }

    public function test_unsupported_config_fallback_locale_is_coerced_to_en(): void
    {
        config(['app.fallback_locale' => 'fr']);

        $this->get('/')->assertOk();

        $this->assertSame('en', App::getLocale());
    }

    public function test_unsupported_users_locale_is_coerced_to_en(): void
    {
        // Defensive: users.locale is guarded by LocaleController's
        // Rule::in(['en','ja','vi']), but the middleware must not trust
        // that guarantee blindly (e.g. a value set by another process).
        $user = User::factory()->create();
        $user->locale = 'fr';
        $user->save();

        $this->actingAs($user)->get('/')->assertOk();

        $this->assertSame('en', App::getLocale());
    }

    public function test_resolved_locale_is_written_back_to_the_session(): void
    {
        $user = User::factory()->create();
        $user->locale = 'ja';
        $user->save();

        $this->actingAs($user)->get('/')->assertOk();

        $this->assertSame('ja', session('locale'));
    }
}
