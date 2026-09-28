<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserRole;
use App\Rules\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            // max:30 is enforced inside PhoneNumber itself (alongside the
            // character-set and digit-count checks), so it isn't repeated
            // as a separate rule here.
            'phone' => ['nullable', 'string', new PhoneNumber()],
            // Only Customer/Provider may self-register. Admin accounts are
            // never created through the public registration form.
            'role' => ['required', Rule::in([
                UserRole::Customer->value,
                UserRole::Provider->value,
            ])],
        ];
    }
}
