import { ReactNode, useEffect } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { SharedProps, UserRole } from '@/types';
import { TranslationKey } from '@/lang/en';
import { LocaleSwitcher } from '@/Components/LocaleSwitcher';
import { MobileNav } from '@/Components/MobileNav';
import { Alert } from '@/Components/Alert';
import { useTranslation } from '@/hooks/useTranslation';

interface NavItem {
    labelKey: TranslationKey;
    href: string;
}

const NAV_ITEMS: Record<UserRole, NavItem[]> = {
    customer: [
        { labelKey: 'nav.dashboard', href: '/dashboard' },
        { labelKey: 'nav.post_request', href: '/requests/create' },
        { labelKey: 'nav.my_requests', href: '/requests' },
        { labelKey: 'nav.my_jobs', href: '/jobs' },
    ],
    provider: [
        { labelKey: 'nav.dashboard', href: '/dashboard' },
        { labelKey: 'nav.request_feed', href: '/provider/requests' },
        { labelKey: 'nav.my_jobs', href: '/jobs' },
        { labelKey: 'nav.profile', href: '/provider/profile' },
    ],
    admin: [
        { labelKey: 'nav.dashboard', href: '/dashboard' },
        { labelKey: 'nav.admin_providers', href: '/admin/providers' },
        { labelKey: 'nav.admin_requests', href: '/admin/requests' },
        { labelKey: 'nav.admin_reviews', href: '/admin/reviews' },
        { labelKey: 'nav.admin_categories', href: '/admin/categories' },
        { labelKey: 'nav.admin_areas', href: '/admin/areas' },
    ],
};

const ROLE_KEYS: Record<UserRole, TranslationKey> = {
    customer: 'role.customer',
    provider: 'role.provider',
    admin: 'role.admin',
};

// Among nav items whose href matches the current url (exact match, or a
// path-segment-bounded prefix so /admin/categories/5/edit still highlights
// "Categories"), the longest href wins — so /requests/create highlights
// only "Post a request", not also "My requests" (/requests).
function resolveActiveHref(url: string, hrefs: string[]): string | null {
    const matches = hrefs.filter((href) => url === href || url.startsWith(`${href}/`));
    if (matches.length === 0) {
        return null;
    }
    return matches.reduce((longest, href) => (href.length > longest.length ? href : longest));
}

export function AppLayout({ children }: { children: ReactNode }) {
    const page = usePage<SharedProps>();
    const { auth, flash, locale } = page.props;
    const { t } = useTranslation();

    // Inertia visits (including the locale switch itself) only replace
    // props, not the full document — so <html lang> would otherwise stay
    // stuck at whatever the initial full page load rendered.
    useEffect(() => {
        document.documentElement.lang = locale;
    }, [locale]);

    const role = auth.user?.role;
    const rawItems = role ? NAV_ITEMS[role] : [];
    const activeHref = resolveActiveHref(page.url, rawItems.map((item) => item.href));
    const navItems = rawItems.map((item) => ({
        href: item.href,
        label: t(item.labelKey),
        active: item.href === activeHref,
    }));

    return (
        <div className="flex min-h-screen flex-col bg-gray-50">
            <header className="border-b border-gray-200 bg-white">
                <div className="flex items-center justify-between gap-4 p-4">
                    <Link href="/" className="text-lg font-semibold text-gray-900">
                        {t('app.name')}
                    </Link>

                    <nav className="hidden items-center gap-4 text-sm md:flex">
                        {navItems.map((item) => (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={item.active ? 'font-semibold text-blue-700' : 'text-gray-700 hover:text-blue-700'}
                            >
                                {item.label}
                            </Link>
                        ))}
                    </nav>

                    <div className="hidden items-center gap-4 text-sm md:flex">
                        <LocaleSwitcher />
                        {auth.user ? (
                            <>
                                <span className="text-gray-600">
                                    {auth.user.name}・{t(ROLE_KEYS[auth.user.role])}
                                </span>
                                <Link href="/logout" method="post" as="button" className="text-blue-600 underline hover:text-blue-800">
                                    {t('nav.logout')}
                                </Link>
                            </>
                        ) : (
                            <>
                                <Link href="/login" className="text-blue-600 underline hover:text-blue-800">
                                    {t('nav.login')}
                                </Link>
                                <Link href="/register" className="text-blue-600 underline hover:text-blue-800">
                                    {t('nav.register')}
                                </Link>
                            </>
                        )}
                    </div>

                    <MobileNav items={navItems} authUser={auth.user} />
                </div>
            </header>

            {(flash.status || flash.warning) && (
                <div className="space-y-2 p-4 pb-0">
                    {flash.status && <Alert variant="success">{flash.status}</Alert>}
                    {flash.warning && <Alert variant="warning">{flash.warning}</Alert>}
                </div>
            )}

            <main className="flex-1">{children}</main>
        </div>
    );
}
