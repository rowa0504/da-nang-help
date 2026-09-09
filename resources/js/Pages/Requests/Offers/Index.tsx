import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { OfferData, OfferStatus, PaginatedData } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/PageHeader';
import { Card } from '@/Components/Card';
import { Badge, BadgeVariant } from '@/Components/Badge';
import { EmptyState } from '@/Components/EmptyState';
import { Button } from '@/Components/Button';
import { PaginationNav } from '@/Components/PaginationNav';
import { TranslatedText } from '@/Components/TranslatedText';
import { useTranslation } from '@/hooks/useTranslation';
import { useLocaleFormat } from '@/hooks/useLocaleFormat';
import { useConfirm } from '@/hooks/useConfirm';
import { TranslationKey } from '@/lang/en';

interface Props {
    serviceRequest: { id: number; title: string };
    offers: PaginatedData<OfferData>;
}

// Explicit, exhaustive correspondence table — a missing case here is a
// compile error, never a silent fallback to an untranslated raw value.
const OFFER_STATUS_KEYS: Record<OfferStatus, TranslationKey> = {
    pending: 'status.offer.pending',
    accepted: 'status.offer.accepted',
    rejected: 'status.offer.rejected',
    withdrawn: 'status.offer.withdrawn',
    cancelled: 'status.offer.cancelled',
};

const OFFER_STATUS_VARIANTS: Record<OfferStatus, BadgeVariant> = {
    pending: 'warning',
    accepted: 'success',
    rejected: 'danger',
    withdrawn: 'neutral',
    cancelled: 'neutral',
};

export default function Index({ serviceRequest, offers }: Props) {
    const { t } = useTranslation();
    const { formatCurrency, formatDateTime } = useLocaleFormat();
    const { confirm, confirmDialog } = useConfirm();
    const [processingId, setProcessingId] = useState<number | null>(null);

    async function accept(offer: OfferData) {
        const ok = await confirm({
            title: t('common.confirm'),
            body: t('offers.index.confirm_accept', { currency: offer.currency, price: offer.price }),
        });
        if (!ok) {
            return;
        }
        setProcessingId(offer.id);
        router.patch(`/offers/${offer.id}/accept`, {}, { onFinish: () => setProcessingId(null) });
    }

    async function reject(offer: OfferData) {
        const ok = await confirm({ title: t('common.confirm'), body: t('offers.index.confirm_reject'), confirmVariant: 'danger' });
        if (!ok) {
            return;
        }
        setProcessingId(offer.id);
        router.patch(`/offers/${offer.id}/reject`, {}, { onFinish: () => setProcessingId(null) });
    }

    return (
        <AppLayout>
            <div className="mx-auto max-w-3xl p-4 sm:p-6">
                <Head title={t('offers.index.heading', { title: serviceRequest.title })} />
                <PageHeader
                    title={t('offers.index.heading', { title: serviceRequest.title })}
                    actions={
                        <Link href={`/requests/${serviceRequest.id}`} className="text-blue-600 underline hover:text-blue-800">
                            {t('nav.back_to_request')}
                        </Link>
                    }
                />

                {offers.data.length === 0 ? (
                    <EmptyState message={t('offers.index.empty')} />
                ) : (
                    <ul className="mt-6 space-y-3">
                        {offers.data.map((offer) => (
                            <Card as="li" key={offer.id}>
                                <div className="flex items-center justify-between gap-4">
                                    <span className="text-lg font-semibold text-gray-900">{formatCurrency(offer.price, offer.currency)}</span>
                                    <Badge variant={OFFER_STATUS_VARIANTS[offer.status]}>{t(OFFER_STATUS_KEYS[offer.status])}</Badge>
                                </div>
                                <p className="mt-2 text-gray-800">
                                    <TranslatedText translation={offer.message_translation} translated={offer.message} />
                                </p>
                                {offer.available_at && (
                                    <p className="mt-1 text-sm text-gray-600">
                                        {t('offers.index.available_from', { date: formatDateTime(offer.available_at) })}
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
                                        <Button
                                            variant="primary"
                                            size="compact"
                                            loading={processingId === offer.id}
                                            onClick={() => accept(offer)}
                                        >
                                            {t('common.accept')}
                                        </Button>
                                        <Button
                                            variant="danger"
                                            size="compact"
                                            loading={processingId === offer.id}
                                            onClick={() => reject(offer)}
                                        >
                                            {t('common.reject')}
                                        </Button>
                                    </div>
                                )}
                            </Card>
                        ))}
                    </ul>
                )}

                <PaginationNav links={offers.meta.links} />
                {confirmDialog}
            </div>
        </AppLayout>
    );
}
