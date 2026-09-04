<?php

namespace App\Support;

/**
 * Computes the `source_hash` used by ServiceRequestTranslation rows to
 * detect stale translation jobs (FR-23a). Shared by
 * CreateServiceRequestAction (which stamps pending translation rows) and
 * TranslateServiceRequestJob (which re-checks before writing), so the two
 * never drift apart.
 */
final class ServiceRequestHasher
{
    public static function hash(string $title, string $description): string
    {
        return hash('sha256', json_encode(
            [$title, $description],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));
    }
}
