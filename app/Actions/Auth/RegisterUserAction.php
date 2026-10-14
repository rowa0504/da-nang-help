<?php

namespace App\Actions\Auth;

use App\Enums\UserRole;
use App\Models\User;

class RegisterUserAction
{
    /**
     * Create a new user account.
     *
     * `role` and the five legal-consent columns are set via direct property
     * assignment rather than mass assignment: all are deliberately excluded
     * from User's Fillable attribute so no request payload can set them
     * implicitly. In particular, `age_confirmed`/`agree_to_legal` in $data
     * are only ever booleans confirming the checkboxes were checked (see
     * RegisterUserRequest) — the actual timestamp and version string
     * recorded here always come from the server clock and
     * config('legal.*'), never from client input.
     *
     * @param  array{name: string, email: string, password: string, phone?: ?string}  $data
     */
    public function handle(array $data, UserRole $role): User
    {
        $user = new User();
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->phone = $data['phone'] ?? null;
        $user->password = $data['password'];
        $user->role = $role;
        $user->locale = $data['locale'] ?? 'en';

        // A single timestamp for all three columns: they are recording one
        // event (this registration), not three independent ones, so they
        // must never drift apart by a microsecond across separate now()
        // calls.
        $now = now();
        $user->age_confirmed_at = $now;
        $user->terms_accepted_at = $now;
        $user->terms_version = config('legal.terms.version');
        $user->privacy_acknowledged_at = $now;
        $user->privacy_version = config('legal.privacy.version');

        $user->save();

        return $user;
    }
}
