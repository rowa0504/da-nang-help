<?php

namespace App\Support;

/**
 * Computes the `source_hash` used by OfferTranslation rows to detect stale
 * translation jobs (FR-23a). Includes source_locale alongside message: a
 * source_locale change alone (message unchanged) still changes what the
 * text is being translated *from*, so a job dispatched under the old
 * source_locale must be treated as stale.
 */
final class OfferHasher
{
    public static function hash(string $message, string $sourceLocale): string
    {
        return hash('sha256', json_encode(
            [$message, $sourceLocale],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));
    }
}
