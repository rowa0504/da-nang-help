import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { LocaleSwitcher } from '@/Components/LocaleSwitcher';

const mockUsePage = vi.fn();
const routerPatch = vi.fn();

vi.mock('@inertiajs/react', () => ({
    usePage: () => mockUsePage(),
    router: { patch: (...args: unknown[]) => routerPatch(...args) },
}));

function setLocale(locale: string) {
    mockUsePage.mockReturnValue({
        props: { locale, auth: { user: null }, flash: { status: null, warning: null } },
    });
}

const FIXED_LABELS = ['🇬🇧 English', '🇯🇵 日本語', '🇻🇳 Tiếng Việt'];

describe('LocaleSwitcher flag + native-language labels', () => {
    it.each(['en', 'ja', 'vi'])('shows all three options with fixed labels regardless of current locale (%s)', (locale) => {
        setLocale(locale);
        render(<LocaleSwitcher />);

        const options = screen.getAllByRole('option').map((o) => o.textContent);
        expect(options).toEqual(FIXED_LABELS);
    });

    it('marks the option matching the current locale as selected', () => {
        setLocale('ja');
        render(<LocaleSwitcher />);

        expect(screen.getByRole('combobox')).toHaveValue('ja');
    });

    it('calls the existing locale-switch handler when a different option is chosen', async () => {
        setLocale('en');
        render(<LocaleSwitcher />);

        await userEvent.selectOptions(screen.getByRole('combobox'), 'ja');

        expect(routerPatch).toHaveBeenCalledWith(
            '/locale',
            { locale: 'ja' },
            expect.objectContaining({ preserveScroll: true, preserveState: true }),
        );
    });

    it('keeps an accessible name for the select via the existing sr-only label', () => {
        setLocale('en');
        render(<LocaleSwitcher />);

        // useTranslation() is not mocked here — it reads the real en.ts
        // dictionary via the mocked usePage() locale, so the accessible
        // name is the actual translated "Language" label, not a raw key.
        expect(screen.getByRole('combobox', { name: 'Language' })).toBeInTheDocument();
    });
});
