import { describe, expect, it, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useConfirm } from '@/hooks/useConfirm';

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { locale: 'en', auth: { user: null }, flash: { status: null, warning: null } }, url: '/' }),
    Link: ({ href, children, ...rest }: Record<string, unknown> & { href: string; children: React.ReactNode }) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
    router: { on: vi.fn(() => () => {}) },
}));

function Harness({ onResult }: { onResult: (value: boolean) => void }) {
    const { confirm, confirmDialog } = useConfirm();
    return (
        <div>
            <button onClick={async () => onResult(await confirm({ title: 'Confirm', body: 'Are you sure?' }))}>Trigger</button>
            {confirmDialog}
        </div>
    );
}

describe('useConfirm', () => {
    it('resolves true when the user clicks Confirm', async () => {
        const onResult = vi.fn();
        render(<Harness onResult={onResult} />);
        await userEvent.click(screen.getByRole('button', { name: 'Trigger' }));
        await userEvent.click(screen.getByRole('button', { name: 'Confirm' }));
        expect(onResult).toHaveBeenCalledWith(true);
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('resolves false when the user clicks Cancel', async () => {
        const onResult = vi.fn();
        render(<Harness onResult={onResult} />);
        await userEvent.click(screen.getByRole('button', { name: 'Trigger' }));
        await userEvent.click(screen.getByRole('button', { name: 'Cancel' }));
        expect(onResult).toHaveBeenCalledWith(false);
    });

    it('resolves false when Escape is pressed', async () => {
        const onResult = vi.fn();
        render(<Harness onResult={onResult} />);
        await userEvent.click(screen.getByRole('button', { name: 'Trigger' }));
        fireEvent.keyDown(document, { key: 'Escape' });
        await waitFor(() => expect(onResult).toHaveBeenCalledWith(false));
    });

    it('resolves the earlier pending confirm() with false when confirm() is called again before it settles', async () => {
        const results: boolean[] = [];

        function DoubleHarness() {
            const { confirm, confirmDialog } = useConfirm();
            return (
                <div>
                    <button
                        onClick={() => {
                            confirm({ title: 'A', body: 'a' }).then((v) => results.push(v));
                            confirm({ title: 'B', body: 'b' }).then((v) => results.push(v));
                        }}
                    >
                        Trigger
                    </button>
                    {confirmDialog}
                </div>
            );
        }

        render(<DoubleHarness />);
        await userEvent.click(screen.getByRole('button', { name: 'Trigger' }));

        expect(results).toEqual([false]);
        expect(screen.getByRole('dialog')).toHaveAccessibleName('B');
    });

    it('resolves a pending confirm() with false when the owning component unmounts', async () => {
        const results: boolean[] = [];

        function UnmountHarness() {
            const { confirm, confirmDialog } = useConfirm();
            return (
                <div>
                    <button onClick={() => confirm({ title: 'A', body: 'a' }).then((v) => results.push(v))}>Trigger</button>
                    {confirmDialog}
                </div>
            );
        }

        const { unmount } = render(<UnmountHarness />);
        await userEvent.click(screen.getByRole('button', { name: 'Trigger' }));
        unmount();
        await Promise.resolve();

        expect(results).toEqual([false]);
    });
});
