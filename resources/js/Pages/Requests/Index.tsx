import { Head, Link } from '@inertiajs/react';
import { PaginatedData, ServiceRequestData, ServiceRequestStatus } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { PaginationNav } from '@/Components/PaginationNav';
import { useTranslation } from '@/hooks/useTranslation';
import { TranslationKey } from '@/lang/en';

interface Props {
    requests: PaginatedData<ServiceRequestData>;
}

const linkClass = 'text-blue-600 underline hover:text-blue-800';

// Explicit, exhaustive correspondence table — a missing case here is a
// compile error, never a silent fallback to an untranslated raw value.
const REQUEST_STATUS_KEYS: Record<ServiceRequestStatus, TranslationKey> = {
    open: 'status.request.open',
    assigned: 'status.request.assigned',
    cancelled: 'status.request.cancelled',
};

export default function Index({ requests }: Props) {
    const { t } = useTranslation();

    return (
        <AppLayout>
            <div className="mx-auto max-w-2xl p-6 font-sans">
                <Head title={t('requests.index.title')} />
                <h1 className="text-2xl font-semibold text-gray-900">{t('requests.index.heading')}</h1>
                <p className="mt-2">
                    <Link href="/requests/create" className={linkClass}>
                        {t('requests.post_new')}
                    </Link>
                </p>

                {requests.data.length === 0 && <p className="mt-4 text-gray-600">{t('requests.index.empty')}</p>}

                <ul className="mt-4 list-none divide-y divide-gray-200 p-0">
                    {requests.data.map((request) => (
                        <li key={request.id} className="py-3">
                            <Link href={`/requests/${request.id}`} className={linkClass}>
                                {request.title}
                            </Link>{' '}
                            <span className="text-sm text-gray-600">
                                — <strong className="font-medium text-gray-800">{t(REQUEST_STATUS_KEYS[request.status])}</strong> ·{' '}
                                {new Date(request.created_at).toLocaleString()}
                            </span>
                        </li>
                    ))}
                </ul>

                <PaginationNav links={requests.meta.links} />
            </div>
        </AppLayout>
    );
}
