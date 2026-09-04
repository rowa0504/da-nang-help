<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A single uploaded photo could not be processed (unsupported type, corrupt
 * data, exceeds dimension limits, or failed to store). The caller (an
 * Action) catches this and skips just this one photo instead of failing the
 * whole service request submission.
 */
class UnprocessablePhotoException extends RuntimeException
{
    //
}
