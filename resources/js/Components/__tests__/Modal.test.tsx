import { describe, expect, it, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { Modal } from '@/Components/Modal';

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { locale: 'en', auth: { user: null }, flash: { status: null, warning: null } }, url: '/' }),
    Link: ({ href, children, ...rest }: Record<string, unknown> & { href: string; children: React.ReactNode }) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
    router: { on: vi.fn(() => () => {}) },
}));

function Harness({ informational = false }: { informational?: boolean }) {
    const [open, setOpen] = useState(false);
    return (
        <div>
            <button onClick={() => setOpen(true)}>Open</button>
            <Modal open={open} title="Confirm" onClose={() => setOpen(false)} onConfirm={informational ? undefined : () => setOpen(false)}>
                Body text
                <input type="text" aria-label="inside" />
            </Modal>
        </div>
    );
}

describe('Modal', () => {
    it('focuses the first focusable element in the panel when opened', async () => {
        render(<Harness />);
        await userEvent.click(screen.getByRole('button', { name: 'Open' }));
        expect(screen.getByLabelText('inside')).toHaveFocus();
    });

    it('closes on Escape', async () => {
        render(<Harness />);
        await userEvent.click(screen.getByRole('button', { name: 'Open' }));
        fireEvent.keyDown(document, { key: 'Escape' });
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('restores focus to the trigger element after closing', async () => {
        render(<Harness />);
        const openButton = screen.getByRole('button', { name: 'Open' });
        await userEvent.click(openButton);
        fireEvent.keyDown(document, { key: 'Escape' });
        expect(openButton).toHaveFocus();
    });

    it('locks body scroll while open and restores it after closing', async () => {
        render(<Harness />);
        expect(document.body.style.overflow).not.toBe('hidden');
        await userEvent.click(screen.getByRole('button', { name: 'Open' }));
        expect(document.body.style.overflow).toBe('hidden');
        fireEvent.keyDown(document, { key: 'Escape' });
        expect(document.body.style.overflow).not.toBe('hidden');
    });

    it('traps Tab, wrapping from the last focusable element back to the first', async () => {
        render(<Harness />);
        await userEvent.click(screen.getByRole('button', { name: 'Open' }));
        screen.getByRole('button', { name: 'Confirm' }).focus();
        fireEvent.keyDown(document, { key: 'Tab' });
        expect(screen.getByLabelText('inside')).toHaveFocus();
    });

    it('traps Shift+Tab, wrapping from the first focusable element back to the last', async () => {
        render(<Harness />);
        await userEvent.click(screen.getByRole('button', { name: 'Open' }));
        screen.getByLabelText('inside').focus();
        fireEvent.keyDown(document, { key: 'Tab', shiftKey: true });
        expect(screen.getByRole('button', { name: 'Confirm' })).toHaveFocus();
    });

    it('closes when the backdrop itself is clicked', async () => {
        render(<Harness />);
        await userEvent.click(screen.getByRole('button', { name: 'Open' }));
        const backdrop = screen.getByRole('dialog').parentElement!;
        fireEvent.click(backdrop);
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('does not close when clicking inside the panel', async () => {
        render(<Harness />);
        await userEvent.click(screen.getByRole('button', { name: 'Open' }));
        fireEvent.click(screen.getByRole('dialog'));
        expect(screen.getByRole('dialog')).toBeInTheDocument();
    });

    it('renders a single Close button in informational mode (no onConfirm)', async () => {
        render(<Harness informational />);
        await userEvent.click(screen.getByRole('button', { name: 'Open' }));
        expect(screen.getByRole('button', { name: 'Close' })).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Confirm' })).not.toBeInTheDocument();
    });
});
