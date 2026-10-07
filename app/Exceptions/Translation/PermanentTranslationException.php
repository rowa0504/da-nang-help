<?php

namespace App\Exceptions\Translation;

/**
 * A translation attempt failed in a way retrying will not fix: the input
 * itself is rejected (too large, malformed, an unsupported language pair)
 * or the request was never authorized. TranslateServiceRequestJob/
 * TranslateOfferJob catch this and write `translation_status = failed`
 * immediately, without consuming any further retry attempts.
 */
class PermanentTranslationException extends \RuntimeException
{
}
