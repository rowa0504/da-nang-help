<?php

namespace App\Models;

use App\Enums\OfferStatus;
use App\Enums\TranslationStatus;
use App\Policies\OfferPolicy;
use Database\Factories\OfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['price', 'currency', 'message', 'available_at', 'source_locale'])]
#[UsePolicy(OfferPolicy::class)]
class Offer extends Model
{
    /** @use HasFactory<OfferFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => OfferStatus::class,
            'price' => 'decimal:2',
            'available_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(OfferTranslation::class);
    }

    public function serviceJob(): HasOne
    {
        return $this->hasOne(ServiceJob::class);
    }

    /**
     * The viewer's locale translation if it has completed, otherwise the
     * original message (FR-20/FR-21).
     */
    public function translatedMessageFor(string $locale): string
    {
        if ($locale === $this->source_locale) {
            return $this->message;
        }

        $translation = $this->translations->firstWhere('locale', $locale);
        if ($translation !== null && $translation->translation_status === TranslationStatus::Completed) {
            return $translation->message;
        }

        return $this->message;
    }
}
