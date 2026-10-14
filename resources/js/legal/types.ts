// Content for Terms.tsx/Privacy.tsx. A paragraph block's text may contain
// {{operator_name}}, {{contact_email}}, and {{governing_locale_language}}
// tokens — substituted at render time from the controller-provided props
// (never hardcoded per-locale, since they vary per deployment/viewer).
export interface LegalParagraphBlock {
    type: 'p';
    text: string;
}

export interface LegalListBlock {
    type: 'ul';
    items: string[];
}

export type LegalBlock = LegalParagraphBlock | LegalListBlock;

export interface LegalSection {
    heading: string;
    blocks: LegalBlock[];
}

export interface LegalDocument {
    title: string;
    sections: LegalSection[];
}

export interface LegalContent {
    terms: LegalDocument;
    privacy: LegalDocument;
}
