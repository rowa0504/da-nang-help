const FULLWIDTH_DIGIT_OFFSET = '０'.codePointAt(0)! - '0'.codePointAt(0)!;

function toHalfWidthDigit(char: string): string {
    const code = char.codePointAt(0);
    if (code === undefined || code < 0xff10 || code > 0xff19) {
        return char;
    }
    return String.fromCodePoint(code - FULLWIDTH_DIGIT_OFFSET);
}

/**
 * Converts full-width digits to ASCII digits — nothing else. Returns null
 * if, after conversion, anything other than an ASCII digit remains.
 *
 * Deliberately narrow: this function has exactly one job (digit-charset
 * normalization) and knows nothing about thousands separators or grouping
 * — see parseGroupedAmount for that. A ","/"."/space is not a digit, so it
 * is rejected here, not silently stripped; stripping it without validating
 * its position is exactly what would turn an ambiguous decimal amount like
 * "100.00" into a wrong value ("10000") instead of being rejected.
 */
export function normalizeDigits(input: string): string | null {
    let result = '';
    for (const char of input) {
        const halfWidth = toHalfWidthDigit(char);
        if (halfWidth < '0' || halfWidth > '9' || halfWidth.length !== 1) {
            return null;
        }
        result += halfWidth;
    }
    return result;
}
