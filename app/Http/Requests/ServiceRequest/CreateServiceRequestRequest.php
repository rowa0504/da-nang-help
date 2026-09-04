<?php

namespace App\Http\Requests\ServiceRequest;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateServiceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Customer;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'area_id' => ['required', 'integer', Rule::exists('areas', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'address_text' => ['required', 'string', 'max:500'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'urgency' => ['required', Rule::in(['normal', 'urgent'])],
            'source_locale' => ['required', Rule::in(['en', 'ja', 'vi'])],
            'photos' => ['nullable', 'array', 'max:5'],
            // Real MIME type, decodability, and dimensions are validated by
            // ProcessUploadedPhoto, not here: rejecting on `mimes:` here
            // would 422 the whole request instead of letting the Action
            // skip just the one bad photo (see CreateServiceRequestAction).
            'photos.*' => ['file', 'max:5120'],
        ];
    }
}
