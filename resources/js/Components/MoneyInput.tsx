import { ChangeEvent, ClipboardEvent, ComponentPropsWithoutRef, useEffect, useRef, useState } from 'react';
import { parseGroupedAmount } from '@/lib/parseGroupedAmount';
import { SupportedLocale } from '@/types';

const BASE = 'mt-1 block w-full rounded border px-3 py-2 focus:outline-none focus:ring-1';
const VALID = 'border-gray-300 focus:border-brand-600 focus:ring-brand-600';
const INVALID = 'border-red-400 focus:border-red-500 focus:ring-red-500';

interface MoneyInputProps
    extends Omit<ComponentPropsWithoutRef<'input'>, 'value' | 'onChange' | 'type' | 'inputMode' | 'onPaste'> {
    // Always an ASCII-digit-only string ("" for empty), never grouped —
    // grouping is a display-only concern local to this component.
    value: string;
    onChange: (value: string) => void;
    locale: SupportedLocale;
    invalid?: boolean;
}

function groupForDisplay(digits: string, locale: SupportedLocale): string {
    if (digits === '') {
        return '';
    }
    // `style: 'decimal'`, not 'currency' — the caller (e.g. Show.tsx) is
    // responsible for the " VND"/" ₫" suffix, matching useLocaleFormat's
    // formatCurrency for read-only display. This is purely the live-typing
    // thousands-grouping.
    return new Intl.NumberFormat(locale, { maximumFractionDigits: 0 }).format(Number(digits));
}

function digitsBeforeIndex(raw: string, index: number): number {
    let count = 0;
    for (let i = 0; i < index && i < raw.length; i++) {
        if (raw[i] >= '0' && raw[i] <= '9') {
            count++;
        }
    }
    return count;
}

// After reformatting with thousands separators, the caret would otherwise
// jump to the end of the field on every keystroke. This walks the new
// (separator-containing) string and returns the index right after the same
// *digit* the caret was after before reformatting, so typing in the middle
// of a number behaves naturally on both desktop and mobile.
function caretIndexAfterNDigits(formatted: string, digitCount: number): number {
    if (digitCount <= 0) {
        return 0;
    }
    let seen = 0;
    for (let i = 0; i < formatted.length; i++) {
        if (formatted[i] >= '0' && formatted[i] <= '9') {
            seen++;
            if (seen === digitCount) {
                return i + 1;
            }
        }
    }
    return formatted.length;
}

const SEPARATOR_CHARS = new Set([',', '.', ' ', ' ']);

// Strips every recognized separator character with *no* grouping
// validation at all (unlike parseGroupedAmount) — deliberately used only
// as a narrow fallback below, never for a paste.
function stripSeparatorsBlindly(raw: string): string | null {
    let result = '';
    for (const char of raw) {
        if (SEPARATOR_CHARS.has(char)) {
            continue;
        }
        if (char < '0' || char > '9') {
            return null;
        }
        result += char;
    }
    return result;
}

/**
 * Resolves what the canonical digit value should become after a typed
 * (non-paste) change. Tries strict grouping validation first via
 * parseGroupedAmount — this alone correctly handles a value freshly typed
 * or IME-composed in one shot ("100,000" accepted; the ambiguous "100.00"
 * rejected).
 *
 * If that fails, falls back to blindly stripping separators — but *only*
 * when the resulting digit count differs from the previous value by
 * exactly one. A `value` this component displays is always grouped by
 * groupForDisplay() first, so a single keystroke against it (typing one
 * more digit at the end of "450,000", or Backspacing one) produces a raw
 * DOM value like "450,0001" or "450,00" that *looks* malformed by strict
 * grouping rules purely because it's mid-edit, not because the user typed
 * anything ambiguous — digit count changing by exactly one is what
 * distinguishes that ordinary case from a genuinely ambiguous value like
 * "1.5" or "12.34" (whose digit count differs by 2+ from an empty/starting
 * field). Anything larger — a real paste that reached here some other way,
 * IME composing several characters at once, autofill — is intentionally
 * NOT covered by this fallback and is rejected, exactly like a malformed
 * paste would be.
 */
