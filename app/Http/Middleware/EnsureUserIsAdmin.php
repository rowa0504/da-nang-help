<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Blanket gate for the /admin route group. Only ever consults the
     * authenticated User model's `role` column — never a request input —
     * so it cannot be spoofed by a client-supplied "role" value.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== UserRole::Admin) {
            abort(403);
        }

        return $next($request);
    }
}
