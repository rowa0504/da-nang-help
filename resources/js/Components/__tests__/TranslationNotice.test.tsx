import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { TranslationNotice } from '@/Components/TranslationNotice';

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { locale: 'en', auth: { user: null }, flash: { status: null, warning: null } } }),
}));

describe('TranslationNotice', () => {
    it('renders nothing when status is null (same locale, or no translation row)', () => {
        const { container } = render(
            <TranslationNotice status={null} sourceLocale="en" showOriginal={false} onToggle={vi.fn()} />,
        );
        expect(container).toBeEmptyDOMElement();
    });

    it('shows "in progress" and no button/badge while pending', () => {
        render(<TranslationNotice status="pending" sourceLocale="en" showOriginal={false} onToggle={vi.fn()} />);

        expect(screen.getByText('Translation in progress')).toBeInTheDocument();
        expect(screen.queryByRole('button')).not.toBeInTheDocument();
        expect(screen.queryByText('(machine-translated)')).not.toBeInTheDocument();
    });

    it('shows "unavailable" and no button/badge when failed', () => {
        render(<TranslationNotice status="failed" sourceLocale="en" showOriginal={false} onToggle={vi.fn()} />);

        expect(screen.getByText('Translation unavailable')).toBeInTheDocument();
        expect(screen.queryByRole('button')).not.toBeInTheDocument();
        expect(screen.queryByText('(machine-translated)')).not.toBeInTheDocument();
    });

    it('shows the badge and a "Show original" button while displaying the translation (completed, showOriginal=false)', () => {
        render(<TranslationNotice status="completed" sourceLocale="en" showOriginal={false} onToggle={vi.fn()} />);

        expect(screen.getByText('(machine-translated)')).toBeInTheDocument();
        const button = screen.getByRole('button', { name: 'Show original' });
        expect(button).toHaveAttribute('type', 'button');
        expect(button).toHaveAttribute('aria-pressed', 'false');
    });

    it('hides the badge and shows "Original · {language}" while displaying the original (completed, showOriginal=true)', () => {
        render(<TranslationNotice status="completed" sourceLocale="en" showOriginal={true} onToggle={vi.fn()} />);

        expect(screen.queryByText('(machine-translated)')).not.toBeInTheDocument();
        expect(screen.getByText('Original · English')).toBeInTheDocument();
        const button = screen.getByRole('button', { name: 'Show translation' });
        expect(button).toHaveAttribute('aria-pressed', 'true');
    });

    it('resolves a not-yet-supported locale (e.g. a future ko/zh-CN/ru) to a readable name via Intl, with no hardcoded lookup table', () => {
        render(<TranslationNotice status="completed" sourceLocale="ko" showOriginal={true} onToggle={vi.fn()} />);

        expect(screen.getByText('Original · Korean')).toBeInTheDocument();
    });

    it('falls back to the raw locale code when the tag is too malformed for Intl to resolve at all', () => {
        render(<TranslationNotice status="completed" sourceLocale="12345" showOriginal={true} onToggle={vi.fn()} />);

        expect(screen.getByText('Original · 12345')).toBeInTheDocument();
    });

    it('calls onToggle on click and on keyboard activation (Enter/Space)', async () => {
        const user = userEvent.setup();
        const onToggle = vi.fn();
        render(<TranslationNotice status="completed" sourceLocale="en" showOriginal={false} onToggle={onToggle} />);

        const button = screen.getByRole('button', { name: 'Show original' });
        await user.click(button);
        expect(onToggle).toHaveBeenCalledTimes(1);

        button.focus();
        await user.keyboard('{Enter}');
        expect(onToggle).toHaveBeenCalledTimes(2);

        await user.keyboard(' ');
        expect(onToggle).toHaveBeenCalledTimes(3);
    });
});
