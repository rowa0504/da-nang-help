<?php

namespace App\Providers;

use App\Contracts\Translator;
use App\Services\Translation\AwsTranslateTranslator;
use App\Services\Translation\FakeTranslator;
use Aws\Translate\TranslateClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Only actually constructed if something resolves Translator::class
        // to AwsTranslateTranslator below — i.e. never in local/testing's
        // default TRANSLATOR_DRIVER=fake, so no AWS region/credential
        // resolution is ever attempted there.
        $this->app->singleton(TranslateClient::class, function () {
            return new TranslateClient([
                'version' => 'latest',
                'region' => config('services.translate.region'),
                // No explicit 'credentials' option: resolved via the AWS
                // SDK's default provider chain (an ECS Task Role in
                // production; a developer's exported IAM Identity
                // Center/STS temporary credentials for local manual
                // verification). An API key is never read here.
                'retries' => [
                    'mode' => 'standard',
                    // Total attempts including the first, per the SDK's
                    // own documented meaning of this option.
                    'max_attempts' => 3,
                ],
                'http' => [
                    // Kept short so that even 3 'retries' above cannot
                    // together exceed TranslateServiceRequestJob/
                    // TranslateOfferJob's own 60s $timeout.
                    'connect_timeout' => 5,
                    'timeout' => 10,
                ],
            ]);
        });

        $this->app->singleton(Translator::class, function () {
            return match (config('services.translate.driver')) {
                'fake' => new FakeTranslator(),
                'aws' => new AwsTranslateTranslator($this->app->make(TranslateClient::class)),
                default => throw new InvalidArgumentException(
                    'Unsupported TRANSLATOR_DRIVER value ['.var_export(config('services.translate.driver'), true)."]. Expected 'fake' or 'aws'."
                ),
            };
        });
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
