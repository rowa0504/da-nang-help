<?php

namespace App\Models;

use App\Enums\TranslationStatus;
use Database\Factories\OfferTranslationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['offer_id', 'locale', 'message', 'source_hash', 'translation_status', 'translated_at'])]
class OfferTranslation extends Model
{
    /** @use HasFactory<OfferTranslationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'translation_status' => TranslationStatus::class,
            'translated_at' => 'datetime',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}
