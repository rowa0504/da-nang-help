<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_switch_locale_via_session(): void
    {
        $response = $this->patch('/locale', ['locale' => 'ja']);

        $response->assertRedirect();
        $this->assertSame('ja', session('locale'));
    }

    public function test_authenticated_user_switching_locale_persists_it_to_the_database(): void
    {
        $user = User::factory()->create();
        $user->locale = 'en';
        $user->save();

        $this->actingAs($user)->patch('/locale', ['locale' => 'ja'])->assertRedirect();

        // Explicit DB-level check — locale is not #[Fillable] on User, so a
        // regression back to update()/fill() would silently no-op this and
        // this assertion is what would catch it (see Phase 7's identical
        // completed_jobs_count/is_hidden pitfall).
        $this->assertDatabaseHas('users', ['id' => $user->id, 'locale' => 'ja']);
        $this->assertSame('ja', $user->fresh()->locale);
    }

    public function test_switching_locale_is_reflected_on_the_very_next_request(): void
    {
        $user = User::factory()->create();
        $user->locale = 'en';
        $user->save();

        $this->actingAs($user)->patch('/locale', ['locale' => 'vi'])->assertRedirect();
        $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->assertSame('vi', App::getLocale());
    }

    public function test_unsupported_locale_value_is_rejected(): void
    {
        $user = User::factory()->create();
        $user->locale = 'en';
        $user->save();

        $this->actingAs($user)->patch('/locale', ['locale' => 'fr'])->assertInvalid(['locale']);

        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_missing_locale_value_is_rejected(): void
    {
        $this->patch('/locale', [])->assertInvalid(['locale']);
    }
}
