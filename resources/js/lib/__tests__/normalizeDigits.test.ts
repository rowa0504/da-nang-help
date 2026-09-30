import { describe, expect, it } from 'vitest';
import { normalizeDigits } from '@/lib/normalizeDigits';

describe('normalizeDigits', () => {
    it('passes a plain ASCII digit string through unchanged', () => {
        expect(normalizeDigits('100000')).toBe('100000');
    });

    it('converts full-width digits to ASCII', () => {
        expect(normalizeDigits('１０００００')).toBe('100000');
    });

    it('converts a mix of full-width and ASCII digits', () => {
        expect(normalizeDigits('1２3４5')).toBe('12345');
    });

    it('normalizes an empty string to an empty string', () => {
        expect(normalizeDigits('')).toBe('');
    });

    it('rejects a comma, period, space, or NBSP — grouping separators are not this function\'s job', () => {
        expect(normalizeDigits('100,000')).toBeNull();
        expect(normalizeDigits('100.000')).toBeNull();
        expect(normalizeDigits('100 000')).toBeNull();
        expect(normalizeDigits('100 000')).toBeNull();
    });

    it('rejects a string containing letters', () => {
        expect(normalizeDigits('100abc000')).toBeNull();
    });

    it('rejects any other symbol', () => {
        expect(normalizeDigits('-100000')).toBeNull();
        expect(normalizeDigits('100000円')).toBeNull();
    });
});
