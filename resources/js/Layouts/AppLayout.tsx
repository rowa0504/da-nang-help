import { ReactNode, useEffect } from 'react';
import { usePage } from '@inertiajs/react';
import { SharedProps } from '@/types';
import { LocaleSwitcher } from '@/Components/LocaleSwitcher';

// Deliberately thin: language switching, a content slot, and a place for
// future shared navigation. No page-specific styling — each page keeps
// its own inner wrapper (max-w-*, padding, etc.) inside `children`.
export function AppLayout({ children }: { children: ReactNode }) {
    const { locale } = usePage<SharedProps>().props;

    // Inertia visits (including the locale switch itself) only replace
    // props, not the full document — so <html lang> would otherwise stay
    // stuck at whatever the initial full page load rendered.
    useEffect(() => {
        document.documentElement.lang = locale;
    }, [locale]);

    return (
        <div>
            <header className="flex items-center justify-end gap-4 border-b border-gray-200 p-4">
                <LocaleSwitcher />
            </header>
            <main>{children}</main>
        </div>
    );
}
