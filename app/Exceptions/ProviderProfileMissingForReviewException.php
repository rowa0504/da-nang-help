<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by CreateReviewAction/HideReviewAction when the review's Provider
 * has no ProviderProfile row to update avg_rating on. Should be unreachable
 * in practice (CreateOfferAction requires an approved profile to exist
 * before an Offer — and therefore a Job — can ever be created), but is
 * handled explicitly so a review is never created/hidden without the
 * rating aggregate being updated to match.
 */
class ProviderProfileMissingForReviewException extends RuntimeException
{
    public function __construct(public readonly int $providerId)
    {
        parent::__construct("Cannot update rating: provider {$providerId} has no provider profile.");
    }
}
