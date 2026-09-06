<?php

namespace App\Exceptions;

/**
 * Dedicated exception for the job_id UNIQUE-constraint case (a Customer
 * has already reviewed this Job). Thrown both by a pre-lock exists() check
 * inside CreateReviewAction and by catching a QueryException whose
 * errorInfo/message identify the reviews_job_id_unique constraint.
 */
class DuplicateReviewException extends InvalidReviewTransitionException
{
    //
}
