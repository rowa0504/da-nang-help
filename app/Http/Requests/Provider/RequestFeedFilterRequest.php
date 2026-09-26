<?php

namespace App\Http\Requests\Provider;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the optional query-string filters on GET /provider/requests.
 * All fields are optional — an absent/empty value means "no filter", not
 * an error.
 */
class RequestFeedFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Provider;
    }

    public function rules(): array
    {
        return [
            'recommended' => ['nullable', 'boolean'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'area_id' => ['nullable', 'integer', Rule::exists('areas', 'id')->where(fn ($query) => $query->where('is_active', true))],
        ];
    }

    public function isRecommendedOnly(): bool
    {
        return $this->boolean('recommended');
    }

    public function categoryId(): ?int
    {
        $value = $this->validated('category_id');

        return $value !== null ? (int) $value : null;
    }

    public function areaId(): ?int
    {
        $value = $this->validated('area_id');

        return $value !== null ? (int) $value : null;
    }
}
