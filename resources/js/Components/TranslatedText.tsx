import { useEffect, useState } from 'react';
import { TranslationMeta } from '@/types';
import { useTranslation } from '@/hooks/useTranslation';
import { TranslationNotice } from '@/Components/TranslationNotice';

interface Props {
    // Identifies the entity this text belongs to (an Offer id, typically).
    // Included solely so the reset effect below can tell "the same field,
    // still mid-translation" apart from "an unrelated entity that happens
    // to render through the same mounted component" — e.g. a list re-sort
    // that reuses a DOM/component slot for a different row's data.
    id: number | string;
    translation: TranslationMeta;
    translated: string;
}

// Renders the resolved (server-side translated or original) text for a
// single field, plus its own machine-translation status/toggle UI. Owns
// its own showOriginal state — appropriate here because each usage (e.g.
// one Offer's message per list row) is independent of every other
// instance. For fields that must toggle together (a ServiceRequest's
// title + description), the parent lifts this same state itself instead
// of using this component — see Requests/Show.tsx.
export function TranslatedText({ id, translation, translated }: Props) {
    const { locale } = useTranslation();
    const [showOriginal, setShowOriginal] = useState(false);

    // Always land back on "show the translation" whenever the identity of
    // what's being displayed changes — the viewer's own locale, which
    // entity this is, or the translation's own status/source — so a stale
    // "I asked to see the original" choice from a moment ago can never
    // silently carry over onto different content.
    useEffect(() => {
        setShowOriginal(false);
    }, [id, locale, translation.status, translation.source_locale]);

    const text = translation.status === 'completed' && showOriginal ? translation.original : translated;

    return (
        <span>
            {text}
            <TranslationNotice
                status={translation.status}
                sourceLocale={translation.source_locale}
                showOriginal={showOriginal}
                onToggle={() => setShowOriginal((v) => !v)}
            />
        </span>
    );
}
