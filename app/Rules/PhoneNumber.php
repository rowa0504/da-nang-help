<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accepts domestic and international phone numbers written with ASCII
 * digits, spaces, hyphens, and parentheses, plus at most one leading '+' —
 * e.g. "0901234567", "+84 90 123 4567", "(+84) 90-123-4567". Rejects
 * letters, full-width digits, emoji, tabs/newlines, and any other symbol.
 * Digits alone (punctuation stripped) must total 7-15, the range real
 * national/international numbers fall into, without hardcoding any single
 * country's format.
 *
 * The value is stored exactly as typed — no normalization to E.164 or any
 * other canonical form. That, plus per-country validation via
 * libphonenumber, is deferred to whenever real SMS verification is added
 * (not in scope here).
 */
class PhoneNumber implements ValidationRule
{
    private const MAX_LENGTH = 30;

    private const MIN_DIGITS = 7;

    private const MAX_DIGITS = 15;

    /**
     * A single optional leading '+', then one or more ASCII digits,
     * spaces, hyphens, or parentheses — nothing else. Since '+' is only
     * matched by the leading `\+?` and never appears in the repeated
     * character class, a '+' anywhere else in the string (mid-string or
     * repeated) fails this pattern on its own.
     */
    private const ALLOWED_PATTERN = '/^\+?[0-9 \-()]+$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('validation.phone_number')->translate();

            return;
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            $fail('validation.phone_number')->translate();

            return;
        }

        if (preg_match(self::ALLOWED_PATTERN, $value) !== 1) {
            $fail('validation.phone_number')->translate();

            return;
        }

        $digitCount = strlen((string) preg_replace('/\D/', '', $value));

        if ($digitCount < self::MIN_DIGITS || $digitCount > self::MAX_DIGITS) {
            $fail('validation.phone_number')->translate();
        }
    }
}
