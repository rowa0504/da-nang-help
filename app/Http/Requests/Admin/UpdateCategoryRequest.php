<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Admin;
    }

    /**
     * No 'slug' rule: slug is immutable after creation (Phase 9 plan
     * §2#9), so the Edit form never submits it and this Request never
     * validates or accepts one.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', 'exists:categories,id', Rule::notIn([$this->route('category')->id])],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'names.en' => ['required', 'string', 'max:255'],
            'names.ja' => ['required', 'string', 'max:255'],
            'names.vi' => ['required', 'string', 'max:255'],
        ];
    }
}
