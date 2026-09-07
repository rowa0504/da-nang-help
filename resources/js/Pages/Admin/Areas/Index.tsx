import { Head, Link } from '@inertiajs/react';
import { AdminAreaData } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { useTranslation } from '@/hooks/useTranslation';

interface Props {
    areas: AdminAreaData[];
}

const linkClass = 'text-blue-600 underline hover:text-blue-800';
const primaryButtonClass =
    'rounded bg-blue-600 px-3 py-1.5 text-sm text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50';

export default function Index({ areas }: Props) {
    const { t } = useTranslation();

    return (
        <AppLayout>
            <div className="mx-auto max-w-3xl p-6 font-sans">
                <Head title={t('admin.areas.index.title')} />
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold text-gray-900">{t('admin.areas.index.title')}</h1>
                    <Link href="/admin/areas/create" className={primaryButtonClass}>
                        {t('admin.areas.index.create')}
                    </Link>
                </div>

                {areas.length === 0 ? (
                    <p className="mt-4 text-gray-600">{t('admin.areas.index.empty')}</p>
                ) : (
                    <table className="mt-4 w-full border-collapse text-sm">
                        <thead>
                            <tr>
                                <th className="border-b border-gray-300 py-2 text-left">{t('common.name')}</th>
                                <th className="border-b border-gray-300 py-2 text-left">{t('common.slug')}</th>
                                <th className="border-b border-gray-300 py-2 text-left">{t('common.status')}</th>
                                <th className="border-b border-gray-300 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {areas.map((area) => (
                                <tr key={area.id}>
                                    <td className="border-b border-gray-100 py-2">{area.name}</td>
                                    <td className="border-b border-gray-100 py-2">{area.slug}</td>
                                    <td className="border-b border-gray-100 py-2">
                                        {area.is_active ? t('common.active') : t('common.inactive')}
                                    </td>
                                    <td className="border-b border-gray-100 py-2">
                                        <Link href={`/admin/areas/${area.id}/edit`} className={linkClass}>
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
