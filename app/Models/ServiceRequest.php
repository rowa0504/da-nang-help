<?php

namespace App\Models;

use App\Enums\ServiceRequestModerationStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestUrgency;
use App\Enums\TranslationStatus;
use App\Policies\ServiceRequestPolicy;
use Database\Factories\ServiceRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['title', 'description', 'category_id', 'area_id', 'address_text', 'lat', 'lng', 'urgency', 'source_locale'])]
#[Hidden(['address_text', 'lat', 'lng'])]
#[UsePolicy(ServiceRequestPolicy::class)]
class ServiceRequest extends Model
{
    /** @use HasFactory<ServiceRequestFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ServiceRequestStatus::class,
            'urgency' => ServiceRequestUrgency::class,
            'moderation_status' => ServiceRequestModerationStatus::class,
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'hidden_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(RequestPhoto::class)->orderBy('sort_order');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ServiceRequestTranslation::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function serviceJob(): HasOne
    {
        return $this->hasOne(ServiceJob::class);
    }

    public function hiddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by');
    }

    public function translatedTitleFor(string $locale): string
    {
        if ($locale === $this->source_locale) {
            return $this->title;
        }

        $translation = $this->translations->firstWhere('locale', $locale);
        if ($translation !== null && $translation->translation_status === TranslationStatus::Completed) {
            return $translation->title;
        }

        return $this->title;
    }

    public function translatedDescriptionFor(string $locale): string
    {
        if ($locale === $this->source_locale) {
            return $this->description;
        }

        $translation = $this->translations->firstWhere('locale', $locale);
        if ($translation !== null && $translation->translation_status === TranslationStatus::Completed) {
            return $translation->description;
        }

        return $this->description;
    }
}
