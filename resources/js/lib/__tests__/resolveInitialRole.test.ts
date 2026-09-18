import { describe, expect, it } from 'vitest';
import { resolveInitialRole } from '@/lib/resolveInitialRole';

describe('resolveInitialRole', () => {
    it('returns provider when the role query param is exactly "provider"', () => {
        expect(resolveInitialRole('?role=provider')).toBe('provider');
    });

    it('falls back to customer for an explicit customer value', () => {
        expect(resolveInitialRole('?role=customer')).toBe('customer');
    });

    it('falls back to customer for an unrecognized value', () => {
        expect(resolveInitialRole('?role=admin')).toBe('customer');
    });

    it('falls back to customer when there is no query string', () => {
        expect(resolveInitialRole('')).toBe('customer');
    });

    it('falls back to customer when role is not present among other params', () => {
        expect(resolveInitialRole('?foo=bar')).toBe('customer');
    });
});
