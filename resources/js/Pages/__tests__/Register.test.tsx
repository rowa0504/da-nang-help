import { describe, expect, it, vi, afterEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import Register from '@/Pages/Auth/Register';

vi.mock('@inertiajs/react', () => ({
    // Static — this test only checks the initial `data.role` selection
    // driven by resolveInitialRole(window.location.search), not live form
    // interaction, so useForm doesn't need real state.
    useForm: (initial: Record<string, unknown>) => ({
        data: initial,
        setData: vi.fn(),
        post: vi.fn(),
        processing: false,
        errors: {},
        reset: vi.fn(),
    }),
    Head: () => null,
    usePage: () => ({ props: { locale: 'en', auth: { user: null }, flash: { status: null, warning: null } }, url: '/register' }),
    Link: ({ href, children, ...rest }: Record<string, unknown> & { href: string; children: React.ReactNode }) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
    router: { patch: vi.fn(), on: vi.fn(() => () => {}) },
}));

afterEach(() => {
    window.history.pushState({}, '', '/register');
});

describe('Register', () => {
    it('defaults to the Customer role when there is no role query param', () => {
        window.history.pushState({}, '', '/register');
        render(<Register />);
        expect(screen.getByRole('radio', { name: 'Customer' })).toBeChecked();
        expect(screen.getByRole('radio', { name: 'Provider' })).not.toBeChecked();
    });

    it('pre-selects the Provider role when ?role=provider is present', () => {
        window.history.pushState({}, '', '/register?role=provider');
        render(<Register />);
        expect(screen.getByRole('radio', { name: 'Provider' })).toBeChecked();
        expect(screen.getByRole('radio', { name: 'Customer' })).not.toBeChecked();
    });

    it('falls back to Customer for an unrecognized role value', () => {
        window.history.pushState({}, '', '/register?role=admin');
        render(<Register />);
        expect(screen.getByRole('radio', { name: 'Customer' })).toBeChecked();
    });
});
