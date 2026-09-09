import { Head, Link } from '@inertiajs/react';
import { PaginatedData, ServiceRequestData, ServiceRequestStatus } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/PageHeader';
import { Card } from '@/Components/Card';
import { Badge, BadgeVariant } from '@/Components/Badge';
import { EmptyState } from '@/Components/EmptyState';
import { PaginationNav } from '@/Components/PaginationNav';
import { useTranslation } from '@/hooks/useTranslation';
import { useLocaleFormat } from '@/hooks/useLocaleFormat';
import { TranslationKey } from '@/lang/en';

interface Props {
    requests: PaginatedData<ServiceRequestData>;
}

// Explicit, exhaustive correspondence table — a missing case here is a
// compile error, never a silent fallback to an untranslated raw value.
const REQUEST_STATUS_KEYS: Record<ServiceRequestStatus, TranslationKey> = {
    open: 'status.request.open',
    assigned: 'status.request.assigned',
    cancelled: 'status.request.cancelled',
};

const REQUEST_STATUS_VARIANTS: Record<ServiceRequestStatus, BadgeVariant> = {
    open: 'info',
    assigned: 'success',
    cancelled: 'danger',
};

export default function Index({ requests }: Props) {
    const { t } = useTranslation();
    const { formatDateTime } = useLocaleFormat();

    return (
        <AppLayout>
            <div className="mx-auto max-w-3xl p-4 sm:p-6">
                <Head title={t('requests.index.title')} />
                <PageHeader
                    title={t('requests.index.heading')}
                    actions={
                        <Link href="/requests/create" className="text-blue-600 underline hover:text-blue-800">
                            {t('requests.post_new')}
                        </Link>
                    }
                />

                {requests.data.length === 0 ? (
                    <EmptyState message={t('requests.index.empty')} actionLabel={t('requests.post_new')} actionHref="/requests/create" />
                ) : (
                    <ul className="mt-6 space-y-3">
                        {requests.data.map((request) => (
                            <Card as="li" key={request.id}>
                                <div className="flex items-center justify-between gap-4">
                                    <Link href={`/requests/${request.id}`} className="font-medium text-blue-600 underline hover:text-blue-800">
                                        {request.title}
                                    </Link>
                                    <Badge variant={REQUEST_STATUS_VARIANTS[request.status]}>{t(REQUEST_STATUS_KEYS[request.status])}</Badge>
                                </div>
                                <p className="mt-1 text-sm text-gray-600">{formatDateTime(request.created_at)}</p>
                            </Card>
                        ))}
                    </ul>
                )}

                <PaginationNav links={requests.meta.links} />
            </div>
        </AppLayout>
    );
}
