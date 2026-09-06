import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';
import { JobData, OfferData, OfferStatus, ServiceRequestData, ServiceRequestStatus, ServiceRequestUrgency, SharedProps, SupportedLocale } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { TranslatedText } from '@/Components/TranslatedText';
import { useTranslation } from '@/hooks/useTranslation';
import { TranslationKey } from '@/lang/en';

interface Props {
    request: ServiceRequestData;
    myOffer: OfferData | null;
    canOffer: boolean;
    job: JobData | null;
}

const linkClass = 'text-blue-600 underline hover:text-blue-800';
const dangerButtonClass =
    'rounded bg-red-600 px-4 py-2 text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50';
const primaryButtonClass =
    'rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50';
const fieldClass =
    'mt-1 block w-full max-w-xl rounded border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';
const labelClass = 'block text-sm font-medium text-gray-700';
const errorClass = 'mt-1 text-sm text-red-600';

// Explicit, exhaustive correspondence tables — a missing case here is a
// compile error, never a silent fallback to an untranslated raw value.
const REQUEST_STATUS_KEYS: Record<ServiceRequestStatus, TranslationKey> = {
    open: 'status.request.open',
    assigned: 'status.request.assigned',
    cancelled: 'status.request.cancelled',
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
    const { auth, flash } = usePage<SharedProps>().props;
    const { t } = useTranslation();
    const { patch, processing } = useForm();

    function cancel() {
        if (confirm(t('requests.show.confirm_cancel'))) {
            patch(`/requests/${request.id}/cancel`);
        }
    }

    // address_text/lat/lng/customer are only present in props when the
    // backend (ServiceRequestResource, gated by ServiceRequestPolicy) has
    // decided this viewer may see them. Their presence here is a display
    // convenience only, not the security boundary itself — see the same
    // note in Dashboard.tsx.
    const canSeePrivate = request.address_text !== undefined;

    return (
        <AppLayout>
            <div className="mx-auto max-w-2xl p-6 font-sans">
                <Head title={request.title} />

                {flash.warning && (
                    <p className="mb-4 rounded border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-800">
                        {flash.warning}
                    </p>
                )}

                <h1 className="text-2xl font-semibold text-gray-900">
                    <TranslatedText translation={request.title_translation} translated={request.title} />
                </h1>
                <p className="mt-1 text-sm text-gray-600">
                    <strong className="font-medium text-gray-800">{t('common.status')}:</strong> {t(REQUEST_STATUS_KEYS[request.status])} ·{' '}
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
                            <img
                                key={photo.id}
                                src={photo.url}
                                alt=""
                                className="h-40 w-40 rounded border border-gray-200 object-cover"
                            />
                        ))}
                    </div>
                )}

                {canSeePrivate && (
                    <dl className="mt-4 space-y-1 rounded border border-gray-200 p-4 text-sm">
                        <dt className="font-medium text-gray-700">{t('common.address')}</dt>
                        <dd className="text-gray-800">{request.address_text}</dd>
                        <dt className="font-medium text-gray-700">{t('requests.show.coordinates')}</dt>
                        <dd className="text-gray-800">
                            {request.lat}, {request.lng}
                        </dd>
                        {request.customer && (
                            <>
                                <dt className="font-medium text-gray-700">{t('common.customer')}</dt>
                                <dd className="text-gray-800">
                                    {request.customer.name} ({request.customer.email}
                                    {request.customer.phone ? `, ${request.customer.phone}` : ''})
                                </dd>
                            </>
                        )}
                    </dl>
                )}

                {auth.user?.role === 'customer' && request.status === 'open' && (
                    <button onClick={cancel} disabled={processing} className={`${dangerButtonClass} mt-4`}>
                        {t('requests.show.cancel')}
                    </button>
                )}

                {auth.user?.role === 'customer' && (
                    <p className="mt-4">
                        <Link href={`/requests/${request.id}/offers`} className={linkClass}>
                            {t('nav.view_offers')}
                        </Link>
                    </p>
                )}

                {auth.user?.role === 'provider' && (canOffer || myOffer) && (
                    <OfferSection requestId={request.id} myOffer={myOffer} canOffer={canOffer} />
                )}

                {job && (
                    <p className="mt-4">
                        <Link href={`/jobs/${job.id}`} className={linkClass}>
                            {t('nav.view_job')}
                        </Link>
                    </p>
                )}

                <p className="mt-4">
                    <Link href="/requests" className={linkClass}>
                        {t('nav.back_to_my_requests')}
                    </Link>
                </p>
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
            <div className="mt-6 rounded border border-gray-200 p-4">
                <h2 className="text-lg font-semibold text-gray-900">{t('requests.show.your_offer')}</h2>
                <p className="mt-1 text-sm text-gray-600">{t('common.status_label', { status: t(OFFER_STATUS_KEYS[myOffer.status]) })}</p>
                <p className="mt-2 text-gray-800">
                    {myOffer.currency} {myOffer.price} — <TranslatedText translation={myOffer.message_translation} translated={myOffer.message} />
                </p>
            </div>
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

    function withdraw() {
        if (existingOffer && confirm(t('requests.show.confirm_withdraw'))) {
            patch(`/offers/${existingOffer.id}/withdraw`);
        }
    }

    return (
        <div className="mt-6 rounded border border-gray-200 p-4">
            <h2 className="text-lg font-semibold text-gray-900">
                {existingOffer ? t('requests.show.edit_offer_heading') : t('requests.show.send_offer_heading')}
            </h2>
            <form onSubmit={submit} className="mt-3 flex flex-col gap-4">
                <div>
                    <label className={labelClass}>
                        {t('common.price')}
                        <input
                            type="number"
                            min="0"
                            max="9999999999.99"
                            step="0.01"
                            className={fieldClass}
                            value={data.price}
                            onChange={(e) => setData('price', e.target.value)}
                        />
                    </label>
                    {errors.price && <div className={errorClass}>{errors.price}</div>}
                </div>

                <div>
                    <label className={labelClass}>
                        {t('common.currency')}
                        <input
                            type="text"
                            maxLength={3}
                            className={fieldClass}
                            value={data.currency}
                            onChange={(e) => setData('currency', e.target.value.toUpperCase())}
                        />
                    </label>
                    {errors.currency && <div className={errorClass}>{errors.currency}</div>}
                </div>

                <div>
                    <label className={labelClass}>
                        {t('common.message')}
                        <textarea
                            className={`${fieldClass} min-h-32`}
                            value={data.message}
                            onChange={(e) => setData('message', e.target.value)}
                        />
                    </label>
                    {errors.message && <div className={errorClass}>{errors.message}</div>}
                </div>

                <div>
                    <label className={labelClass}>
                        {t('requests.show.available_from_optional')}
                        <input
                            type="datetime-local"
                            className={fieldClass}
                            value={data.available_at}
                            onChange={(e) => setData('available_at', e.target.value)}
                        />
                    </label>
                    {errors.available_at && <div className={errorClass}>{errors.available_at}</div>}
                </div>

                <div>
                    <label className={labelClass}>
                        {t('requests.show.offer_language_label')}
                        <select
                            className={fieldClass}
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
                        </select>
                    </label>
                    {errors.source_locale && <div className={errorClass}>{errors.source_locale}</div>}
                </div>

                <div className="flex gap-2">
                    <button type="submit" disabled={processing} className={`${primaryButtonClass} self-start`}>
                        {existingOffer ? t('common.save_changes') : t('common.send_offer')}
                    </button>
                    {existingOffer && (
                        <button
                            type="button"
                            onClick={withdraw}
                            disabled={processing}
                            className={`${dangerButtonClass} self-start`}
                        >
                            {t('common.withdraw')}
                        </button>
                    )}
                </div>
            </form>
        </div>
    );
}
