import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';
import { JobData, OfferData, OfferStatus, ServiceRequestData, ServiceRequestStatus, ServiceRequestUrgency, SharedProps, SupportedLocale } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/PageHeader';
import { Card } from '@/Components/Card';
import { Badge, BadgeVariant } from '@/Components/Badge';
import { Alert } from '@/Components/Alert';
import { FormField } from '@/Components/FormField';
import { Input } from '@/Components/Input';
import { Textarea } from '@/Components/Textarea';
import { Select } from '@/Components/Select';
import { Button } from '@/Components/Button';
import { TranslatedText } from '@/Components/TranslatedText';
import { useTranslation } from '@/hooks/useTranslation';
import { useConfirm } from '@/hooks/useConfirm';
import { TranslationKey } from '@/lang/en';

interface Props {
    request: ServiceRequestData;
    myOffer: OfferData | null;
    canOffer: boolean;
    job: JobData | null;
}

// Explicit, exhaustive correspondence tables — a missing case here is a
// compile error, never a silent fallback to an untranslated raw value.
const REQUEST_STATUS_KEYS: Record<ServiceRequestStatus, TranslationKey> = {
    open: 'status.request.open',
    assigned: 'status.request.assigned',
    cancelled: 'status.request.cancelled',
};
const REQUEST_STATUS_VARIANTS: Record<ServiceRequestStatus, BadgeVariant> = {
    open: 'info',
    assigned: 'success',
    cancelled: 'danger',
};
const URGENCY_KEYS: Record<ServiceRequestUrgency, TranslationKey> = {
    normal: 'status.urgency.normal',
    urgent: 'status.urgency.urgent',
};
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
const SOURCE_LOCALE_OPTIONS: { value: SupportedLocale; labelKey: 'language.en' | 'language.ja' | 'language.vi' }[] = [
    { value: 'en', labelKey: 'language.en' },
    { value: 'ja', labelKey: 'language.ja' },
    { value: 'vi', labelKey: 'language.vi' },
];

function isSupportedLocale(value: string): value is SupportedLocale {
    return SOURCE_LOCALE_OPTIONS.some((option) => option.value === value);
}

