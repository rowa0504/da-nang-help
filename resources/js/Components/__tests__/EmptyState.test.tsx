import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { EmptyState } from '@/Components/EmptyState';

describe('EmptyState', () => {
    it('renders the title alone when nothing else is given', () => {
        render(<EmptyState title="Nothing here yet" />);
        expect(screen.getByText('Nothing here yet')).toBeInTheDocument();
    });

    it('renders description, icon, and note when provided', () => {
        render(
            <EmptyState
                icon={<span data-testid="empty-icon" />}
                title="Nothing here yet"
                description="Here is why, and what to do next."
                note="You do not have permission to do this."
            />,
        );
        expect(screen.getByTestId('empty-icon')).toBeInTheDocument();
        expect(screen.getByText('Here is why, and what to do next.')).toBeInTheDocument();
        expect(screen.getByText('You do not have permission to do this.')).toBeInTheDocument();
    });

    it('renders the action as a link when actionHref is given', () => {
        render(<EmptyState title="Nothing here yet" actionLabel="Do something" actionHref="/somewhere" />);
        const link = screen.getByRole('link', { name: 'Do something' });
        expect(link).toHaveAttribute('href', '/somewhere');
    });

    it('renders the action as a button and calls onAction when no actionHref is given', async () => {
        const onAction = vi.fn();
        render(<EmptyState title="Nothing here yet" actionLabel="Do something" onAction={onAction} />);
        await userEvent.click(screen.getByRole('button', { name: 'Do something' }));
        expect(onAction).toHaveBeenCalledTimes(1);
    });
});
