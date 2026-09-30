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
    //
    // VND gets a hand-built format rather than Intl's built-in currency
    // formatting: `style: 'currency'` renders "₫100,000" for en/ja (the ₫
    // symbol, not the "VND" code) and `currencyDisplay: 'code'` puts the
    // code *before* the number ("VND 100,000") — neither matches the
    // product's chosen convention of "100,000 VND" (en/ja) / "100.000 ₫"
    // (vi). A pre-existing non-VND Offer/Job (legacy USD data from before
    // the MVP's VND-only policy) keeps using Intl's generic currency
    // formatting unchanged, exactly as it always has.
    function formatCurrency(amount: string | number, currency: string): string {
        if (currency === 'VND') {
            const grouped = new Intl.NumberFormat(locale, { maximumFractionDigits: 0 }).format(Number(amount));
            return locale === 'vi' ? `${grouped} ₫` : `${grouped} VND`;
        }
        return new Intl.NumberFormat(locale, { style: 'currency', currency }).format(Number(amount));
    }

    return { formatDate, formatDateTime, formatCurrency };
}
