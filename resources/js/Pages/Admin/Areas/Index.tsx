import { Head, Link } from '@inertiajs/react';
import { AdminAreaData } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/PageHeader';
import { DataTable, DataTableColumn } from '@/Components/DataTable';
import { EmptyState } from '@/Components/EmptyState';
import { Badge } from '@/Components/Badge';
import { useTranslation } from '@/hooks/useTranslation';

interface Props {
    areas: AdminAreaData[];
}

export default function Index({ areas }: Props) {
    const { t } = useTranslation();

    const columns: DataTableColumn<AdminAreaData>[] = [
        { key: 'name', header: t('common.name'), render: (area) => area.name },
        { key: 'slug', header: t('common.slug'), render: (area) => area.slug },
        {
            key: 'status',
            header: t('common.status'),
            render: (area) => <Badge variant={area.is_active ? 'success' : 'neutral'}>{t(area.is_active ? 'common.active' : 'common.inactive')}</Badge>,
        },
        {
            key: 'action',
            header: '',
            render: (area) => (
                <Link href={`/admin/areas/${area.id}/edit`} className="text-blue-600 underline hover:text-blue-800">
                    {t('common.edit')}
                </Link>
            ),
        },
    ];

    return (
        <AppLayout>
            <div className="mx-auto max-w-6xl p-4 sm:p-6">
                <Head title={t('admin.areas.index.title')} />
                <PageHeader
                    title={t('admin.areas.index.title')}
                    actions={
                        <Link href="/admin/areas/create" className="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                            {t('admin.areas.index.create')}
                        </Link>
                    }
                />

                <div className="mt-6">
                    <DataTable
                        columns={columns}
                        rows={areas}
                        rowKey={(area) => area.id}
                        emptyState={<EmptyState message={t('admin.areas.index.empty')} />}
                    />
                </div>

                <p className="mt-4">
                    <Link href="/dashboard" className="text-blue-600 underline hover:text-blue-800">
                        {t('nav.back_to_dashboard')}
                    </Link>
                </p>
            </div>
        </AppLayout>
    );
}
