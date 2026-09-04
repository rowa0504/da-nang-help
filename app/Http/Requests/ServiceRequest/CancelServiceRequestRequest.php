<?php

namespace App\Http\Requests\ServiceRequest;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class CancelServiceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Customer;
    }

    public function rules(): array
    {
        return [];
    }
}