// datetime-local inputs need "YYYY-MM-DDTHH:mm" in the browser's local time
// (no timezone suffix); this converts a stored UTC ISO 8601 string back to
// that format for pre-filling the edit form.
function toDatetimeLocalValue(iso?: string | null): string {
    if (!iso) return '';
    const date = new Date(iso);
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export default function Show({ request, myOffer, canOffer, job }: Props) {
    const { auth } = usePage<SharedProps>().props;
    const { t } = useTranslation();
    const { confirm, confirmDialog } = useConfirm();
    const { patch, processing } = useForm();

    async function cancel() {
        const ok = await confirm({ title: t('common.confirm'), body: t('requests.show.confirm_cancel'), confirmVariant: 'danger' });
        if (!ok) {
            return;
        }
        patch(`/requests/${request.id}/cancel`);
    }

    // address_text/lat/lng/customer are only present in props when the
    // backend (ServiceRequestResource, gated by ServiceRequestPolicy) has
    // decided this viewer may see them. Their presence here is a display
    // convenience only, not the security boundary itself — see the same
    // note in Dashboard.tsx.
    const canSeePrivate = request.address_text !== undefined;

    return (
        <AppLayout>
            <div className="mx-auto max-w-3xl p-4 sm:p-6">
                <Head title={request.title} />

                <PageHeader
                    title={<TranslatedText translation={request.title_translation} translated={request.title} />}
                    actions={<Badge variant={REQUEST_STATUS_VARIANTS[request.status]}>{t(REQUEST_STATUS_KEYS[request.status])}</Badge>}
                />
                <p className="mt-1 text-sm text-gray-600">
                    <strong className="font-medium text-gray-800">{t('common.urgency')}:</strong> {t(URGENCY_KEYS[request.urgency])}
                </p>
                <p className="mt-3 text-gray-800">
                    <TranslatedText translation={request.description_translation} translated={request.description} />
                </p>
                <p className="mt-1 text-sm text-gray-600">
                    <strong className="font-medium text-gray-800">{t('common.category')}:</strong> {request.category.name} ·{' '}
                    <strong className="font-medium text-gray-800">{t('common.area')}:</strong> {request.area.name}
                </p>

                {request.photos.length > 0 && (
                    <div className="mt-4 flex flex-wrap gap-2">
                        {request.photos.map((photo) => (
                            <img key={photo.id} src={photo.url} alt="" className="h-40 w-40 rounded border border-gray-200 object-cover" />
                        ))}
                    </div>
                )}

                {canSeePrivate && (
                    <div className="mt-4 space-y-2">
                        <Alert variant="info">{t('requests.show.private_info_notice')}</Alert>
                        <Card>
                            <dl className="space-y-3 text-sm">
                                <div>
                                    <dt className="font-medium text-gray-700">{t('common.address')}</dt>
                                    <dd className="text-gray-800">{request.address_text}</dd>
                                </div>
                                <div>
                                    <dt className="font-medium text-gray-700">{t('requests.show.coordinates')}</dt>
                                    <dd className="text-gray-800">
                                        {request.lat}, {request.lng}
                                    </dd>
                                </div>
                                {request.customer && (
                                    <div>
                                        <dt className="font-medium text-gray-700">{t('common.customer')}</dt>
                                        <dd className="text-gray-800">
                                            {request.customer.name} ({request.customer.email}
                                            {request.customer.phone ? `, ${request.customer.phone}` : ''})
                                        </dd>
                                    </div>
                                )}
                            </dl>
                        </Card>
                    </div>
                )}

                {auth.user?.role === 'customer' && request.status === 'open' && (
                    <Button variant="danger" loading={processing} onClick={cancel} className="mt-4">
                        {t('requests.show.cancel')}
                    </Button>
                )}

                {auth.user?.role === 'customer' && (
                    <p className="mt-4">
                        <Link href={`/requests/${request.id}/offers`} className="text-blue-600 underline hover:text-blue-800">
                            {t('nav.view_offers')}
                        </Link>
                    </p>
                )}

                {auth.user?.role === 'provider' && (canOffer || myOffer) && (
                    <OfferSection requestId={request.id} myOffer={myOffer} canOffer={canOffer} />
                )}

                {job && (
                    <p className="mt-4">
                        <Link href={`/jobs/${job.id}`} className="text-blue-600 underline hover:text-blue-800">
                            {t('nav.view_job')}
                        </Link>
                    </p>
                )}

                <p className="mt-4">
                    <Link href="/requests" className="text-blue-600 underline hover:text-blue-800">
                        {t('nav.back_to_my_requests')}
                    </Link>
                </p>

                {confirmDialog}
            </div>
        </AppLayout>
    );
}

type OfferForm = {
    price: string;
    currency: string;
    message: string;
    available_at: string;
    source_locale: SupportedLocale;
};

function OfferSection({ requestId, myOffer, canOffer }: { requestId: number; myOffer: OfferData | null; canOffer: boolean }) {
    const { t } = useTranslation();

    if (myOffer && myOffer.status !== 'pending') {
        return (
            <Card className="mt-6">
                <h2 className="text-lg font-semibold text-gray-900">{t('requests.show.your_offer')}</h2>
                <Badge variant={OFFER_STATUS_VARIANTS[myOffer.status]}>{t(OFFER_STATUS_KEYS[myOffer.status])}</Badge>
                <p className="mt-2 text-gray-800">
                    {myOffer.currency} {myOffer.price} — <TranslatedText translation={myOffer.message_translation} translated={myOffer.message} />
                </p>
            </Card>
        );
    }

    if (myOffer && myOffer.status === 'pending') {
        return <OfferForm requestId={requestId} existingOffer={myOffer} />;
    }

    if (canOffer) {
        return <OfferForm requestId={requestId} existingOffer={null} />;
    }

    return null;
}

function OfferForm({ requestId, existingOffer }: { requestId: number; existingOffer: OfferData | null }) {
    const { auth } = usePage<SharedProps>().props;
    const { t } = useTranslation();
    const { confirm, confirmDialog } = useConfirm();
    const rawLocale = auth.user?.locale;
    const defaultLocale: SupportedLocale = rawLocale !== undefined && isSupportedLocale(rawLocale) ? rawLocale : 'en';

    const { data, setData, post, patch, transform, processing, errors } = useForm<OfferForm>({
        price: existingOffer?.price ?? '',
        currency: existingOffer?.currency ?? 'USD',
        message: existingOffer?.original_message ?? '',
        available_at: toDatetimeLocalValue(existingOffer?.available_at),
        source_locale: existingOffer?.source_locale && isSupportedLocale(existingOffer.source_locale) ? existingOffer.source_locale : defaultLocale,
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        // available_at holds a local datetime-local value; transform()
        // converts it to UTC ISO 8601 immediately before sending, without
        // changing what the input itself displays. An empty value is sent
        // as null.
        transform((current) => ({
            ...current,
            available_at: current.available_at ? new Date(current.available_at).toISOString() : null,
        }));

        if (existingOffer) {
            patch(`/offers/${existingOffer.id}`);
        } else {
            post(`/requests/${requestId}/offers`);
        }
    }

    async function withdraw() {
        if (!existingOffer) {
            return;
        }
        const ok = await confirm({ title: t('common.confirm'), body: t('requests.show.confirm_withdraw'), confirmVariant: 'danger' });
        if (!ok) {
            return;
        }
        patch(`/offers/${existingOffer.id}/withdraw`);
    }

    return (
        <Card className="mt-6">
            <h2 className="text-lg font-semibold text-gray-900">
                {existingOffer ? t('requests.show.edit_offer_heading') : t('requests.show.send_offer_heading')}
            </h2>
            <form onSubmit={submit} className="mt-3 flex flex-col gap-4">
                <FormField label={t('common.price')} htmlFor="price" error={errors.price}>
                    <Input
                        type="number"
                        min="0"
                        max="9999999999.99"
                        step="0.01"
                        value={data.price}
                        onChange={(e) => setData('price', e.target.value)}
                    />
                </FormField>

                <FormField label={t('common.currency')} htmlFor="currency" error={errors.currency}>
                    <Input
                        type="text"
                        maxLength={3}
                        value={data.currency}
                        onChange={(e) => setData('currency', e.target.value.toUpperCase())}
                    />
                </FormField>

                <FormField label={t('common.message')} htmlFor="message" error={errors.message}>
                    <Textarea value={data.message} onChange={(e) => setData('message', e.target.value)} />
                </FormField>

                <FormField label={t('requests.show.available_from_optional')} htmlFor="available_at" error={errors.available_at}>
                    <Input type="datetime-local" value={data.available_at} onChange={(e) => setData('available_at', e.target.value)} />
                </FormField>

                <FormField label={t('requests.show.offer_language_label')} htmlFor="source_locale" error={errors.source_locale}>
                    <Select
                        value={data.source_locale}
                        onChange={(e) => {
                            if (isSupportedLocale(e.target.value)) {
                                setData('source_locale', e.target.value);
                            }
                        }}
                    >
                        {SOURCE_LOCALE_OPTIONS.map((option) => (
                            <option key={option.value} value={option.value}>
                                {t(option.labelKey)}
                            </option>
                        ))}
                    </Select>
                </FormField>

                <div className="flex gap-2">
                    <Button type="submit" loading={processing} className="self-start">
                        {existingOffer ? t('common.save_changes') : t('common.send_offer')}
                    </Button>
                    {existingOffer && (
                        <Button type="button" variant="danger" loading={processing} onClick={withdraw} className="self-start">
                            {t('common.withdraw')}
                        </Button>
                    )}
                </div>
            </form>

            {confirmDialog}
        </Card>
    );
}
