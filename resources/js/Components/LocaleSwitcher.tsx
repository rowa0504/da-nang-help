import { ChangeEvent, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { SharedProps, SupportedLocale } from '@/types';
import { useTranslation } from '@/hooks/useTranslation';

// Explicit, exhaustive correspondence table — deliberately not a cast of
// the raw <select> value to SupportedLocale, so an unexpected option value
// can never be smuggled through as a supported locale.
const LOCALE_OPTIONS: { value: SupportedLocale; labelKey: 'language.en' | 'language.ja' | 'language.vi' }[] = [
    { value: 'en', labelKey: 'language.en' },
    { value: 'ja', labelKey: 'language.ja' },
    { value: 'vi', labelKey: 'language.vi' },
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
                        {t(option.labelKey)}
                    </option>
                ))}
            </select>
        </label>
    );
}
