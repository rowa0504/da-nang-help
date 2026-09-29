<?php

namespace App\Actions\ServiceRequest;

use App\Models\ServiceRequest;

final readonly class UpdateServiceRequestResult
{
    /**
     * @param  array<int, string>  $photoWarnings  Reasons for any newly uploaded photos that could not be processed and were skipped. Empty when all succeeded.
     */
    public function __construct(
        public ServiceRequest $serviceRequest,
        public array $photoWarnings,
    ) {}
}
