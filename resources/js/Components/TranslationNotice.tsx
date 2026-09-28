import { Badge } from '@/Components/Badge';
import { MachineTranslationStatus, SupportedLocale } from '@/types';
import { useTranslation } from '@/hooks/useTranslation';

interface Props {
    // null: nothing to show (viewer's locale matches the source locale, or
    // no translation row exists yet) — renders nothing at all.
    status: MachineTranslationStatus | null;
    sourceLocale: string;
    showOriginal: boolean;
    onToggle: () => void;
}

// Resolves a BCP-47 language subtag to a human-readable name in the
// viewer's own UI language, via the platform's Intl API rather than a
// hand-maintained lookup table — so a future locale (ko, zh-CN, ru, or any
// other) is named correctly with zero code changes here. Falls back to the
// raw code if the platform can't resolve it (unknown tag, or an
// environment without full ICU data).
function localeDisplayName(locale: string, uiLocale: SupportedLocale): string {
    try {
        return new Intl.DisplayNames([uiLocale], { type: 'language' }).of(locale) ?? locale;
    } catch {
        return locale;
    }
}

// Renders the machine-translation status/toggle UI alongside a piece of
// text — never the text itself (the caller owns that, since callers differ
// in whether one toggle covers one field or several). Exactly one of four
// mutually exclusive states:
// - status === null: same locale, or no translation row yet — nothing to show.
// - 'pending': original is being shown; translation isn't ready — no toggle.
// - 'failed': original is being shown; translation is unavailable — no toggle.
// - 'completed': a real toggle between translated/original, with the
//   Badge shown only while the translation is the one on screen.
export function TranslationNotice({ status, sourceLocale, showOriginal, onToggle }: Props) {
    const { t, locale } = useTranslation();

    if (status === null) {
        return null;
    }

    if (status === 'pending') {
        return <span className="ml-2 text-xs text-gray-500">{t('translation.pending')}</span>;
    }

    if (status === 'failed') {
        return <span className="ml-2 text-xs text-gray-500">{t('translation.failed')}</span>;
    }

    return (
        <span className="ml-2 inline-flex items-center gap-2 align-middle">
            {showOriginal ? (
                <span className="text-xs text-gray-500">
                    {t('translation.original_label', { locale: localeDisplayName(sourceLocale, locale) })}
                </span>
            ) : (
                <Badge variant="info">{t('translation.machine_translated')}</Badge>
            )}
            <button
                type="button"
                aria-pressed={showOriginal}
                onClick={onToggle}
                className="rounded border border-gray-300 bg-white px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
            >
                {showOriginal ? t('translation.show_translation') : t('translation.show_original')}
            </button>
        </span>
    );
}
