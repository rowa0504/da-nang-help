import { useState } from 'react';
import { TranslationMeta } from '@/types';
import { useTranslation } from '@/hooks/useTranslation';
import { Badge } from '@/Components/Badge';

interface Props {
    translation: TranslationMeta;
    translated: string;
}

// Renders the resolved (server-side translated) text, plus — only when a
// machine translation actually exists — a Badge and an original/translated
// toggle styled as a small secondary button (Phase 10A: previously plain
// gray text + an underlined link, easy to miss). No extra request: both
// strings already arrived in the response.
export function TranslatedText({ translation, translated }: Props) {
    const { t } = useTranslation();
    const [showOriginal, setShowOriginal] = useState(false);
    const text = showOriginal ? translation.original : translated;

    return (
        <span>
            {text}
            {translation.is_translated && (
                <span className="ml-2 inline-flex items-center gap-2 align-middle">
                    <Badge variant="info">{t('translation.machine_translated')}</Badge>
                    <button
                        type="button"
                        onClick={() => setShowOriginal((v) => !v)}
                        className="rounded border border-gray-300 bg-white px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                    >
                        {showOriginal ? t('translation.show_translation') : t('translation.show_original')}
                    </button>
                </span>
            )}
        </span>
    );
}
