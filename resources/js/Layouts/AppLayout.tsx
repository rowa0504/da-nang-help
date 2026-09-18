import { ReactNode, useEffect } from 'react';
import { Link, usePage } from '@inertiajs/react';
import {
    Briefcase,
    ClipboardList,
    ClipboardPlus,
    HelpCircle,
    Inbox,
    LayoutDashboard,
    LucideIcon,
    MapPin,
    Star,
    Tags,
    UserCircle,
    Users,
} from 'lucide-react';
import { SharedProps, UserRole } from '@/types';
import { TranslationKey } from '@/lang/en';
import { LocaleSwitcher } from '@/Components/LocaleSwitcher';
import { MobileNav } from '@/Components/MobileNav';
import { Alert } from '@/Components/Alert';
import { Logo } from '@/Components/Logo';
import { useTranslation } from '@/hooks/useTranslation';

interface NavItem {
    labelKey: TranslationKey;
    href: string;
    icon: LucideIcon;
}

const NAV_ITEMS: Record<UserRole, NavItem[]> = {
    customer: [
        { labelKey: 'nav.dashboard', href: '/dashboard', icon: LayoutDashboard },
        { labelKey: 'nav.post_request', href: '/requests/create', icon: ClipboardPlus },
        { labelKey: 'nav.my_requests', href: '/requests', icon: ClipboardList },
        { labelKey: 'nav.my_jobs', href: '/jobs', icon: Briefcase },
    ],
    provider: [
        { labelKey: 'nav.dashboard', href: '/dashboard', icon: LayoutDashboard },
        { labelKey: 'nav.request_feed', href: '/provider/requests', icon: Inbox },
        { labelKey: 'nav.my_jobs', href: '/jobs', icon: Briefcase },
        { labelKey: 'nav.profile', href: '/provider/profile', icon: UserCircle },
    ],
    admin: [
        { labelKey: 'nav.dashboard', href: '/dashboard', icon: LayoutDashboard },
        { labelKey: 'nav.admin_providers', href: '/admin/providers', icon: Users },
        { labelKey: 'nav.admin_requests', href: '/admin/requests', icon: ClipboardList },
        { labelKey: 'nav.admin_reviews', href: '/admin/reviews', icon: Star },
        { labelKey: 'nav.admin_categories', href: '/admin/categories', icon: Tags },
        { labelKey: 'nav.admin_areas', href: '/admin/areas', icon: MapPin },
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
        icon: item.icon,
        active: item.href === activeHref,
    }));
    // "How it works" always jumps to Home's 3-step section — it's a
    // fragment anchor, not a route, so it never counts as the active item.
    const howItWorksItem = { href: '/#how-it-works', label: t('nav.how_it_works'), icon: HelpCircle, active: false };
    const allNavItems = [...navItems, howItWorksItem];

    return (
        <div className="flex min-h-screen flex-col bg-gray-50">
            <header className="border-b border-gray-200 bg-white">
                <div className="flex items-center justify-between gap-4 p-4">
                    <Link href="/" className="shrink-0">
                        <Logo />
                    </Link>

                    <nav className="hidden items-center gap-4 text-sm md:flex">
                        {allNavItems.map((item) => (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={item.active ? 'font-semibold text-brand-700' : 'text-gray-700 hover:text-brand-700'}
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
                                <Link href="/logout" method="post" as="button" className="text-brand-600 underline hover:text-brand-700">
                                    {t('nav.logout')}
                                </Link>
                            </>
                        ) : (
                            <>
                                <Link href="/login" className="text-brand-600 underline hover:text-brand-700">
                                    {t('nav.login')}
                                </Link>
                                <Link href="/register" className="text-brand-600 underline hover:text-brand-700">
                                    {t('nav.register')}
                                </Link>
                            </>
                        )}
                    </div>

                    <MobileNav items={allNavItems} authUser={auth.user} />
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
