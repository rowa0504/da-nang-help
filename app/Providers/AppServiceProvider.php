<?php

namespace App\Providers;

use App\Contracts\Translator;
use App\Services\Translation\FakeTranslator;
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
        //
    }
}
