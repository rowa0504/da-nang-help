import { ReactNode, useEffect, useRef, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { AuthUser, UserRole } from '@/types';
import { TranslationKey } from '@/lang/en';
import { useTranslation } from '@/hooks/useTranslation';
import { LocaleSwitcher } from '@/Components/LocaleSwitcher';

interface MobileNavItem {
    label: ReactNode;
    href: string;
    active: boolean;
}

interface MobileNavProps {
    items: MobileNavItem[];
    authUser: AuthUser | null;
}

const ROLE_KEYS: Record<UserRole, TranslationKey> = {
    customer: 'role.customer',
    provider: 'role.provider',
    admin: 'role.admin',
};

export function MobileNav({ items, authUser }: MobileNavProps) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) {
            return;
        }
        function handleClickOutside(event: MouseEvent) {
            if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
                setOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, [open]);

    // Close after every completed Inertia page visit (e.g. tapping a nav
    // link), regardless of what triggered the navigation.
    useEffect(() => router.on('navigate', () => setOpen(false)), []);

    return (
        <div className="relative md:hidden" ref={containerRef}>
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                aria-expanded={open}
                aria-label={t('nav.menu')}
                className="rounded p-2 text-gray-700 hover:bg-gray-100"
            >
                <svg className="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            {open && (
                <div className="absolute right-0 z-40 mt-2 w-56 rounded border border-gray-200 bg-white p-2 shadow-lg">
                    <nav className="flex flex-col gap-1 text-sm">
                        {items.map((item) => (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={
                                    item.active
                                        ? 'rounded px-2 py-1.5 font-semibold text-blue-700'
                                        : 'rounded px-2 py-1.5 text-gray-700 hover:bg-gray-50'
                                }
                            >
                                {item.label}
                            </Link>
                        ))}
                    </nav>

                    <div className="mt-2 border-t border-gray-200 pt-2">
                        <LocaleSwitcher />
                    </div>

                    <div className="mt-2 border-t border-gray-200 pt-2 text-sm">
                        {authUser ? (
                            <>
                                <p className="px-2 py-1 text-gray-600">
                                    {authUser.name}・{t(ROLE_KEYS[authUser.role])}
                                </p>
                                <Link
                                    href="/logout"
                                    method="post"
                                    as="button"
                                    className="block w-full rounded px-2 py-1.5 text-left text-blue-600 underline"
                                >
                                    {t('nav.logout')}
                                </Link>
                            </>
                        ) : (
                            <>
                                <Link href="/login" className="block rounded px-2 py-1.5 text-blue-600 underline">
                                    {t('nav.login')}
                                </Link>
                                <Link href="/register" className="block rounded px-2 py-1.5 text-blue-600 underline">
                                    {t('nav.register')}
                                </Link>
                            </>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
