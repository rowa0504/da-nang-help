import { describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import Home from '@/Pages/Home';
import { AuthUser, SharedProps } from '@/types';

let mockProps: SharedProps;

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: mockProps, url: '/' }),
    Head: () => null,
    Link: ({ href, children, ...rest }: Record<string, unknown> & { href: string; children: React.ReactNode }) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
    router: { patch: vi.fn(), on: vi.fn(() => () => {}) },
}));

function setProps(user: AuthUser | null) {
    mockProps = {
        auth: { user },
        flash: { status: null, warning: null },
        locale: 'en',
    };
}

describe('Home', () => {
    // Scoped to the hero section specifically: AppLayout's own nav also
    // has a "Post a request" link for a logged-in Customer (a different,
    // legitimately-present element with the same text) — an unscoped
    // query would incorrectly also match that nav link.
    it('shows registration CTAs for a guest, pointing at the correct roles', () => {
        setProps(null);
        render(<Home categories={[]} />);
        const hero = within(screen.getByTestId('hero'));

        expect(hero.getByRole('link', { name: 'Post a request' })).toHaveAttribute('href', '/register');
        expect(hero.getByRole('link', { name: 'Become a provider' })).toHaveAttribute('href', '/register?role=provider');
    });

    it('does not show the registration CTAs for a logged-in user, showing a dashboard link instead', () => {
        setProps({ id: 1, name: 'Casey', email: 'c@example.test', role: 'customer', locale: 'en' });
        render(<Home categories={[]} />);
        const hero = within(screen.getByTestId('hero'));

        expect(hero.queryByRole('link', { name: 'Post a request' })).not.toBeInTheDocument();
        expect(hero.queryByRole('link', { name: 'Become a provider' })).not.toBeInTheDocument();
        expect(hero.getByText('Welcome back, Casey')).toBeInTheDocument();
        expect(hero.getByRole('link', { name: 'Go to dashboard' })).toHaveAttribute('href', '/dashboard');
    });

    it.each([
        { id: 2, name: 'Provi', email: 'p@example.test', role: 'provider' as const, locale: 'en' },
        { id: 3, name: 'Addy', email: 'a@example.test', role: 'admin' as const, locale: 'en' },
    ])('shows the dashboard link (not the CTAs) for a logged-in $role', (user) => {
        setProps(user);
        render(<Home categories={[]} />);
        const hero = within(screen.getByTestId('hero'));

        expect(hero.queryByRole('link', { name: 'Post a request' })).not.toBeInTheDocument();
        expect(hero.getByRole('link', { name: 'Go to dashboard' })).toBeInTheDocument();
    });

    it('always links "How it works" to the absolute Home anchor, regardless of auth state', () => {
        setProps(null);
        const { rerender } = render(<Home categories={[]} />);
        expect(screen.getByRole('link', { name: 'How it works' })).toHaveAttribute('href', '/#how-it-works');

        setProps({ id: 1, name: 'Casey', email: 'c@example.test', role: 'customer', locale: 'en' });
        rerender(<Home categories={[]} />);
        expect(screen.getByRole('link', { name: 'How it works' })).toHaveAttribute('href', '/#how-it-works');
    });

    it('renders the given categories', () => {
        setProps(null);
        render(<Home categories={[{ id: 1, slug: 'cleaning', name: 'Cleaning' }]} />);
        expect(screen.getByText('Cleaning')).toBeInTheDocument();
    });
});
