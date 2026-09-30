<?php

namespace App\Http\Requests\Offer;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Provider;
    }

    public function rules(): array
    {
        return [
            // MVP is VND-only (integer, no decimals): see CreateOfferAction,
            // which forces currency to 'VND' regardless of what's sent here.
            'price' => ['required', 'integer', 'min:0', 'max:9999999999'],
            'currency' => ['prohibited'],
            'message' => ['required', 'string', 'max:2000'],
            'available_at' => ['nullable', 'date'],
            'source_locale' => ['required', Rule::in(['en', 'ja', 'vi'])],
        ];
    }
}
