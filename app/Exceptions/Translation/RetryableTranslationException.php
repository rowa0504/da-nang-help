<?php

namespace App\Exceptions\Translation;

/**
 * A translation attempt failed in a way that is expected to succeed on
 * retry: a connection/network problem, a rate limit, or a transient AWS
 * service error. TranslateServiceRequestJob/TranslateOfferJob let this
 * propagate uncaught so Laravel's queue retries the job (see their $tries
 * and backoff()), rather than writing `failed` immediately.
 */
class RetryableTranslationException extends \RuntimeException
{
}
