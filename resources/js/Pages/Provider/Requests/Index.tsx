import { Head, Link } from '@inertiajs/react';
import { PaginatedData, ServiceRequestData, ServiceRequestUrgency } from '@/types';
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
const URGENCY_KEYS: Record<ServiceRequestUrgency, TranslationKey> = {
    normal: 'status.urgency.normal',
    urgent: 'status.urgency.urgent',
};

export default function Index({ requests }: Props) {
    const { t } = useTranslation();

    return (
        <AppLayout>
            <div className="mx-auto max-w-2xl p-6 font-sans">
                <Head title={t('provider.requests.index.title')} />
                <h1 className="text-2xl font-semibold text-gray-900">{t('provider.requests.index.heading')}</h1>

                {requests.data.length === 0 && <p className="mt-4 text-gray-600">{t('provider.requests.index.empty')}</p>}

                <ul className="mt-4 list-none divide-y divide-gray-200 p-0">
                    {requests.data.map((request) => (
                        <li key={request.id} className="py-3">
                            <Link href={`/requests/${request.id}`} className={linkClass}>
                                {request.title}
                            </Link>{' '}
                            <span className="text-sm text-gray-600">
                                — {request.category.name} · {request.area.name} · {t(URGENCY_KEYS[request.urgency])}
                            </span>
                            {request.photos.length > 0 && (
                                <div className="mt-2 flex flex-wrap gap-2">
                                    {request.photos.map((photo) => (
                                        <img
                                            key={photo.id}
                                            src={photo.url}
                                            alt=""
                                            className="h-20 w-20 rounded border border-gray-200 object-cover"
                                        />
                                    ))}
                                </div>
                            )}
                        </li>
                    ))}
                </ul>

                <PaginationNav links={requests.meta.links} />
            </div>
        </AppLayout>
    );
}
