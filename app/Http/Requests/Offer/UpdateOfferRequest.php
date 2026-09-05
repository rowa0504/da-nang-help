<?php

namespace App\Http\Requests\Offer;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Provider;
    }

    public function rules(): array
    {
        return [
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'currency' => ['required', 'regex:/^[A-Z]{3}$/'],
            'message' => ['required', 'string', 'max:2000'],
            'available_at' => ['nullable', 'date'],
            'source_locale' => ['required', Rule::in(['en', 'ja', 'vi'])],
        ];
    }
}
