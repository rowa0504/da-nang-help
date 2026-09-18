import { ReactNode, useEffect, useRef, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { LucideIcon } from 'lucide-react';
import { AuthUser, UserRole } from '@/types';
import { TranslationKey } from '@/lang/en';
import { useTranslation } from '@/hooks/useTranslation';
import { LocaleSwitcher } from '@/Components/LocaleSwitcher';

interface MobileNavItem {
    label: ReactNode;
    href: string;
    active: boolean;
    icon?: LucideIcon;
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
                className="flex h-11 w-11 items-center justify-center rounded text-gray-700 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"
            >
                <svg className="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            {open && (
                <div
                    className="absolute right-0 z-40 mt-2 w-64 origin-top-right rounded border border-gray-200 bg-white p-2 shadow-lg
                        transition-[opacity,transform] duration-150 motion-reduce:transition-none"
                >
                    <nav className="flex flex-col gap-1 text-sm">
                        {items.map((item) => {
                            const Icon = item.icon;
                            return (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    className={
                                        item.active
                                            ? 'flex min-h-11 items-center gap-3 rounded bg-brand-50 px-2 py-1.5 font-semibold text-brand-700'
                                            : 'flex min-h-11 items-center gap-3 rounded px-2 py-1.5 text-gray-700 hover:bg-gray-50'
                                    }
                                >
                                    {Icon && <Icon aria-hidden="true" className="h-5 w-5 shrink-0" />}
                                    {item.label}
                                </Link>
                            );
                        })}
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
                                    className="flex min-h-11 w-full items-center rounded px-2 py-1.5 text-left text-brand-600 underline hover:text-brand-700"
                                >
                                    {t('nav.logout')}
                                </Link>
                            </>
                        ) : (
                            <>
                                <Link
                                    href="/login"
                                    className="flex min-h-11 items-center rounded px-2 py-1.5 text-brand-600 underline hover:text-brand-700"
                                >
                                    {t('nav.login')}
                                </Link>
                                <Link
                                    href="/register"
                                    className="flex min-h-11 items-center rounded px-2 py-1.5 text-brand-600 underline hover:text-brand-700"
                                >
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
