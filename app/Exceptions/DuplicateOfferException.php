<?php

namespace App\Exceptions;

/**
 * A Provider already has an offer (in any status) on this Service Request
 * (FR-25/FR-32). Extends InvalidOfferTransitionException so a generic catch
 * still works, but the Controller catches this first for a more specific
 * message.
 */
class DuplicateOfferException extends InvalidOfferTransitionException
{
    //
}
