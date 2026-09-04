<?php

namespace App\Models;

use Database\Factories\RequestPhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['service_request_id', 'object_key', 'sort_order'])]
class RequestPhoto extends Model
{
    /** @use HasFactory<RequestPhotoFactory> */
    use HasFactory;

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }
}
