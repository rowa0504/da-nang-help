<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by the Provider/Admin Actions when a ProviderProfile state
 * transition is attempted from a status that does not allow it (e.g.
 * resubmitting a `pending`/`approved` profile, or approving/rejecting a
 * profile that is no longer `pending`). This is checked again inside the
 * Action after acquiring a row lock, so it also guards against races
 * between concurrent requests that both passed the earlier Policy check.
 */
class InvalidProviderVerificationTransitionException extends RuntimeException
{
    //
}
