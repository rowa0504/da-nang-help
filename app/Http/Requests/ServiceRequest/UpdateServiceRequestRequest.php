<?php

namespace App\Http\Requests\ServiceRequest;

use App\Models\Area;
use App\Models\Category;
use App\Models\ServiceRequest;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRequestRequest extends FormRequest
{
    private const MAX_PHOTOS = 5;

    /**
     * Resolves and authorizes against the specific ServiceRequest up front
     * (unlike role-only Form Requests elsewhere in this app) so that a
     * non-owner is rejected before any validation rule below — including
     * the remove_photo_ids ownership check — ever runs, and never has a
     * chance to observe a difference in behavior between "not yours" and
     * "invalid input".
     */
    public function authorize(): bool
    {
        $serviceRequest = $this->route('serviceRequest');

        return $serviceRequest instanceof ServiceRequest
            && $this->user()?->can('update', $serviceRequest);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var ServiceRequest $serviceRequest */
        $serviceRequest = $this->route('serviceRequest');
        $existingPhotoIds = $serviceRequest->photos()->pluck('id')->all();

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'category_id' => [
                'required',
                'integer',
                $this->activeOrCurrentRule(Category::class, $serviceRequest->category_id),
            ],
            'area_id' => [
                'required',
                'integer',
                $this->activeOrCurrentRule(Area::class, $serviceRequest->area_id),
            ],
            'address_text' => ['required', 'string', 'max:500'],
            'urgency' => ['required', Rule::in(['normal', 'urgent'])],
            'photos' => ['nullable', 'array'],
            // Same as CreateServiceRequestRequest: real MIME/decodability
            // checks are deferred to ProcessUploadedPhoto so one bad file
            // can be skipped instead of 422-ing the whole submission.
            'photos.*' => ['file', 'max:5120'],
            'remove_photo_ids' => ['nullable', 'array'],
            // Sourced from the DB, not trusted from the client: only ids
            // that actually belong to *this* ServiceRequest are accepted,
            // so another Request's photo id is rejected the same way an
            // unrelated garbage id would be.
            'remove_photo_ids.*' => ['integer', 'distinct', Rule::in($existingPhotoIds)],
        ];
    }

    /**
     * Cross-field: current photos, minus those marked for removal, plus any
     * newly uploaded files, must not exceed the 5-photo cap. This is the
     * fast, pre-flight check — UpdateServiceRequestAction re-verifies the
     * same arithmetic again after acquiring its row lock, sourced from the
     * DB at that point, as the authoritative last line of defence.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var ServiceRequest $serviceRequest */
            $serviceRequest = $this->route('serviceRequest');
            $existingCount = $serviceRequest->photos()->count();
            $removeCount = count(array_unique((array) $this->input('remove_photo_ids', [])));
            $newCount = count($this->file('photos', []));

            if ($existingCount - $removeCount + $newCount > self::MAX_PHOTOS) {
                $validator->errors()->add('photos', __('validation.photo_limit_exceeded', ['max' => self::MAX_PHOTOS]));
            }
        });
    }

    /**
     * @param  class-string  $modelClass
     */
    private function activeOrCurrentRule(string $modelClass, int $currentId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($modelClass, $currentId) {
            if ((int) $value === $currentId) {
                return;
            }

            $isActive = $modelClass::query()->where('id', $value)->where('is_active', true)->exists();
            if (! $isActive) {
                $fail('validation.exists')->translate();
            }
        };
    }
}
