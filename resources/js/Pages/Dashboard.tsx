import { Head, Link, usePage } from '@inertiajs/react';
import { ProviderVerificationStatus, SharedProps } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { TranslationKey } from '@/lang/en';

// Explicit, exhaustive correspondence table — a missing case here is a
// compile error, never a silent fallback to an untranslated raw value.
const ROLE_KEYS: Record<'customer' | 'provider' | 'admin', TranslationKey> = {
    customer: 'role.customer',
    provider: 'role.provider',
    admin: 'role.admin',
};

// NOTE: This role-based content switch is a *display* convenience only —
// it is not a security boundary. Anyone can read/modify client-side JS, so
// it must never be relied on to hide data or actions a role shouldn't have.
// When real Provider/Admin-only features are added (Phase 3+), enforce
// access on the server via Laravel Middleware/Policies, and only use this
// switch to choose which already-authorized UI to render.
export default function Dashboard() {
    const { auth } = usePage<SharedProps>().props;
    const { t } = useTranslation();
    const user = auth.user;

    if (!user) {
        // Should be unreachable: this route is behind the `auth` middleware.
        return null;
    }

    return (
        <AppLayout>
            <div style={{ fontFamily: 'sans-serif', padding: '2rem' }}>
                <Head title={t('dashboard.title')} />
                <h1>{t('dashboard.title')}</h1>
                <p>
                    {t('dashboard.signed_in_as', { name: user.name, email: user.email, role: t(ROLE_KEYS[user.role]) })}
                </p>

                {user.role === 'customer' && (
                    <p>
                        <Link href="/requests/create">{t('requests.post_new')}</Link> ·{' '}
                        <Link href="/requests">{t('nav.view_my_requests')}</Link> · <Link href="/jobs">{t('nav.my_jobs')}</Link>
                    </p>
                )}

                {user.role === 'provider' && (
                    <>
                        <ProviderStatus status={user.provider_verification_status} />
                        {user.provider_verification_status === 'approved' && (
                            <p>
                                <Link href="/provider/requests">{t('nav.browse_feed')}</Link>
                            </p>
                        )}
                        <p>
                            <Link href="/jobs">{t('nav.my_jobs')}</Link>
                        </p>
                    </>
                )}

                {user.role === 'admin' && (
                    <p>
                        {t('dashboard.admin_placeholder')} <Link href="/admin/providers">{t('nav.review_pending_providers')}</Link> ·{' '}
                        <Link href="/admin/reviews">{t('nav.manage_reviews')}</Link>
                    </p>
                )}

                <Link href="/logout" method="post" as="button">
                    {t('nav.logout')}
                </Link>
            </div>
        </AppLayout>
    );
}

function ProviderStatus({ status }: { status?: ProviderVerificationStatus | null }) {
    const { t } = useTranslation();

    if (!status) {
        return (
            <p>
                {t('dashboard.provider_status.none')} <Link href="/provider/profile">{t('dashboard.provider_status.setup_link')}</Link>
            </p>
        );
    }

    switch (status) {
        case 'pending':
            return (
                <p>
                    {t('dashboard.provider_status.pending')}{' '}
                    <Link href="/provider/profile">{t('dashboard.provider_status.view_submission')}</Link>
                </p>
            );
        case 'approved':
            return (
                <p>
                    {t('dashboard.provider_status.approved')}{' '}
                    <Link href="/provider/profile">{t('dashboard.provider_status.view_profile')}</Link>
                </p>
            );
        case 'rejected':
            return (
                <p>
                    {t('dashboard.provider_status.rejected')}{' '}
                    <Link href="/provider/profile">{t('dashboard.provider_status.update_resubmit')}</Link>
                </p>
            );
        case 'suspended':
            return <p>{t('dashboard.provider_status.suspended')}</p>;
        default:
            return null;
    }
}
