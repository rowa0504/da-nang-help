import { describe, expect, it, vi } from 'vitest';
import { render, screen, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MobileNav } from '@/Components/MobileNav';

let navigateCallback: (() => void) | null = null;

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { locale: 'en', auth: { user: null }, flash: { status: null, warning: null } }, url: '/' }),
    Link: ({ href, children, ...rest }: Record<string, unknown> & { href: string; children: React.ReactNode }) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
    router: {
        patch: vi.fn(),
        on: (event: string, callback: () => void) => {
            if (event === 'navigate') {
                navigateCallback = callback;
            }
            return () => {
                navigateCallback = null;
            };
        },
    },
}));

const items = [
    { label: 'Dashboard', href: '/dashboard', active: true },
    { label: 'My requests', href: '/requests', active: false },
];

describe('MobileNav', () => {
    it('toggles the menu open and closed via the hamburger button', async () => {
        render(<MobileNav items={items} authUser={null} />);
        expect(screen.queryByRole('link', { name: 'Dashboard' })).not.toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: 'Menu' }));
        expect(screen.getByRole('link', { name: 'Dashboard' })).toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: 'Menu' }));
        expect(screen.queryByRole('link', { name: 'Dashboard' })).not.toBeInTheDocument();
    });

    it('closes after an Inertia navigation completes', async () => {
        render(<MobileNav items={items} authUser={null} />);
        await userEvent.click(screen.getByRole('button', { name: 'Menu' }));
        expect(screen.getByRole('link', { name: 'Dashboard' })).toBeInTheDocument();

        act(() => {
            navigateCallback?.();
        });

        expect(screen.queryByRole('link', { name: 'Dashboard' })).not.toBeInTheDocument();
    });

    it('closes when clicking outside the menu', async () => {
        render(
            <div>
                <button>Outside</button>
                <MobileNav items={items} authUser={null} />
            </div>,
        );
        await userEvent.click(screen.getByRole('button', { name: 'Menu' }));
        expect(screen.getByRole('link', { name: 'Dashboard' })).toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: 'Outside' }));
        expect(screen.queryByRole('link', { name: 'Dashboard' })).not.toBeInTheDocument();
    });
});
