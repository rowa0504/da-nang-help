<?php

namespace App\Models;

use App\Enums\ServiceJobStatus;
use App\Policies\JobPolicy;
use Database\Factories\ServiceJobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Blueprint's `JOBS` entity, implemented under the `service_jobs` table
 * name to avoid colliding with Laravel's own queue `jobs` table. Phase 5
 * only ever creates a row here (via AcceptOfferAction, status=assigned);
 * every column beyond that point (provider_completed_at onward) exists per
 * the ER diagram but is not read or written until Phase 6.
 */
#[Fillable([])]
#[UsePolicy(JobPolicy::class)]
class ServiceJob extends Model
{
    /** @use HasFactory<ServiceJobFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ServiceJobStatus::class,
            'agreed_price' => 'decimal:2',
            'provider_completed_at' => 'datetime',
            'customer_confirmed_at' => 'datetime',
            'auto_confirm_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }
}
