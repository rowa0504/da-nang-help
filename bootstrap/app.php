<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);

        // Stage 1 puts an ALB directly in front of every Task, and the
        // Task's own Security Group accepts inbound traffic only from the
        // ALB (see RELEASE_PLAN.md 4.2/4.6) — that network-level isolation
        // is the actual trust boundary, not this IP allow-list (ALB IPs are
        // dynamic and can't be pinned). Without this, $request->ip() would
        // resolve to the ALB's own IP for every visitor in production,
        // collapsing all guests into a single IP-based rate-limit bucket.
        // Harmless locally too: the dev nginx never sends X-Forwarded-For,
        // so there is nothing here for an untrusted header to spoof.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_AWS_ELB);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Only a rate-limit rejection from our own named limiters (see
        // AppServiceProvider) is redirected through this branch — any other
        // source of a 429 (e.g. a future abort(429) elsewhere) falls
        // through unchanged, since instanceof is checked first.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if (! $e instanceof ThrottleRequestsException || $response->getStatusCode() !== 429) {
                return $response;
            }

            // Laravel's ThrottleRequests middleware always sets this header
            // on the response it builds, but never trust an external value
            // blindly — clamp to a sane positive minimum.
            $retryAfter = max(1, (int) ($response->headers->get('Retry-After') ?? 60));
            $message = __('messages.rate_limited_body', ['seconds' => $retryAfter]);

            // A plain truthiness check on header() would also match an
            // explicit "X-Inertia: false", which should not happen in
            // practice but hasHeader() is the unambiguous check either way.
            if ($request->hasHeader('X-Inertia')) {
                return Inertia::render('Error', [
                    'status' => 429,
                    'message' => $message,
                    'retryAfter' => $retryAfter,
                ])->toResponse($request)
                    ->setStatusCode(429)
                    ->header('Retry-After', (string) $retryAfter);
            }

            // JSON/api clients: the original $response Laravel already
            // built is correct as-is (shouldRenderJsonWhen above already
            // picked JSON for these, with status 429 and Retry-After set).
            // Plain HTML clients: falling through here lets Laravel's
            // normal view-resolution pick up resources/views/errors/429
            // .blade.php on its own — nothing to do.
            return $response;
        });
    })->create();
