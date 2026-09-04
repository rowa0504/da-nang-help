<?php

namespace App\Actions\ServiceRequest;

use App\Models\ServiceRequest;

final readonly class CreateServiceRequestResult
{
    /**
     * @param  array<int, string>  $photoWarnings  Reasons for any photos that could not be processed and were skipped. Empty when all photos (if any) succeeded.
     */
    public function __construct(
        public ServiceRequest $serviceRequest,
        public array $photoWarnings,
    ) {}
}
