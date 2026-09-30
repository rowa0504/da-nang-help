import { describe, expect, it } from 'vitest';
import { parseGroupedAmount } from '@/lib/parseGroupedAmount';

describe('parseGroupedAmount — accepted shapes', () => {
    it('accepts a bare digit string with no separator at all', () => {
        expect(parseGroupedAmount('100000')).toBe('100000');
    });

    it('accepts an empty string — clearing the field entirely is not an ambiguous/rejected value', () => {
        expect(parseGroupedAmount('')).toBe('');
    });

    it('accepts a correct comma thousands grouping', () => {
        expect(parseGroupedAmount('100,000')).toBe('100000');
    });

    it('accepts a correct period thousands grouping', () => {
        expect(parseGroupedAmount('100.000')).toBe('100000');
    });

    it('accepts multiple repeated groups (comma)', () => {
        expect(parseGroupedAmount('1,000,000')).toBe('1000000');
    });

    it('accepts multiple repeated groups (period)', () => {
        expect(parseGroupedAmount('1.000.000')).toBe('1000000');
    });

    it('accepts full-width digits, with or without a grouping separator', () => {
        expect(parseGroupedAmount('１０００００')).toBe('100000');
        expect(parseGroupedAmount('１，０００')).toBeNull(); // full-width comma "，" is not a recognized separator
        expect(parseGroupedAmount('１,０００')).toBe('1000'); // half-width "," is
    });

    it('accepts a 1- or 2-digit leading group, not just 3', () => {
        expect(parseGroupedAmount('1,000')).toBe('1000');
        expect(parseGroupedAmount('12,000')).toBe('12000');
    });
});

describe('parseGroupedAmount — rejected as ambiguous, never coerced', () => {
    it('rejects an incomplete 2-digit final group (e.g. "100.00") rather than turning it into 10000', () => {
        expect(parseGroupedAmount('100.00')).toBeNull();
        expect(parseGroupedAmount('100,00')).toBeNull();
    });

    it('rejects a 1-digit final group (e.g. "1.5")', () => {
        expect(parseGroupedAmount('1.5')).toBeNull();
    });

    it('rejects a 2-digit final group on a 2-digit leading amount (e.g. "12.34")', () => {
        expect(parseGroupedAmount('12.34')).toBeNull();
    });

    it('rejects comma and period mixed together in the same value', () => {
        expect(parseGroupedAmount('1,000.000')).toBeNull();
    });

    it('rejects a leading group longer than 3 digits', () => {
        expect(parseGroupedAmount('12345,678')).toBeNull();
    });

    it('rejects a trailing separator with nothing after it', () => {
        expect(parseGroupedAmount('100,')).toBeNull();
    });

    it('rejects letters mixed into the value, grouped or not', () => {
        expect(parseGroupedAmount('100abc000')).toBeNull();
        expect(parseGroupedAmount('100,abc')).toBeNull();
    });

    it('rejects any other symbol', () => {
        expect(parseGroupedAmount('-100,000')).toBeNull();
    });
});
