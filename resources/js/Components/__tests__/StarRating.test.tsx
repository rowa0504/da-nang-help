import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { StarRating } from '@/Components/StarRating';

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { locale: 'en', auth: { user: null }, flash: { status: null, warning: null } }, url: '/' }),
}));

function InteractiveHarness({ initial = 3 }: { initial?: number }) {
    const [value, setValue] = useState(initial);
    return <StarRating value={value} onChange={setValue} ariaLabel="Rating" />;
}

describe('StarRating', () => {
    it('exposes a labelled radiogroup and calls onChange when a star is clicked', async () => {
        const onChange = vi.fn();
        render(<StarRating value={3} onChange={onChange} ariaLabel="Rating" />);
        expect(screen.getByRole('radiogroup', { name: 'Rating' })).toBeInTheDocument();
        await userEvent.click(screen.getByRole('radio', { name: '5 star(s)' }));
        expect(onChange).toHaveBeenCalledWith(5);
    });

    it('moves selection and focus with the arrow keys', async () => {
        render(<InteractiveHarness />);
        screen.getByRole('radio', { name: '3 star(s)' }).focus();

        await userEvent.keyboard('{ArrowRight}');
        expect(screen.getByRole('radio', { name: '4 star(s)' })).toHaveFocus();
        expect(screen.getByRole('radio', { name: '4 star(s)' })).toHaveAttribute('aria-checked', 'true');

        await userEvent.keyboard('{ArrowDown}');
        expect(screen.getByRole('radio', { name: '5 star(s)' })).toHaveFocus();

        await userEvent.keyboard('{ArrowLeft}');
        expect(screen.getByRole('radio', { name: '4 star(s)' })).toHaveFocus();

        await userEvent.keyboard('{ArrowUp}');
        expect(screen.getByRole('radio', { name: '3 star(s)' })).toHaveFocus();
    });

    it('moves to the first/last star with Home and End', async () => {
        render(<InteractiveHarness />);
        screen.getByRole('radio', { name: '3 star(s)' }).focus();

        await userEvent.keyboard('{Home}');
        expect(screen.getByRole('radio', { name: '1 star(s)' })).toHaveFocus();

        await userEvent.keyboard('{End}');
        expect(screen.getByRole('radio', { name: '5 star(s)' })).toHaveFocus();
    });

    it('does not move past the lower boundary', async () => {
        render(<InteractiveHarness initial={1} />);
        screen.getByRole('radio', { name: '1 star(s)' }).focus();
        await userEvent.keyboard('{ArrowLeft}');
        expect(screen.getByRole('radio', { name: '1 star(s)' })).toHaveAttribute('aria-checked', 'true');
    });

    it('renders no interactive elements in read-only mode', () => {
        render(<StarRating value={4} readOnly />);
        expect(screen.queryAllByRole('radio')).toHaveLength(0);
        expect(screen.queryAllByRole('button')).toHaveLength(0);
    });
});
