import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AdminServiceRequestData, PaginatedData, ServiceRequestModerationStatus, ServiceRequestStatus } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/PageHeader';
import { DataTable, DataTableColumn } from '@/Components/DataTable';
import { EmptyState } from '@/Components/EmptyState';
import { Badge, BadgeVariant } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { PaginationNav } from '@/Components/PaginationNav';
import { useTranslation } from '@/hooks/useTranslation';
import { useConfirm } from '@/hooks/useConfirm';
import { TranslationKey } from '@/lang/en';

interface Props {
    requests: PaginatedData<AdminServiceRequestData>;
}

// Explicit, exhaustive correspondence tables — a missing case here is a
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
const MODERATION_STATUS_KEYS: Record<ServiceRequestModerationStatus, TranslationKey> = {
    visible: 'status.review.visible',
    hidden: 'status.review.hidden',
};
const MODERATION_STATUS_VARIANTS: Record<ServiceRequestModerationStatus, BadgeVariant> = {
    visible: 'success',
    hidden: 'danger',
};

export default function Index({ requests }: Props) {
    const { t } = useTranslation();
    const { confirm, confirmDialog } = useConfirm();
    const [processingId, setProcessingId] = useState<number | null>(null);

    async function hide(request: AdminServiceRequestData) {
        const ok = await confirm({
            title: t('common.confirm'),
            body: t('admin.requests.index.confirm_hide', { title: request.title }),
            confirmVariant: 'danger',
        });
        if (!ok) {
            return;
        }
        setProcessingId(request.id);
        router.patch(`/admin/requests/${request.id}/hide`, {}, { onFinish: () => setProcessingId(null) });
    }

    const columns: DataTableColumn<AdminServiceRequestData>[] = [
        { key: 'title', header: t('common.title'), render: (request) => request.title },
        { key: 'customer', header: t('common.customer'), render: (request) => request.customer_name },
        { key: 'category', header: t('common.category'), render: (request) => request.category_name },
        { key: 'area', header: t('common.area'), render: (request) => request.area_name },
        {
            key: 'status',
            header: t('common.status'),
            render: (request) => <Badge variant={REQUEST_STATUS_VARIANTS[request.status]}>{t(REQUEST_STATUS_KEYS[request.status])}</Badge>,
        },
        {
            key: 'visibility',
            header: t('admin.requests.index.visibility'),
            render: (request) => (
                <Badge variant={MODERATION_STATUS_VARIANTS[request.moderation_status]}>{t(MODERATION_STATUS_KEYS[request.moderation_status])}</Badge>
            ),
        },
        {
            key: 'action',
            header: '',
            render: (request) =>
                request.moderation_status === 'visible' ? (
                    <Button variant="danger" size="compact" loading={processingId === request.id} onClick={() => hide(request)}>
                        {t('common.hide')}
                    </Button>
                ) : null,
        },
    ];

    return (
        <AppLayout>
            <div className="mx-auto max-w-6xl p-4 sm:p-6">
                <Head title={t('admin.requests.index.title')} />
                <PageHeader title={t('admin.requests.index.title')} />

                <div className="mt-6">
                    <DataTable
                        columns={columns}
                        rows={requests.data}
                        rowKey={(request) => request.id}
                        emptyState={<EmptyState message={t('admin.requests.index.empty')} />}
                    />
                </div>

                <PaginationNav links={requests.meta.links} />

                <p className="mt-4">
                    <Link href="/dashboard" className="text-blue-600 underline hover:text-blue-800">
                        {t('nav.back_to_dashboard')}
                    </Link>
                </p>

                {confirmDialog}
            </div>
        </AppLayout>
    );
}
