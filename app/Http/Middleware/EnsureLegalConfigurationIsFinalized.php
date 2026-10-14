<?php

namespace App\Http\Middleware;

use App\Support\Legal\LegalConfigurationValidator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runtime, defense-in-depth gate on the register/terms/privacy routes: if
 * `php artisan legal:check` was somehow skipped (or passed against a
 * different config than what actually shipped), this stops unfinalized
 * legal text — and the registration consent that references it — from
 * ever being served to a real visitor in production.
 *
 * local/testing always pass through untouched: a developer must be able to
 * work with the committed placeholder operator name/contact email without
 * this ever tripping.
 */
class EnsureLegalConfigurationIsFinalized
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('production')) {
            return $next($request);
        }

        if (! app(LegalConfigurationValidator::class)->passes()) {
            // No message argument: Laravel's bundled errors::503 view
            // ignores it anyway (it always renders the static "Service
            // Unavailable" string, confirmed by reading that view) — but
            // omitting it here removes any risk that a future custom
            // errors/503.blade.php would start echoing $exception
            // ->getMessage() and leak which config keys/problems tripped
            // this. The detailed problem list is for `php artisan
            // legal:check` (an operator-only CLI command) and application
            // logs, never for an HTTP response body.
            report(new \RuntimeException(
                'Blocked request to a legal-config-gated route: '.implode('; ', app(LegalConfigurationValidator::class)->problems())
            ));

            abort(503);
        }

        return $next($request);
    }
}
