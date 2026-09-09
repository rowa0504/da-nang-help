import { Head, Link } from '@inertiajs/react';
import { AdminCategoryData } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/PageHeader';
import { DataTable, DataTableColumn } from '@/Components/DataTable';
import { EmptyState } from '@/Components/EmptyState';
import { Badge } from '@/Components/Badge';
import { useTranslation } from '@/hooks/useTranslation';

interface Props {
    categories: AdminCategoryData[];
}

export default function Index({ categories }: Props) {
    const { t } = useTranslation();

    const columns: DataTableColumn<AdminCategoryData>[] = [
        {
            key: 'name',
            header: t('common.name'),
            render: (category) => <span style={{ paddingLeft: `${category.depth * 1.5}rem` }}>{category.name}</span>,
        },
        { key: 'slug', header: t('common.slug'), render: (category) => category.slug },
        { key: 'sort_order', header: t('common.sort_order'), render: (category) => category.sort_order },
        {
            key: 'status',
            header: t('common.status'),
            render: (category) => (
                <Badge variant={category.is_active ? 'success' : 'neutral'}>{t(category.is_active ? 'common.active' : 'common.inactive')}</Badge>
            ),
        },
        {
            key: 'action',
            header: '',
            render: (category) => (
                <Link href={`/admin/categories/${category.id}/edit`} className="text-blue-600 underline hover:text-blue-800">
                    {t('common.edit')}
                </Link>
            ),
        },
    ];

    return (
        <AppLayout>
            <div className="mx-auto max-w-6xl p-4 sm:p-6">
                <Head title={t('admin.categories.index.title')} />
                <PageHeader
                    title={t('admin.categories.index.title')}
                    actions={
                        <Link href="/admin/categories/create" className="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                            {t('admin.categories.index.create')}
                        </Link>
                    }
                />

                <div className="mt-6">
                    <DataTable
                        columns={columns}
                        rows={categories}
                        rowKey={(category) => category.id}
                        emptyState={<EmptyState message={t('admin.categories.index.empty')} />}
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
