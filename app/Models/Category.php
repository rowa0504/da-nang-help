<?php

namespace App\Models;

use App\Policies\CategoryPolicy;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'parent_id', 'is_active', 'sort_order'])]
#[UsePolicy(CategoryPolicy::class)]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(CategoryTranslation::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function scopeActiveOrdered($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * Resolves the display name for a locale without re-querying: callers
     * must eager load `translations` (all locales, not filtered to one) so
     * this can fall back from the requested locale to English to the slug
     * without an extra query per category.
     */
    public function nameFor(string $locale): string
    {
        return $this->translations->firstWhere('locale', $locale)?->name
            ?? $this->translations->firstWhere('locale', 'en')?->name
            ?? $this->slug;
    }
}
