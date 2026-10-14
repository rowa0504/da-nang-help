import { Link } from '@inertiajs/react';
import { useTranslation } from '@/hooks/useTranslation';

// Rendered once inside AppLayout, so it appears on every page that uses
// that layout — guest pages (Home, Login, Register) and authenticated
// pages (including Terms/Privacy themselves) alike.
export function LegalFooter() {
    const { t } = useTranslation();

    return (
        <footer className="border-t border-gray-200 bg-white py-4">
            <div className="mx-auto flex max-w-5xl justify-center gap-4 px-4 text-sm text-gray-600">
                <Link href="/terms" className="hover:text-brand-700 hover:underline">
                    {t('nav.terms')}
                </Link>
                <Link href="/privacy" className="hover:text-brand-700 hover:underline">
                    {t('nav.privacy')}
                </Link>
            </div>
        </footer>
    );
}
