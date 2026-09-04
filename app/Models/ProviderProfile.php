<?php

namespace App\Models;

use App\Enums\ProviderVerificationStatus;
use App\Policies\ProviderProfilePolicy;
use Database\Factories\ProviderProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['business_name', 'bio'])]
#[Hidden(['verification_note'])]
#[UsePolicy(ProviderProfilePolicy::class)]
class ProviderProfile extends Model
{
    /** @use HasFactory<ProviderProfileFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'verification_status' => ProviderVerificationStatus::class,
            'avg_rating' => 'decimal:2',
            'completed_jobs_count' => 'integer',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'provider_categories');
    }

    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class, 'provider_areas');
    }
}
