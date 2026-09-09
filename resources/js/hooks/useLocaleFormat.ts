import { usePage } from '@inertiajs/react';
import { SharedProps } from '@/types';

// SupportedLocale values ('en'/'ja'/'vi') are valid BCP-47 tags Intl accepts
// directly, so — unlike LocaleSwitcher's UI-label table — no mapping is
// needed here.
export function useLocaleFormat() {
    const { locale } = usePage<SharedProps>().props;

    function formatDate(iso: string): string {
        return new Intl.DateTimeFormat(locale, { dateStyle: 'medium' }).format(new Date(iso));
    }

    function formatDateTime(iso: string): string {
        return new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
    }

    // `currency` must always come from the domain data (OfferData.currency,
    // JobData.currency), never inferred from `locale` — a vi-locale user's
    // job can still be priced in USD.
    function formatCurrency(amount: string | number, currency: string): string {
        return new Intl.NumberFormat(locale, { style: 'currency', currency }).format(Number(amount));
    }

    return { formatDate, formatDateTime, formatCurrency };
}
