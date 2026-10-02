<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Category;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Covers the named rate limiters configured in AppServiceProvider
 * (guest-write, content-create, authenticated-write, locale-switch) and the
 * 429 response handling in bootstrap/app.php. Each limiter combines a short
 * burst Limit with a longer sustained Limit sharing the same underlying
 * counter-increment call (see ThrottleRequests::handleRequest — both Limits
 * in the array are hit() together on every allowed request), so the
 * sustained-limit tests below use time travel to let the burst window decay
 * between batches rather than firing requests fast enough to trip it.
 */
class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Mirrors ThrottleRequests::handleRequestUsingNamedLimiter's own key
     * construction (self::$shouldHashKeys defaults to true), so these tests
     * can explicitly clear the exact cache entries a limiter uses rather
     * than relying solely on the testing environment's array cache driver
     * resetting itself between tests.
     */
    private function limiterCacheKey(string $limiterName, string $byKey): string
    {
        return md5($limiterName.$byKey);
    }

    private function clearGuestWrite(string $ip): void
    {
        RateLimiter::clear($this->limiterCacheKey('guest-write', "guest-write:minute:ip:{$ip}"));
        RateLimiter::clear($this->limiterCacheKey('guest-write', "guest-write:hour:ip:{$ip}"));
    }

    private function clearContentCreate(int $userId): void
    {
        RateLimiter::clear($this->limiterCacheKey('content-create', "content-create:minute:user:{$userId}"));
        RateLimiter::clear($this->limiterCacheKey('content-create', "content-create:ten-minutes:user:{$userId}"));
    }

    private function clearAuthenticatedWrite(int $userId): void
    {
        RateLimiter::clear($this->limiterCacheKey('authenticated-write', "authenticated-write:minute:user:{$userId}"));
        RateLimiter::clear($this->limiterCacheKey('authenticated-write', "authenticated-write:ten-minutes:user:{$userId}"));
    }

    private function clearLocaleSwitch(string $key): void
    {
        RateLimiter::clear($this->limiterCacheKey('locale-switch', $key));
    }

    private function requestPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Fix my leaking AC',
            'description' => 'Water is dripping from the unit.',
            'category_id' => Category::factory()->create()->id,
            'area_id' => Area::factory()->create()->id,
            'address_text' => '123 Example Street',
            'urgency' => 'normal',
            'source_locale' => 'en',
        ], $overrides);
    }

    private function categoryPayload(array $overrides = []): array
    {
        return array_merge([
            'parent_id' => null,
            'is_active' => true,
            'sort_order' => 0,
            'names' => ['en' => 'Gardening', 'ja' => '庭仕事', 'vi' => 'Làm vườn'],
        ], $overrides);
    }

    // ---- guest-write (POST /register): perMinute(5), perHour(20) by IP ----

    public function test_guest_write_burst_limit_blocks_the_sixth_request_within_a_minute(): void
    {
        $this->clearGuestWrite('127.0.0.1');
        $usersBefore = User::count();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', [])->assertStatus(302);
        }

        $response = $this->post('/register', []);

        $response->assertStatus(429);
        $retryAfter = (int) $response->headers->get('Retry-After');
        $this->assertGreaterThan(0, $retryAfter);
        $this->assertLessThanOrEqual(60, $retryAfter);

        // The blocked 6th request never reached the controller at all
        // (ThrottleRequests throws before calling $next), so nothing it
        // might otherwise have done to the database happened.
        $this->assertSame($usersBefore, User::count());
    }

    public function test_guest_write_sustained_limit_blocks_after_twenty_requests_across_several_minutes(): void
    {
        $this->clearGuestWrite('127.0.0.1');

        // 4 batches of 5 (the burst max), each separated by >60s so the
        // per-minute Limit decays between batches while the per-hour Limit
        // (3600s decay) keeps accumulating — 20 total successful requests.
        for ($batch = 0; $batch < 4; $batch++) {
            for ($i = 0; $i < 5; $i++) {
                $this->post('/register', [])->assertStatus(302);
            }
            $this->travel(61)->seconds();
        }

        $response = $this->post('/register', []);

        $response->assertStatus(429);
        $retryAfter = (int) $response->headers->get('Retry-After');
        $this->assertGreaterThan(60, $retryAfter);
        $this->assertLessThanOrEqual(3600, $retryAfter);
    }

    public function test_guest_write_limiter_tracks_separate_budgets_per_ip(): void
    {
        $this->clearGuestWrite('10.0.0.1');
        $this->clearGuestWrite('10.0.0.2');

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1']);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', [])->assertStatus(302);
        }
        $this->post('/register', [])->assertStatus(429);

        // A different guest IP has its own, untouched budget.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2']);
        $this->post('/register', [])->assertStatus(302);
    }

    public function test_alb_style_x_forwarded_for_header_is_trusted_for_client_ip_resolution(): void
    {
        $albIp = '172.31.0.10';
        $this->clearGuestWrite('203.0.113.5');
        $this->clearGuestWrite('203.0.113.9');

        // Both "clients" arrive via the same ALB (same REMOTE_ADDR), only
        // distinguished by X-Forwarded-For — this only resolves to separate
        // budgets if trustProxies(at: '*', ...) in bootstrap/app.php is
        // actually wired up.
        $this->withServerVariables(['REMOTE_ADDR' => $albIp]);

        for ($i = 0; $i < 5; $i++) {
            $this->withHeaders(['X-Forwarded-For' => '203.0.113.5'])
                ->post('/register', [])
                ->assertStatus(302);
        }
        $this->withHeaders(['X-Forwarded-For' => '203.0.113.5'])
            ->post('/register', [])
            ->assertStatus(429);

        $this->withHeaders(['X-Forwarded-For' => '203.0.113.9'])
            ->post('/register', [])
            ->assertStatus(302);
    }

    // ---- content-create (POST /requests): perMinute(10), perMinutes(10, 20) by user ----

    public function test_content_create_burst_limit_blocks_the_eleventh_request_within_a_minute(): void
    {
        $customer = User::factory()->create();
        $this->clearContentCreate($customer->id);
        Bus::fake();
        $countBefore = ServiceRequest::count();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($customer)->post('/requests', $this->requestPayload())->assertRedirect();
        }
        $this->assertSame($countBefore + 10, ServiceRequest::count());
        Bus::assertDispatchedTimes(\App\Jobs\TranslateServiceRequestJob::class, 20);

        $response = $this->actingAs($customer)->post('/requests', $this->requestPayload());

        $response->assertStatus(429);
        $this->assertSame($countBefore + 10, ServiceRequest::count());
        Bus::assertDispatchedTimes(\App\Jobs\TranslateServiceRequestJob::class, 20);
    }

    public function test_content_create_sustained_limit_blocks_after_twenty_requests_across_minutes(): void
    {
        $customer = User::factory()->create();
        $this->clearContentCreate($customer->id);

        for ($batch = 0; $batch < 2; $batch++) {
            for ($i = 0; $i < 10; $i++) {
                $this->actingAs($customer)->post('/requests', $this->requestPayload())->assertRedirect();
            }
            $this->travel(61)->seconds();
        }

        $response = $this->actingAs($customer)->post('/requests', $this->requestPayload());

        $response->assertStatus(429);
        $retryAfter = (int) $response->headers->get('Retry-After');
        $this->assertGreaterThan(60, $retryAfter);
        $this->assertLessThanOrEqual(600, $retryAfter);
    }

    // ---- authenticated-write (PATCH /admin/categories/{id}): perMinute(30), perMinutes(10, 60) by user ----

    public function test_authenticated_write_burst_limit_blocks_the_thirty_first_request_within_a_minute(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        $this->clearAuthenticatedWrite($admin->id);

        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($admin)
                ->patch("/admin/categories/{$category->id}", $this->categoryPayload())
                ->assertRedirect();
        }

        $response = $this->actingAs($admin)->patch("/admin/categories/{$category->id}", $this->categoryPayload());

        $response->assertStatus(429);
    }

    public function test_authenticated_write_sustained_limit_blocks_after_sixty_requests_across_minutes(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        $this->clearAuthenticatedWrite($admin->id);

        for ($batch = 0; $batch < 2; $batch++) {
            for ($i = 0; $i < 30; $i++) {
                $this->actingAs($admin)
                    ->patch("/admin/categories/{$category->id}", $this->categoryPayload())
                    ->assertRedirect();
            }
            $this->travel(61)->seconds();
        }

        $response = $this->actingAs($admin)->patch("/admin/categories/{$category->id}", $this->categoryPayload());

        $response->assertStatus(429);
        $retryAfter = (int) $response->headers->get('Retry-After');
        $this->assertGreaterThan(60, $retryAfter);
        $this->assertLessThanOrEqual(600, $retryAfter);
    }

    public function test_authenticated_write_limiter_tracks_separate_budgets_per_user(): void
    {
        $adminA = User::factory()->admin()->create();
        $adminB = User::factory()->admin()->create();
        $category = Category::factory()->create();
        $this->clearAuthenticatedWrite($adminA->id);
        $this->clearAuthenticatedWrite($adminB->id);

        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($adminA)
                ->patch("/admin/categories/{$category->id}", $this->categoryPayload())
                ->assertRedirect();
        }
        $this->actingAs($adminA)->patch("/admin/categories/{$category->id}", $this->categoryPayload())->assertStatus(429);

        // A different admin's budget is untouched by adminA's usage.
        $this->actingAs($adminB)->patch("/admin/categories/{$category->id}", $this->categoryPayload())->assertRedirect();
    }

    // ---- locale-switch (PATCH /locale): its own budget, separate from authenticated-write ----

    public function test_locale_switch_has_an_independent_budget_from_authenticated_write(): void
    {
        $user = User::factory()->create();
        $this->clearLocaleSwitch("locale-switch:user:{$user->id}");
        $this->clearAuthenticatedWrite($user->id);

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->patch('/locale', ['locale' => 'ja'])->assertRedirect();
            $this->actingAs($user)->patch('/locale', ['locale' => 'en'])->assertRedirect();
        }

        // 20 locale switches made; the first-ever authenticated-write hit
        // for this user should still report a fresh 29-of-30 remaining,
        // proving the two limiters never shared a counter.
        $response = $this->actingAs($user)->patch('/locale', ['locale' => 'vi']);
        $response->assertRedirect();

        $admin = $user;
        $admin->role = \App\Enums\UserRole::Admin;
        $admin->save();
        $category = Category::factory()->create();

        $authWriteResponse = $this->actingAs($admin)->patch("/admin/categories/{$category->id}", $this->categoryPayload());
        $authWriteResponse->assertRedirect();
        $this->assertSame('29', $authWriteResponse->headers->get('X-RateLimit-Remaining'));
    }

    public function test_guest_may_switch_locale_without_an_authenticated_write_budget(): void
    {
        $this->clearLocaleSwitch('locale-switch:ip:127.0.0.1');

        for ($i = 0; $i < 29; $i++) {
            $this->patch('/locale', ['locale' => $i % 2 === 0 ? 'ja' : 'en'])->assertRedirect();
        }

        $response = $this->patch('/locale', ['locale' => 'vi']);
        $response->assertRedirect();
    }

    // ---- 429 response shape: Inertia / plain HTML / JSON ----

    public function test_inertia_request_receives_the_error_page_component_on_429(): void
    {
        $this->clearGuestWrite('127.0.0.1');
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', [])->assertStatus(302);
        }

        $response = $this->withHeaders(['X-Inertia' => 'true'])->post('/register', []);

        $response->assertStatus(429);
        $response->assertHeader('X-Inertia', 'true');
        $this->assertNotNull($response->headers->get('Retry-After'));
        $response->assertJson(['component' => 'Error']);
        $this->assertSame(429, $response->json('props.status'));
        $this->assertIsString($response->json('props.message'));
        $this->assertNotSame('', $response->json('props.message'));
    }

    public function test_plain_html_request_receives_the_blade_429_page(): void
    {
        $this->clearGuestWrite('127.0.0.1');
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', [])->assertStatus(302);
        }

        $response = $this->post('/register', []);

        $response->assertStatus(429);
        $this->assertNotNull($response->headers->get('Retry-After'));
        $response->assertSee(__('messages.rate_limited_title'));
    }

    public function test_json_request_receives_the_default_json_429_response(): void
    {
        $this->clearGuestWrite('127.0.0.1');
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/register', [])->assertStatus(422);
        }

        $response = $this->postJson('/register', []);

        $response->assertStatus(429);
        $this->assertNotNull($response->headers->get('Retry-After'));
        $response->assertJsonStructure(['message']);
    }

    public function test_non_throttle_exceptions_are_not_altered_by_the_429_responder(): void
    {
        $owner = User::factory()->create();
        $otherCustomer = User::factory()->create();
        // status defaults to Open via ServiceRequestFactory::configure().
        $serviceRequest = ServiceRequest::factory()->forCustomer($owner)->create();

        // A policy-denied action from an unrelated Customer: an ordinary
        // 403, nothing to do with rate limiting — must come back untouched.
        $response = $this->actingAs($otherCustomer)->patch("/requests/{$serviceRequest->id}/cancel");

        $response->assertStatus(403);
    }
}
