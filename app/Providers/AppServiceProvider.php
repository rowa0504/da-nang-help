<?php

namespace App\Providers;

use App\Contracts\Translator;
use App\Services\Translation\FakeTranslator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // FakeTranslator is local/testing-only (see its docblock). Swap
        // this binding for a real Amazon Translate implementation before
        // going to production.
        $this->app->bind(Translator::class, FakeTranslator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Named write-endpoint limiters (see routes/*.php for which routes use
     * which). Login has its own, separate, tighter email+IP mechanism
     * (LoginRequest) and is untouched here.
     *
     * Each tier combines a short burst limit with a longer sustained limit
     * — two separate Limit instances, each keyed with its own time-window
     * prefix. Without the distinct prefix, a burst that trips the 1-minute
     * limit would share its hit-count with the 10-minute/hourly limit's
     * key, under-counting the longer window once the short one starts
     * rejecting requests (and making the longer window effectively
     * untestable in isolation).
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('guest-write', function (Request $request) {
            $ip = $request->ip();

            return [
                Limit::perMinute(5)->by("guest-write:minute:ip:{$ip}"),
                Limit::perHour(20)->by("guest-write:hour:ip:{$ip}"),
            ];
        });

        RateLimiter::for('content-create', function (Request $request) {
            $userId = $request->user()->id;

            return [
                Limit::perMinute(10)->by("content-create:minute:user:{$userId}"),
                Limit::perMinutes(10, 20)->by("content-create:ten-minutes:user:{$userId}"),
            ];
        });

        RateLimiter::for('authenticated-write', function (Request $request) {
            $userId = $request->user()->id;

            return [
                Limit::perMinute(30)->by("authenticated-write:minute:user:{$userId}"),
                Limit::perMinutes(10, 60)->by("authenticated-write:ten-minutes:user:{$userId}"),
            ];
        });

        // The only write-ish route guests may hit (PATCH /locale has no
        // 'auth' middleware) — kept separate from authenticated-write so
        // switching languages a few times never eats into the budget for
        // offer/job/request actions.
        RateLimiter::for('locale-switch', function (Request $request) {
            $key = $request->user() !== null
                ? "locale-switch:user:{$request->user()->id}"
                : "locale-switch:ip:{$request->ip()}";

            return Limit::perMinute(30)->by($key);
        });
    }
}
