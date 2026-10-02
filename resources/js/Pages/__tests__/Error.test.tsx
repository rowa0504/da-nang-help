import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import Error from '@/Pages/Error';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ href, children }: Record<string, unknown> & { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
    usePage: () => ({
        url: '/requests',
        props: { locale: 'en', auth: { user: null }, flash: { status: null, warning: null } },
    }),
    router: { on: vi.fn(() => () => {}) },
}));

describe('Error page', () => {
    it('renders the status code and the server-localized message', () => {
        render(<Error status={429} message="You have made too many requests. Please try again in 42 seconds." retryAfter={42} />);

        expect(screen.getByText('429')).toBeInTheDocument();
        expect(screen.getByText('You have made too many requests. Please try again in 42 seconds.')).toBeInTheDocument();
    });

    it('renders a message in any locale as-is, without re-translating it client-side', () => {
        render(<Error status={429} message="リクエストが多すぎます。42秒後に再度お試しください。" retryAfter={42} />);

        expect(screen.getByText('リクエストが多すぎます。42秒後に再度お試しください。')).toBeInTheDocument();
    });
});
