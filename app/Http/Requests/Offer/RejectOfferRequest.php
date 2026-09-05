<?php

namespace App\Http\Requests\Offer;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class RejectOfferRequest extends FormRequest
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
