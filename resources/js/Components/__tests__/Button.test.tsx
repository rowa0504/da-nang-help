import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Button } from '@/Components/Button';

describe('Button', () => {
    it('is disabled and shows aria-busy while loading', () => {
        render(<Button loading>Save</Button>);
        const button = screen.getByRole('button', { name: 'Save' });
        expect(button).toBeDisabled();
        expect(button).toHaveAttribute('aria-busy', 'true');
    });

    it('does not fire onClick while disabled', async () => {
        const onClick = vi.fn();
        render(
            <Button disabled onClick={onClick}>
                Save
            </Button>,
        );
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        expect(onClick).not.toHaveBeenCalled();
    });

    it('fires onClick when enabled', async () => {
        const onClick = vi.fn();
        render(<Button onClick={onClick}>Save</Button>);
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        expect(onClick).toHaveBeenCalledTimes(1);
    });
});
