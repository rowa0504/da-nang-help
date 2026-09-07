import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AdminServiceRequestData, PaginatedData, ServiceRequestStatus } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { PaginationNav } from '@/Components/PaginationNav';
import { useTranslation } from '@/hooks/useTranslation';
import { TranslationKey } from '@/lang/en';

interface Props {
    requests: PaginatedData<AdminServiceRequestData>;
}

const linkClass = 'text-blue-600 underline hover:text-blue-800';
const dangerButtonClass =
    'rounded bg-red-600 px-3 py-1.5 text-sm text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50';

// Explicit, exhaustive correspondence table — a missing case here is a
// compile error, never a silent fallback to an untranslated raw value.
const REQUEST_STATUS_KEYS: Record<ServiceRequestStatus, TranslationKey> = {
    open: 'status.request.open',
    assigned: 'status.request.assigned',
    cancelled: 'status.request.cancelled',
};

export default function Index({ requests }: Props) {
    const { t } = useTranslation();
    const [processingId, setProcessingId] = useState<number | null>(null);

    function hide(request: AdminServiceRequestData) {
        if (!confirm(t('admin.requests.index.confirm_hide', { title: request.title }))) {
            return;
        }
        setProcessingId(request.id);
        router.patch(`/admin/requests/${request.id}/hide`, {}, { onFinish: () => setProcessingId(null) });
    }

    return (
        <AppLayout>
            <div className="mx-auto max-w-4xl p-6 font-sans">
                <Head title={t('admin.requests.index.title')} />
                <h1 className="text-2xl font-semibold text-gray-900">{t('admin.requests.index.title')}</h1>

                {requests.data.length === 0 ? (
                    <p className="mt-4 text-gray-600">{t('admin.requests.index.empty')}</p>
                ) : (
                    <table className="mt-4 w-full border-collapse text-sm">
                        <thead>
                            <tr>
                                <th className="border-b border-gray-300 py-2 text-left">{t('common.title')}</th>
                                <th className="border-b border-gray-300 py-2 text-left">{t('common.customer')}</th>
                                <th className="border-b border-gray-300 py-2 text-left">{t('common.category')}</th>
                                <th className="border-b border-gray-300 py-2 text-left">{t('common.area')}</th>
                                <th className="border-b border-gray-300 py-2 text-left">{t('common.status')}</th>
                                <th className="border-b border-gray-300 py-2 text-left">{t('admin.requests.index.visibility')}</th>
                                <th className="border-b border-gray-300 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {requests.data.map((request) => (
                                <tr key={request.id}>
                                    <td className="border-b border-gray-100 py-2">{request.title}</td>
                                    <td className="border-b border-gray-100 py-2">{request.customer_name}</td>
                                    <td className="border-b border-gray-100 py-2">{request.category_name}</td>
                                    <td className="border-b border-gray-100 py-2">{request.area_name}</td>
                                    <td className="border-b border-gray-100 py-2">{t(REQUEST_STATUS_KEYS[request.status])}</td>
                                    <td className="border-b border-gray-100 py-2">
                                        {request.moderation_status === 'hidden' ? t('status.review.hidden') : t('status.review.visible')}
                                    </td>
                                    <td className="border-b border-gray-100 py-2">
                                        {request.moderation_status === 'visible' && (
                                            <button
                                                onClick={() => hide(request)}
                                                disabled={processingId === request.id}
                                                className={dangerButtonClass}
                                            >
                                                {t('common.hide')}
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}

                <PaginationNav links={requests.meta.links} />

                <p className="mt-4">
                    <Link href="/dashboard" className={linkClass}>
                        {t('nav.back_to_dashboard')}
                    </Link>
                </p>
            </div>
        </AppLayout>
    );
}
