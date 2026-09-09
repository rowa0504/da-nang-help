import { Head, Link, usePage } from '@inertiajs/react';
import { AdminStats, ProviderVerificationStatus, SharedProps } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/PageHeader';
import { Card } from '@/Components/Card';
import { Badge, BadgeVariant } from '@/Components/Badge';
import { useTranslation } from '@/hooks/useTranslation';
import { TranslationKey } from '@/lang/en';

interface Props {
    // Only present for Admin viewers (DashboardController computes it
    // conditionally) — everyone else gets no adminStats prop at all.
    adminStats?: AdminStats;
}

// Explicit, exhaustive correspondence table — a missing case here is a
// compile error, never a silent fallback to an untranslated raw value.
const ROLE_KEYS: Record<'customer' | 'provider' | 'admin', TranslationKey> = {
    customer: 'role.customer',
    provider: 'role.provider',
    admin: 'role.admin',
};

const VERIFICATION_STATUS_KEYS: Record<ProviderVerificationStatus, TranslationKey> = {
    pending: 'status.provider_verification.pending',
    approved: 'status.provider_verification.approved',
    rejected: 'status.provider_verification.rejected',
    suspended: 'status.provider_verification.suspended',
};

const VERIFICATION_STATUS_VARIANTS: Record<ProviderVerificationStatus, BadgeVariant> = {
    pending: 'warning',
    approved: 'success',
    rejected: 'danger',
    suspended: 'danger',
};

const STAT_ITEMS: { key: keyof AdminStats; labelKey: TranslationKey; suffix?: string }[] = [
    { key: 'total_requests', labelKey: 'admin.dashboard.stats.total_requests' },
    { key: 'open_requests', labelKey: 'admin.dashboard.stats.open_requests' },
    { key: 'conversion_rate', labelKey: 'admin.dashboard.stats.conversion_rate', suffix: '%' },
    { key: 'total_jobs', labelKey: 'admin.dashboard.stats.total_jobs' },
    { key: 'completed_jobs', labelKey: 'admin.dashboard.stats.completed_jobs' },
    { key: 'pending_providers', labelKey: 'admin.dashboard.stats.pending_providers' },
    { key: 'active_categories', labelKey: 'admin.dashboard.stats.active_categories' },
    { key: 'active_areas', labelKey: 'admin.dashboard.stats.active_areas' },
    { key: 'hidden_reviews', labelKey: 'admin.dashboard.stats.hidden_reviews' },
];

// NOTE: This role-based content switch is a *display* convenience only —
// it is not a security boundary. Anyone can read/modify client-side JS, so
// it must never be relied on to hide data or actions a role shouldn't have.
// Navigation itself (post a request, browse feed, admin management links,
// etc.) now lives in AppLayout's role-based nav — this page only renders
// role-specific status/summary content that isn't a navigation link.
export default function Dashboard({ adminStats }: Props) {
    const { auth } = usePage<SharedProps>().props;
    const { t } = useTranslation();
    const user = auth.user;

    if (!user) {
        // Should be unreachable: this route is behind the `auth` middleware.
        return null;
    }

    return (
        <AppLayout>
            <div className="mx-auto max-w-6xl p-4 sm:p-6">
                <Head title={t('dashboard.title')} />
                <PageHeader
                    title={t('dashboard.title')}
                    description={t('dashboard.signed_in_as', { name: user.name, email: user.email, role: t(ROLE_KEYS[user.role]) })}
                />

                {user.role === 'provider' && <ProviderStatus status={user.provider_verification_status} />}

                {user.role === 'admin' && adminStats && (
                    <div className="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3">
                        {STAT_ITEMS.map((item) => (
                            <Card key={item.key}>
                                <dt className="text-sm text-gray-600">{t(item.labelKey)}</dt>
                                <dd className="mt-1 text-2xl font-semibold text-gray-900">
                                    {adminStats[item.key]}
                                    {item.suffix ?? ''}
                                </dd>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

const PROVIDER_STATUS_MESSAGE_KEYS: Record<ProviderVerificationStatus, TranslationKey> = {
    pending: 'dashboard.provider_status.pending',
    approved: 'dashboard.provider_status.approved',
    rejected: 'dashboard.provider_status.rejected',
    suspended: 'dashboard.provider_status.suspended',
};

const PROVIDER_STATUS_ACTION_KEYS: Partial<Record<ProviderVerificationStatus, TranslationKey>> = {
    pending: 'dashboard.provider_status.view_submission',
    approved: 'dashboard.provider_status.view_profile',
    rejected: 'dashboard.provider_status.update_resubmit',
};

function ProviderStatus({ status }: { status?: ProviderVerificationStatus | null }) {
    const { t } = useTranslation();

    if (!status) {
        return (
            <Card className="mt-6">
                <p className="text-sm text-gray-700">
                    {t('dashboard.provider_status.none')}{' '}
                    <Link href="/provider/profile" className="text-blue-600 underline hover:text-blue-800">
                        {t('dashboard.provider_status.setup_link')}
                    </Link>
                </p>
            </Card>
        );
    }

    const actionKey = PROVIDER_STATUS_ACTION_KEYS[status];

    return (
        <Card className="mt-6">
            <Badge variant={VERIFICATION_STATUS_VARIANTS[status]}>{t(VERIFICATION_STATUS_KEYS[status])}</Badge>
            <p className="mt-2 text-sm text-gray-700">
                {t(PROVIDER_STATUS_MESSAGE_KEYS[status])}
                {actionKey && (
                    <>
                        {' '}
                        <Link href="/provider/profile" className="text-blue-600 underline hover:text-blue-800">
                            {t(actionKey)}
                        </Link>
                    </>
                )}
            </p>
        </Card>
    );
}
