<?php

namespace App\Http\Requests\Provider;

use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Category;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitProviderProfileRequest extends FormRequest
{
    /**
     * Only checks *who* is making the request. Whether the request's
     * current state (e.g. already pending/approved, or suspended) allows
     * submission is a Policy/Action concern, not a Form Request concern.
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
        // Read from the DB, never from the request: an id is allowed only
        // if it's currently active, OR it was already attached to *this*
        // Provider's own profile before this submission. The second branch
        // must be sourced from the caller's existing pivot rows, not from
        // anything the client sent — otherwise a Provider could claim any
        // inactive id was "already theirs". A brand-new Provider (no
        // profile yet) simply has an empty existing set here, which
        // degrades this to "active only", matching the create flow exactly.
        $existingCategoryIds = $this->user()?->providerProfile?->categories()->pluck('categories.id')->all() ?? [];
        $existingAreaIds = $this->user()?->providerProfile?->areas()->pluck('areas.id')->all() ?? [];

        return [
            'business_name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => [
                'required',
                'integer',
                'distinct',
                $this->activeOrAlreadyAttachedRule(Category::class, $existingCategoryIds),
            ],
            'area_ids' => ['required', 'array', 'min:1'],
            'area_ids.*' => [
                'required',
                'integer',
                'distinct',
                $this->activeOrAlreadyAttachedRule(Area::class, $existingAreaIds),
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
     * @param  class-string  $modelClass
     * @param  array<int>  $alreadyAttachedIds
     */
    private function activeOrAlreadyAttachedRule(string $modelClass, array $alreadyAttachedIds): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($modelClass, $alreadyAttachedIds) {
            if (in_array((int) $value, $alreadyAttachedIds, true)) {
                return;
            }

            $isActive = $modelClass::query()->where('id', $value)->where('is_active', true)->exists();
            if (! $isActive) {
                $fail('validation.exists')->translate();
            }
        };
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
