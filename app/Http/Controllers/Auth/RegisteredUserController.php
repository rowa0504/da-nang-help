<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterUserAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterUserRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(RegisterUserRequest $request, RegisterUserAction $action): RedirectResponse
    {
        $user = $action->handle(
            $request->validated(),
            UserRole::from($request->validated('role')),
        );

        Auth::login($user);

        // Regenerate the session ID to guard against session fixation now
        // that the session is associated with a new, authenticated user.
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
