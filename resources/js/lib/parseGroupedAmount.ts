import { normalizeDigits } from '@/lib/normalizeDigits';

const GROUP_SEPARATORS = [',', '.', ' ', ' '] as const;

/**
 * Parses a complete, user-supplied money string (typed in one go, or
 * pasted) into a canonical ASCII-digit string (e.g. "1000000"), or returns
 * null to reject it outright.
 *
 * Only three shapes are accepted, after converting full-width digits to
 * ASCII:
 *   - a bare digit string with no separator at all ("1000000")
 *   - a single separator character (",", ".", a plain space, or NBSP)
 *     used consistently as a three-digit thousands grouping: 1–3 digits,
 *     then one or more repeats of (separator + exactly 3 digits) —
 *     "100,000", "1.000.000"
 *
 * Everything else is rejected — never coerced into a "best guess" amount:
 *   - a group that isn't exactly 3 digits (e.g. "100.00", "1.5", "12.34" —
 *     indistinguishable from a decimal-cents amount, which this app does
 *     not support at all; silently stripping the separator would turn
 *     "100.00" into "10000", a materially different — and wrong — amount)
 *   - two different separator characters mixed together ("1,000.000")
 *   - any character that isn't a digit or a recognized separator
 *     ("100abc000")
 */
export function parseGroupedAmount(rawInput: string): string | null {
    // Convert full-width digits to ASCII one character at a time; leave
    // separator/other characters as-is for the structural check below
    // (normalizeDigits itself rejects anything that isn't a digit).
    const converted = Array.from(rawInput, (char) => normalizeDigits(char) ?? char).join('');

    const usedSeparators = GROUP_SEPARATORS.filter((sep) => converted.includes(sep));

    if (usedSeparators.length === 0) {
        // `*`, not `+`: an empty string (the field cleared entirely, e.g.
        // via select-all + Delete) is a valid — not rejected — amount.
        return /^\d*$/.test(converted) ? converted : null;
    }

    if (new Set(usedSeparators).size > 1) {
        // Two different separator characters in the same string (e.g.
        // "1,000.000") — genuinely ambiguous, reject rather than guess
        // which one was "the real" grouping separator.
        return null;
    }

    const separator = usedSeparators[0];
    const escaped = separator === '.' ? '\\.' : separator;
    const pattern = new RegExp(`^\\d{1,3}(?:${escaped}\\d{3})+$`);

    return pattern.test(converted) ? converted.split(separator).join('') : null;
}
