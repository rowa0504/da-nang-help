<?php

namespace App\Models;

use App\Enums\TranslationStatus;
use Database\Factories\ServiceRequestTranslationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['service_request_id', 'locale', 'title', 'description', 'source_hash', 'translation_status', 'translated_at'])]
class ServiceRequestTranslation extends Model
{
    /** @use HasFactory<ServiceRequestTranslationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'translation_status' => TranslationStatus::class,
            'translated_at' => 'datetime',
        ];
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }
}
