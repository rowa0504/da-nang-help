import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { OfferData, OfferStatus, PaginatedData } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { PaginationNav } from '@/Components/PaginationNav';
import { TranslatedText } from '@/Components/TranslatedText';
import { useTranslation } from '@/hooks/useTranslation';
import { TranslationKey } from '@/lang/en';

interface Props {
    serviceRequest: { id: number; title: string };
    offers: PaginatedData<OfferData>;
}

const linkClass = 'text-blue-600 underline hover:text-blue-800';
const primaryButtonClass =
    'rounded bg-blue-600 px-3 py-1.5 text-sm text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50';
const dangerButtonClass =
    'rounded bg-red-600 px-3 py-1.5 text-sm text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50';

// Explicit, exhaustive correspondence table — a missing case here is a
// compile error, never a silent fallback to an untranslated raw value.
const OFFER_STATUS_KEYS: Record<OfferStatus, TranslationKey> = {
    pending: 'status.offer.pending',
    accepted: 'status.offer.accepted',
    rejected: 'status.offer.rejected',
    withdrawn: 'status.offer.withdrawn',
    cancelled: 'status.offer.cancelled',
};

export default function Index({ serviceRequest, offers }: Props) {
    const { t } = useTranslation();
    const [processingId, setProcessingId] = useState<number | null>(null);

    function accept(offer: OfferData) {
        if (!confirm(t('offers.index.confirm_accept', { currency: offer.currency, price: offer.price }))) {
            return;
        }
        setProcessingId(offer.id);
        router.patch(`/offers/${offer.id}/accept`, {}, { onFinish: () => setProcessingId(null) });
    }

    function reject(offer: OfferData) {
        if (!confirm(t('offers.index.confirm_reject'))) {
            return;
        }
        setProcessingId(offer.id);
        router.patch(`/offers/${offer.id}/reject`, {}, { onFinish: () => setProcessingId(null) });
    }

    return (
        <AppLayout>
            <div className="mx-auto max-w-3xl p-6 font-sans">
                <Head title={t('offers.index.heading', { title: serviceRequest.title })} />
                <h1 className="text-2xl font-semibold text-gray-900">{t('offers.index.heading', { title: serviceRequest.title })}</h1>
                <p className="mt-2">
                    <Link href={`/requests/${serviceRequest.id}`} className={linkClass}>
                        {t('nav.back_to_request')}
                    </Link>
                </p>

                {offers.data.length === 0 && <p className="mt-4 text-gray-600">{t('offers.index.empty')}</p>}

                <ul className="mt-4 list-none space-y-4 p-0">
                    {offers.data.map((offer) => (
                        <li key={offer.id} className="rounded border border-gray-200 p-4">
                            <div className="flex items-center justify-between">
                                <span className="text-lg font-semibold text-gray-900">
                                    {offer.currency} {offer.price}
                                </span>
                                <span className="text-sm text-gray-600">{t(OFFER_STATUS_KEYS[offer.status])}</span>
                            </div>
                            <p className="mt-2 text-gray-800">
                                <TranslatedText translation={offer.message_translation} translated={offer.message} />
                            </p>
                            {offer.available_at && (
                                <p className="mt-1 text-sm text-gray-600">
                                    {t('offers.index.available_from', { date: new Date(offer.available_at).toLocaleString() })}
                                </p>
                            )}
                            <p className="mt-1 text-sm text-gray-600">
                                {t('offers.index.provider_summary', {
                                    business_name: offer.provider.business_name ?? '',
                                    rating: offer.provider.avg_rating,
                                    count: offer.provider.completed_jobs_count,
                                })}
                            </p>

                            {offer.status === 'pending' && (
                                <div className="mt-3 flex gap-2">
                                    <button
                                        onClick={() => accept(offer)}
                                        disabled={processingId === offer.id}
                                        className={primaryButtonClass}
                                    >
                                        {t('common.accept')}
                                    </button>
                                    <button
                                        onClick={() => reject(offer)}
                                        disabled={processingId === offer.id}
                                        className={dangerButtonClass}
                                    >
                                        {t('common.reject')}
                                    </button>
                                </div>
                            )}
                        </li>
                    ))}
                </ul>

                <PaginationNav links={offers.meta.links} />
            </div>
        </AppLayout>
    );
}
