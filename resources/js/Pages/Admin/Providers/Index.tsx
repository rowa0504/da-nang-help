import { Head, Link } from '@inertiajs/react';
import { AppLayout } from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/PageHeader';
import { DataTable, DataTableColumn } from '@/Components/DataTable';
import { EmptyState } from '@/Components/EmptyState';
import { useLocaleFormat } from '@/hooks/useLocaleFormat';
import { useTranslation } from '@/hooks/useTranslation';

interface ProviderListItem {
    id: number;
    business_name: string;
    applicant_name: string;
    applicant_email: string;
    created_at: string | null;
}

interface Props {
    profiles: ProviderListItem[];
}

export default function Index({ profiles }: Props) {
    const { t } = useTranslation();
    const { formatDate } = useLocaleFormat();

    const columns: DataTableColumn<ProviderListItem>[] = [
        { key: 'business_name', header: t('admin.providers.index.business_name'), render: (profile) => profile.business_name },
        {
            key: 'applicant',
            header: t('admin.providers.index.applicant'),
            render: (profile) => `${profile.applicant_name} (${profile.applicant_email})`,
        },
        {
            key: 'submitted',
            header: t('admin.providers.index.submitted'),
            render: (profile) => (profile.created_at ? formatDate(profile.created_at) : t('common.none')),
        },
        {
            key: 'action',
            header: '',
            render: (profile) => (
                <Link href={`/admin/providers/${profile.id}`} className="text-blue-600 underline hover:text-blue-800">
                    {t('common.review_action')}
                </Link>
            ),
        },
    ];

    return (
        <AppLayout>
            <div className="mx-auto max-w-6xl p-4 sm:p-6">
                <Head title={t('admin.providers.index.title')} />
                <PageHeader title={t('admin.providers.index.heading')} />

                <div className="mt-6">
                    <DataTable
                        columns={columns}
                        rows={profiles}
                        rowKey={(profile) => profile.id}
                        emptyState={<EmptyState message={t('admin.providers.index.empty')} />}
                    />
                </div>

                <p className="mt-6">
                    <Link href="/dashboard" className="text-blue-600 underline hover:text-blue-800">
                        {t('nav.back_to_dashboard')}
                    </Link>
                </p>
            </div>
        </AppLayout>
    );
}
