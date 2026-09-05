<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by CompleteJobAction when a Job's provider has no ProviderProfile
 * row to increment completed_jobs_count on. This should be unreachable in
 * practice (CreateOfferAction requires an approved profile to exist before
 * an Offer — and therefore a Job — can ever be created), but is handled
 * explicitly so the job is never marked completed without the count being
 * updated, and so AutoConfirmJobsCommand can log and skip just this job
 * instead of losing the whole batch to it.
 */
class ProviderProfileMissingForJobException extends RuntimeException
{
    public function __construct(public readonly int $jobId, public readonly int $providerId)
    {
        parent::__construct("Cannot complete job {$jobId}: provider {$providerId} has no provider profile.");
    }
}
