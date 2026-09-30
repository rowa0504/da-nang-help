import { ChangeEvent, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { SharedProps, SupportedLocale } from '@/types';
import { useTranslation } from '@/hooks/useTranslation';

// Each option is always shown in its own language (never translated into
// the current UI locale) so a reader can find their target language even
// after accidentally switching to one they can't read. Deliberately not
// sourced from the language.en/ja/vi dictionary keys — those remain in use
// for the Request/Offer source_locale pickers, which *do* want the
// current-UI-locale translation (see Show.tsx's SOURCE_LOCALE_OPTIONS).
const LOCALE_OPTIONS: { value: SupportedLocale; label: string }[] = [
    { value: 'en', label: '🇬🇧 English' },
    { value: 'ja', label: '🇯🇵 日本語' },
    { value: 'vi', label: '🇻🇳 Tiếng Việt' },
];

function isSupportedLocale(value: string): value is SupportedLocale {
    return LOCALE_OPTIONS.some((option) => option.value === value);
}

export function LocaleSwitcher() {
    const { locale } = usePage<SharedProps>().props;
    const { t } = useTranslation();
    const [switching, setSwitching] = useState(false);

    function onChange(e: ChangeEvent<HTMLSelectElement>) {
        const next = e.target.value;
        if (switching || !isSupportedLocale(next) || next === locale) {
            return;
        }
        // Disabled for the duration of the request so a second change
        // event (e.g. a fast double-click on a native <select>) can't fire
        // a second PATCH before the first one lands.
        setSwitching(true);
        router.patch(
            '/locale',
            { locale: next },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setSwitching(false),
            },
        );
    }

    return (
        <label className="flex items-center gap-2 text-sm text-gray-700">
            <span className="sr-only">{t('locale.switcher_label')}</span>
            <select
                value={locale}
                onChange={onChange}
                disabled={switching}
                className="rounded border border-gray-300 px-2 py-1 text-sm disabled:cursor-not-allowed disabled:opacity-50"
            >
                {LOCALE_OPTIONS.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </label>
    );
}
