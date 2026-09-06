import { ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { useTranslation } from '@/hooks/useTranslation';
import { TranslationKey } from '@/lang/en';

interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}

const linkClass = 'text-blue-600 underline hover:text-blue-800';

// Laravel's paginator emits exactly these two literal, HTML-entity-encoded
// labels for the prev/next links; every other label is a bare page number
// or the "..." separator, neither of which needs translation.
const TRANSLATABLE_LABELS: Record<string, TranslationKey> = {
    '&laquo; Previous': 'pagination.previous',
    'Next &raquo;': 'pagination.next',
};

function renderLabel(label: string, t: (key: TranslationKey) => string): ReactNode {
    const key = TRANSLATABLE_LABELS[label];
    return key ? t(key) : <span dangerouslySetInnerHTML={{ __html: label }} />;
}

// Shared across every paginated index page (previously duplicated
// per-file); consolidating here means the prev/next translation only has
// to be written once.
export function PaginationNav({ links }: { links: PageLink[] }) {
    const { t } = useTranslation();

    return (
        <nav className="mt-4 flex flex-wrap gap-2 text-sm">
            {links.map((link, index) =>
                link.url ? (
                    <Link
                        key={index}
                        href={link.url}
                        preserveScroll
                        className={link.active ? `${linkClass} font-bold` : linkClass}
                    >
                        {renderLabel(link.label, t)}
                    </Link>
                ) : (
                    <span key={index} className="text-gray-400">
                        {renderLabel(link.label, t)}
                    </span>
                ),
            )}
        </nav>
    );
}