function resolveTypedValue(raw: string, previousValue: string): string | null {
    const strict = parseGroupedAmount(raw);
    if (strict !== null) {
        return strict;
    }
    const stripped = stripSeparatorsBlindly(raw);
    if (stripped !== null && Math.abs(stripped.length - previousValue.length) === 1) {
        return stripped;
    }
    return null;
}

/**
 * A `type="text"` money input that behaves like a plain numeric field:
 * form state and the value handed to `onChange` are always a bare ASCII
 * digit string (e.g. "450000"), while the field displays it grouped for
 * the given locale while typing. Full-width digits are normalized to
 * ASCII. A typed or pasted value containing a thousands-grouping separator
 * is accepted only if it unambiguously represents one (1–3 digits, then
 * groups of exactly 3) — an amount that merely *looks* like it could be
 * one, such as "100.00" or "1.5", is rejected outright rather than
 * silently reinterpreted as a different number (see parseGroupedAmount).
 * Any other character, or a paste that fails this check, leaves the
 * current value unchanged. Standard editing (Backspace/Delete/Tab/arrows/
 * copy) is untouched since nothing here intercepts keydown — only the
 * resulting value, in onChange/onPaste.
 */
export function MoneyInput({ value, onChange, locale, invalid = false, className = '', ...rest }: MoneyInputProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [display, setDisplay] = useState(() => groupForDisplay(value, locale));
    // A pending caret position to restore after the next render (reformatting
    // replaces the DOM value, which resets the caret to the end otherwise).
    const pendingCaret = useRef<number | null>(null);

    useEffect(() => {
        setDisplay(groupForDisplay(value, locale));
    }, [value, locale]);

    useEffect(() => {
        if (pendingCaret.current !== null && inputRef.current) {
            inputRef.current.setSelectionRange(pendingCaret.current, pendingCaret.current);
            pendingCaret.current = null;
        }
    }, [display]);

    function commit(nextValue: string, caretDigitsAfter: number) {
        const formatted = groupForDisplay(nextValue, locale);
        pendingCaret.current = caretIndexAfterNDigits(formatted, caretDigitsAfter);
        setDisplay(formatted);
        if (nextValue !== value) {
            onChange(nextValue);
        }
    }

    function reject() {
        // Re-assert the last known-good display so the DOM doesn't keep
        // whatever the browser already inserted into this controlled
        // input's underlying element.
        setDisplay(groupForDisplay(value, locale));
    }

    function handleChange(e: ChangeEvent<HTMLInputElement>) {
        const el = e.target;
        const caretDigitsBefore = digitsBeforeIndex(el.value, el.selectionStart ?? el.value.length);
        const resolved = resolveTypedValue(el.value, value);
        if (resolved === null) {
            reject();
            return;
        }
        commit(resolved, caretDigitsBefore);
    }

    function handlePaste(e: ClipboardEvent<HTMLInputElement>) {
        // Always prevent the browser's own paste insertion — every path
        // below applies the pasted text (parsed or not) explicitly instead.
        e.preventDefault();

        const pasted = e.clipboardData.getData('text');
        // The pasted text is validated *on its own*, as a complete unit —
        // never partially applied, and never re-interpreted together with
        // whatever separators the current display happens to already show.
        const parsedPaste = parseGroupedAmount(pasted);
        if (parsedPaste === null) {
            // Leave the current value untouched — no setDisplay/onChange at
            // all, so a rejected paste has zero effect.
            return;
        }

        const el = e.currentTarget;
        const digitsBeforeStart = digitsBeforeIndex(el.value, el.selectionStart ?? el.value.length);
        const digitsBeforeEnd = digitsBeforeIndex(el.value, el.selectionEnd ?? el.value.length);
        const nextValue = value.slice(0, digitsBeforeStart) + parsedPaste + value.slice(digitsBeforeEnd);
        commit(nextValue, digitsBeforeStart + parsedPaste.length);
    }

    return (
        <input
            ref={inputRef}
            type="text"
            inputMode="numeric"
            value={display}
            onChange={handleChange}
            onPaste={handlePaste}
            className={[BASE, invalid ? INVALID : VALID, className].filter(Boolean).join(' ')}
            {...rest}
        />
    );
}
