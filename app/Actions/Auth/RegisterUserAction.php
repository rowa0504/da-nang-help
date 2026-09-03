<?php

namespace App\Actions\Auth;

use App\Enums\UserRole;
use App\Models\User;

class RegisterUserAction
{
    /**
     * Create a new user account.
     *
     * `role` is set via direct property assignment rather than mass
     * assignment: it is deliberately excluded from User's Fillable
     * attribute so that no request payload can set it implicitly.
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
        $user->save();

        return $user;
    }
}
