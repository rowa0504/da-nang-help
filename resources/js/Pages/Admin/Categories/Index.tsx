import { Head, Link } from '@inertiajs/react';
import { AdminCategoryData } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { useTranslation } from '@/hooks/useTranslation';

interface Props {
    categories: AdminCategoryData[];
}

const linkClass = 'text-blue-600 underline hover:text-blue-800';
const primaryButtonClass =
    'rounded bg-blue-600 px-3 py-1.5 text-sm text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50';

export default function Index({ categories }: Props) {
    const { t } = useTranslation();

    return (
        <AppLayout>
            <div className="mx-auto max-w-3xl p-6 font-sans">
                <Head title={t('admin.categories.index.title')} />
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold text-gray-900">{t('admin.categories.index.title')}</h1>
                    <Link href="/admin/categories/create" className={primaryButtonClass}>
                        {t('admin.categories.index.create')}
                    </Link>
                </div>

                {categories.length === 0 ? (
                    <p className="mt-4 text-gray-600">{t('admin.categories.index.empty')}</p>
                ) : (
                    <table className="mt-4 w-full border-collapse text-sm">
                        <thead>
                            <tr>
                                <th className="border-b border-gray-300 py-2 text-left">{t('common.name')}</th>
                                <th className="border-b border-gray-300 py-2 text-left">{t('common.slug')}</th>
                                <th className="border-b border-gray-300 py-2 text-left">{t('common.sort_order')}</th>
                                <th className="border-b border-gray-300 py-2 text-left">{t('common.status')}</th>
                                <th className="border-b border-gray-300 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {categories.map((category) => (
                                <tr key={category.id}>
                                    <td className="border-b border-gray-100 py-2">
                                        <span style={{ paddingLeft: `${category.depth * 1.5}rem` }}>{category.name}</span>
                                    </td>
                                    <td className="border-b border-gray-100 py-2">{category.slug}</td>
                                    <td className="border-b border-gray-100 py-2">{category.sort_order}</td>
                                    <td className="border-b border-gray-100 py-2">
                                        {category.is_active ? t('common.active') : t('common.inactive')}
                                    </td>
                                    <td className="border-b border-gray-100 py-2">
                                        <Link href={`/admin/categories/${category.id}/edit`} className={linkClass}>
                                            {t('common.edit')}
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}

                <p className="mt-4">
                    <Link href="/dashboard" className={linkClass}>
                        {t('nav.back_to_dashboard')}
                    </Link>
                </p>
            </div>
        </AppLayout>
    );
}
