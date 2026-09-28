import { describe, expect, it, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { TranslatedText } from '@/Components/TranslatedText';
import { TranslationMeta } from '@/types';

const mockUsePage = vi.fn();

vi.mock('@inertiajs/react', () => ({
    usePage: () => mockUsePage(),
}));

function setLocale(locale: string) {
    mockUsePage.mockReturnValue({ props: { locale, auth: { user: null }, flash: { status: null, warning: null } } });
}

const completedEn: TranslationMeta = { status: 'completed', source_locale: 'en', original: 'Hello' };

describe('TranslatedText', () => {
    it('shows the translated text by default', () => {
        setLocale('en');
        render(<TranslatedText id={1} translation={completedEn} translated="[ja] Hello" />);

        expect(screen.getByText('[ja] Hello')).toBeInTheDocument();
    });

    it('resets to showing the translation when the id prop changes', () => {
        setLocale('en');
        const { rerender } = render(<TranslatedText id={1} translation={completedEn} translated="[ja] Hello" />);

        fireEvent.click(screen.getByRole('button', { name: 'Show original' }));
        expect(screen.getByText('Hello')).toBeInTheDocument();

        const otherTranslation: TranslationMeta = { status: 'completed', source_locale: 'en', original: 'Goodbye' };
        rerender(<TranslatedText id={2} translation={otherTranslation} translated="[ja] Goodbye" />);

        expect(screen.getByText('[ja] Goodbye')).toBeInTheDocument();
        expect(screen.queryByText('Goodbye')).not.toBeInTheDocument();
    });

    it('resets to showing the translation when the viewer locale changes', () => {
        setLocale('en');
        const { rerender } = render(<TranslatedText id={1} translation={completedEn} translated="[ja] Hello" />);

        fireEvent.click(screen.getByRole('button', { name: 'Show original' }));
        expect(screen.getByText('Hello')).toBeInTheDocument();

        setLocale('vi');
        const viTranslation: TranslationMeta = { status: 'completed', source_locale: 'en', original: 'Hello' };
        rerender(<TranslatedText id={1} translation={viTranslation} translated="[vi] Hello" />);

        expect(screen.getByText('[vi] Hello')).toBeInTheDocument();
    });

    it('resets to showing the translation when the status transitions (e.g. pending to completed)', () => {
        setLocale('en');
        const pending: TranslationMeta = { status: 'pending', source_locale: 'en', original: 'Hello' };
        const { rerender } = render(<TranslatedText id={1} translation={pending} translated="Hello" />);

        expect(screen.getByText('Translation in progress')).toBeInTheDocument();

        rerender(<TranslatedText id={1} translation={completedEn} translated="[ja] Hello" />);

        expect(screen.getByText('[ja] Hello')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Show original' })).toBeInTheDocument();
    });
});
