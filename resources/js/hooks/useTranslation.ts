import { usePage } from '@inertiajs/react';
import { SharedProps, SupportedLocale } from '@/types';
import en, { TranslationKey } from '@/lang/en';
import ja from '@/lang/ja';
import vi from '@/lang/vi';

type TranslationDictionary = Record<TranslationKey, string>;

const dictionaries: Record<SupportedLocale, TranslationDictionary> = { en, ja, vi };

export function useTranslation() {
    const { locale } = usePage<SharedProps>().props;
    const dict = dictionaries[locale] ?? dictionaries.en;

    function t(key: TranslationKey, params?: Record<string, string | number>): string {
        let text: string = dict[key];
        if (params) {
            for (const [param, value] of Object.entries(params)) {
                // The same placeholder can appear more than once in a
                // string (e.g. a name used twice), so replaceAll — not
                // replace — is required.
                text = text.replaceAll(`{${param}}`, String(value));
            }
        }
        return text;
    }

    return { t, locale };
}
