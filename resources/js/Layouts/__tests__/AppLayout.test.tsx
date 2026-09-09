import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { AppLayout } from '@/Layouts/AppLayout';
import { AuthUser, SharedProps } from '@/types';

let mockProps: SharedProps;

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: mockProps, url: '/' }),
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

describe('AppLayout', () => {
    it('renders without error for a guest, with no role nav items and login/register links', () => {
        setProps(null);
        render(<AppLayout>content</AppLayout>);

        expect(screen.getByText('content')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Log in' })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Register' })).toBeInTheDocument();
        expect(screen.queryByRole('link', { name: 'Dashboard' })).not.toBeInTheDocument();
    });

    it('renders the customer nav items', () => {
        setProps({ id: 1, name: 'Casey', email: 'c@example.test', role: 'customer', locale: 'en' });
        render(<AppLayout>content</AppLayout>);

        expect(screen.getByRole('link', { name: 'Dashboard' })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Post a request' })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'My requests' })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'My jobs' })).toBeInTheDocument();
    });

    it('renders the provider nav items', () => {
        setProps({ id: 2, name: 'Provi', email: 'p@example.test', role: 'provider', locale: 'en' });
        render(<AppLayout>content</AppLayout>);

        expect(screen.getByRole('link', { name: 'Request Feed' })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'My jobs' })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Profile' })).toBeInTheDocument();
    });

    it('renders the admin nav items', () => {
        setProps({ id: 3, name: 'Addy', email: 'a@example.test', role: 'admin', locale: 'en' });
        render(<AppLayout>content</AppLayout>);

        expect(screen.getByRole('link', { name: 'Providers' })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Requests' })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Reviews' })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Categories' })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Areas' })).toBeInTheDocument();
    });
});
