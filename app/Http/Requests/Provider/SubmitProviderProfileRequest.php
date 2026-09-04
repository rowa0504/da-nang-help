<?php

namespace App\Http\Requests\Provider;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitProviderProfileRequest extends FormRequest
{
    /**
     * Only checks *who* is making the request. Whether the request's
     * current state (e.g. already pending/approved) allows submission is a
     * Policy/Action concern, not a Form Request concern.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Provider;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'area_ids' => ['required', 'array', 'min:1'],
            'area_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('areas', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ];
    }
}
