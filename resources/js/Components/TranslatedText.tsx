import { useState } from 'react';
import { TranslationMeta } from '@/types';
import { useTranslation } from '@/hooks/useTranslation';

interface Props {
    translation: TranslationMeta;
    translated: string;
}

// Renders the resolved (server-side translated) text, plus — only when a
// machine translation actually exists — a badge and an original/translated
// toggle. No extra request: both strings already arrived in the response.
export function TranslatedText({ translation, translated }: Props) {
    const { t } = useTranslation();
    const [showOriginal, setShowOriginal] = useState(false);
    const text = showOriginal ? translation.original : translated;

    return (
        <span>
            {text}
            {translation.is_translated && (
                <>
                    {' '}
                    <span className="text-xs text-gray-500">{t('translation.machine_translated')}</span>{' '}
                    <button type="button" onClick={() => setShowOriginal((v) => !v)} className="text-xs underline">
                        {showOriginal ? t('translation.show_translation') : t('translation.show_original')}
                    </button>
                </>
            )}
        </span>
    );
}
