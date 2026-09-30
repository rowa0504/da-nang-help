import { describe, expect, it, vi } from 'vitest';
import { renderHook } from '@testing-library/react';
import { useLocaleFormat } from '@/hooks/useLocaleFormat';

const mockUsePage = vi.fn();

vi.mock('@inertiajs/react', () => ({
    usePage: () => mockUsePage(),
}));

function setLocale(locale: string) {
    mockUsePage.mockReturnValue({ props: { locale } });
}

describe('useLocaleFormat formatCurrency — VND', () => {
    it('formats VND as "N,NNN VND" for en', () => {
        setLocale('en');
        const { result } = renderHook(() => useLocaleFormat());
        expect(result.current.formatCurrency('100000', 'VND')).toBe('100,000 VND');
    });

    it('formats VND as "N,NNN VND" for ja', () => {
        setLocale('ja');
        const { result } = renderHook(() => useLocaleFormat());
        expect(result.current.formatCurrency('100000', 'VND')).toBe('100,000 VND');
    });

    it('formats VND as "N.NNN ₫" for vi', () => {
        setLocale('vi');
        const { result } = renderHook(() => useLocaleFormat());
        expect(result.current.formatCurrency('100000', 'VND')).toBe('100.000 ₫');
    });

    it('accepts a numeric amount as well as a string', () => {
        setLocale('en');
        const { result } = renderHook(() => useLocaleFormat());
        expect(result.current.formatCurrency(100000, 'VND')).toBe('100,000 VND');
    });
});

describe('useLocaleFormat formatCurrency — legacy non-VND backward compatibility', () => {
    it('keeps formatting a non-VND currency (e.g. USD) via the generic Intl currency formatter, unchanged', () => {
        setLocale('en');
        const { result } = renderHook(() => useLocaleFormat());
        // Intl's own currency-symbol placement/grouping for USD — this must
        // keep behaving exactly as it did before the VND-only change, since
        // legacy non-VND Offers/Jobs are never auto-converted or rewritten.
        expect(result.current.formatCurrency('120', 'USD')).toBe(
            new Intl.NumberFormat('en', { style: 'currency', currency: 'USD' }).format(120),
        );
    });
});
