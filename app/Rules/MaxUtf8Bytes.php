<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Amazon Translate's TranslateText API rejects input over 10,000 UTF-8
 * BYTES (not characters) per call. This is a byte-based companion to the
 * character-based `max:N` rule already on the same field: `max:5000` keeps
 * an English submission well under the byte limit, but Japanese/
 * Vietnamese text (and emoji) can run 3-4 bytes per character, so a
 * 5,000-character submission in those scripts can still exceed 10,000
 * bytes. Rejecting it here, at submission time, is preferred over
 * accepting it and only discovering the problem when the translation job
 * runs (AwsTranslateTranslator carries the same 10,000-byte check as a
 * defensive second layer, for rows written before this rule existed).
 */
class MaxUtf8Bytes implements ValidationRule
{
    public function __construct(private readonly int $maxBytes = 10_000) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        // strlen() counts bytes, not characters, for any string in PHP -
        // exactly what Amazon Translate's limit is measured in.
        if (strlen($value) > $this->maxBytes) {
            $fail('validation.max_utf8_bytes')->translate(['max' => $this->maxBytes]);
        }
    }
}
