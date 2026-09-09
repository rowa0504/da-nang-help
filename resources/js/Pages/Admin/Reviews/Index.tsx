import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AdminReviewData, PaginatedData } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/PageHeader';
import { DataTable, DataTableColumn } from '@/Components/DataTable';
import { EmptyState } from '@/Components/EmptyState';
import { Badge } from '@/Components/Badge';
import { Button } from '@/Components/Button';
import { PaginationNav } from '@/Components/PaginationNav';
import { useTranslation } from '@/hooks/useTranslation';
import { useConfirm } from '@/hooks/useConfirm';

interface Props {
    reviews: PaginatedData<AdminReviewData>;
}

export default function Index({ reviews }: Props) {
    const { t } = useTranslation();
    const { confirm, confirmDialog } = useConfirm();
    const [processingId, setProcessingId] = useState<number | null>(null);

    async function hide(review: AdminReviewData) {
        const ok = await confirm({
            title: t('common.confirm'),
            body: t('admin.reviews.index.confirm_hide', { rater: review.rater_name, ratee: review.ratee_name }),
            confirmVariant: 'danger',
        });
        if (!ok) {
            return;
        }
        setProcessingId(review.id);
        router.patch(`/admin/reviews/${review.id}/hide`, {}, { onFinish: () => setProcessingId(null) });
    }

    const columns: DataTableColumn<AdminReviewData>[] = [
        { key: 'rating', header: t('common.rating'), render: (review) => t('admin.reviews.index.rating_out_of_5', { rating: review.rating }) },
        { key: 'comment', header: t('common.comment'), render: (review) => review.comment ?? t('common.none') },
        { key: 'from', header: t('admin.reviews.index.from'), render: (review) => review.rater_name },
        { key: 'about', header: t('admin.reviews.index.about'), render: (review) => review.ratee_name },
        {
            key: 'status',
            header: t('common.status'),
            render: (review) => <Badge variant={review.is_hidden ? 'danger' : 'success'}>{t(review.is_hidden ? 'status.review.hidden' : 'status.review.visible')}</Badge>,
        },
        {
            key: 'action',
            header: '',
            render: (review) =>
                !review.is_hidden ? (
                    <Button variant="danger" size="compact" loading={processingId === review.id} onClick={() => hide(review)}>
                        {t('common.hide')}
                    </Button>
                ) : null,
        },
    ];

    return (
        <AppLayout>
            <div className="mx-auto max-w-6xl p-4 sm:p-6">
                <Head title={t('admin.reviews.index.title')} />
                <PageHeader title={t('admin.reviews.index.title')} />

                <div className="mt-6">
                    <DataTable
                        columns={columns}
                        rows={reviews.data}
                        rowKey={(review) => review.id}
                        emptyState={<EmptyState message={t('admin.reviews.index.empty')} />}
                    />
                </div>

                <PaginationNav links={reviews.meta.links} />

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
