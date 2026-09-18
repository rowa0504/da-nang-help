<?php

namespace App\Http\Requests\Provider;

use App\Enums\UserRole;
use App\Models\Category;
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
            'other_service_details' => [
                Rule::requiredIf($this->isOtherCategorySelected()),
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    /**
     * Whether the "other" category's id is present among the *submitted*
     * category_ids — used only to decide whether other_service_details is
     * required here. Ids are normalized to int and compared with a strict
     * in_array() so a string/loose match can't misfire either way.
     */
    private function isOtherCategorySelected(): bool
    {
        $otherCategoryId = Category::query()->where('slug', 'other')->value('id');
        if ($otherCategoryId === null) {
            return false;
        }

        $submittedCategoryIds = array_map('intval', (array) $this->input('category_ids', []));

        return in_array((int) $otherCategoryId, $submittedCategoryIds, true);
    }
}
