import { LegalDocument } from '@/legal/types';
import { SupportedLocale } from '@/types';
import { useTranslation } from '@/hooks/useTranslation';

interface Props {
    document: LegalDocument;
    operatorName: string | null;
    contactEmail: string | null;
    effectiveDate: string | null;
    governingLocale: SupportedLocale;
}

// How the governing locale's own language is named, keyed by the current
// *viewer's* locale — e.g. governingLocale 'vi' reads "Vietnamese" on the
// English page, "ベトナム語" on the Japanese page, and "tiếng Việt" on the
// Vietnamese page itself.
const LOCALE_NAMES: Record<SupportedLocale, Record<SupportedLocale, string>> = {
    vi: { en: 'Vietnamese', ja: 'ベトナム語', vi: 'tiếng Việt' },
    en: { en: 'English', ja: '英語', vi: 'tiếng Anh' },
    ja: { en: 'Japanese', ja: '日本語', vi: 'tiếng Nhật' },
};

function substitute(text: string, tokens: Record<string, string>): string {
    let result = text;
    for (const [key, value] of Object.entries(tokens)) {
        // A token can appear more than once in the same string (e.g. the
        // final section references {{governing_locale_language}} once,
        // but {{contact_email}} shows up in both documents' final
        // section), so replaceAll is required, not replace.
        result = result.replaceAll(`{{${key}}}`, value);
    }
    return result;
}

export function LegalDocumentView({ document, operatorName, contactEmail, effectiveDate, governingLocale }: Props) {
    const { t, locale } = useTranslation();

    const tokens = {
        operator_name: operatorName ?? '',
        contact_email: contactEmail ?? '',
        governing_locale_language: LOCALE_NAMES[governingLocale][locale],
    };

    return (
        <article className="mx-auto max-w-3xl p-4 sm:p-6">
            <h1 className="text-2xl font-bold text-gray-900">{document.title}</h1>
            <p className="mt-2 text-sm text-gray-600">
                {t('legal.operator_label')} {operatorName}
                {/* Deliberately hidden while effectiveDate is null (draft
                    stage) — see config/legal.php and
                    LegalConfigurationValidator. */}
                {effectiveDate && (
                    <>
                        {' '}
                        — {t('legal.effective_date_label')} {effectiveDate}
                    </>
                )}
            </p>

            {document.sections.map((section) => (
                <section key={section.heading} className="mt-6">
                    <h2 className="text-lg font-semibold text-gray-900">{section.heading}</h2>
                    {section.blocks.map((block, index) =>
                        block.type === 'p' ? (
                            <p key={index} className="mt-2 text-sm leading-relaxed text-gray-700">
                                {substitute(block.text, tokens)}
                            </p>
                        ) : (
                            <ul key={index} className="mt-2 list-disc space-y-1 pl-5 text-sm leading-relaxed text-gray-700">
                                {block.items.map((item, itemIndex) => (
                                    <li key={itemIndex}>{substitute(item, tokens)}</li>
                                ))}
                            </ul>
                        ),
                    )}
                </section>
            ))}
        </article>
    );
}
