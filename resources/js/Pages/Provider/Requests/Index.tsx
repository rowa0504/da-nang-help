import { Head, Link } from '@inertiajs/react';
import { PaginatedData, ServiceRequestData, ServiceRequestUrgency } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/PageHeader';
import { Card } from '@/Components/Card';
import { EmptyState } from '@/Components/EmptyState';
import { PaginationNav } from '@/Components/PaginationNav';
import { useTranslation } from '@/hooks/useTranslation';
import { TranslationKey } from '@/lang/en';

interface Props {
    requests: PaginatedData<ServiceRequestData>;
}

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
            <div className="mx-auto max-w-3xl p-4 sm:p-6">
                <Head title={t('provider.requests.index.title')} />
                <PageHeader title={t('provider.requests.index.heading')} />

                {requests.data.length === 0 ? (
                    <EmptyState message={t('provider.requests.index.empty')} />
                ) : (
                    <ul className="mt-6 space-y-3">
                        {requests.data.map((request) => (
                            <Card as="li" key={request.id}>
                                <Link href={`/requests/${request.id}`} className="font-medium text-blue-600 underline hover:text-blue-800">
                                    {request.title}
                                </Link>
                                <p className="mt-1 text-sm text-gray-600">
                                    {request.category.name} · {request.area.name} · {t(URGENCY_KEYS[request.urgency])}
                                </p>
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
                            </Card>
                        ))}
                    </ul>
                )}

                <PaginationNav links={requests.meta.links} />
            </div>
        </AppLayout>
    );
}
