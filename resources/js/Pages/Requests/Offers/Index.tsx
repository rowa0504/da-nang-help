import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { OfferData, PaginatedData } from '@/types';

interface Props {
    serviceRequest: { id: number; title: string };
    offers: PaginatedData<OfferData>;
}

const linkClass = 'text-blue-600 underline hover:text-blue-800';
const primaryButtonClass =
    'rounded bg-blue-600 px-3 py-1.5 text-sm text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50';
const dangerButtonClass =
    'rounded bg-red-600 px-3 py-1.5 text-sm text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50';

export default function Index({ serviceRequest, offers }: Props) {
    const [processingId, setProcessingId] = useState<number | null>(null);

    function accept(offer: OfferData) {
        if (!confirm(`Accept this offer for ${offer.currency} ${offer.price}? This cannot be undone.`)) {
            return;
        }
        setProcessingId(offer.id);
        router.patch(`/offers/${offer.id}/accept`, {}, { onFinish: () => setProcessingId(null) });
    }

    function reject(offer: OfferData) {
        if (!confirm('Reject this offer?')) {
            return;
        }
        setProcessingId(offer.id);
        router.patch(`/offers/${offer.id}/reject`, {}, { onFinish: () => setProcessingId(null) });
    }

    return (
        <main className="mx-auto max-w-3xl p-6 font-sans">
            <Head title={`Offers for ${serviceRequest.title}`} />
            <h1 className="text-2xl font-semibold text-gray-900">Offers for &ldquo;{serviceRequest.title}&rdquo;</h1>
            <p className="mt-2">
                <Link href={`/requests/${serviceRequest.id}`} className={linkClass}>
                    Back to request
                </Link>
            </p>

            {offers.data.length === 0 && <p className="mt-4 text-gray-600">No offers yet.</p>}

            <ul className="mt-4 list-none space-y-4 p-0">
                {offers.data.map((offer) => (
                    <li key={offer.id} className="rounded border border-gray-200 p-4">
                        <div className="flex items-center justify-between">
                            <span className="text-lg font-semibold text-gray-900">
                                {offer.currency} {offer.price}
                            </span>
                            <span className="text-sm text-gray-600">{offer.status}</span>
                        </div>
                        <p className="mt-2 text-gray-800">{offer.message}</p>
                        {offer.available_at && (
                            <p className="mt-1 text-sm text-gray-600">
                                Available from: {new Date(offer.available_at).toLocaleString()}
                            </p>
                        )}
                        <p className="mt-1 text-sm text-gray-600">
                            {offer.provider.business_name} · rating {offer.provider.avg_rating} ·{' '}
                            {offer.provider.completed_jobs_count} completed jobs
                        </p>

                        {offer.status === 'pending' && (
                            <div className="mt-3 flex gap-2">
                                <button
                                    onClick={() => accept(offer)}
                                    disabled={processingId === offer.id}
                                    className={primaryButtonClass}
                                >
                                    Accept
                                </button>
                                <button
                                    onClick={() => reject(offer)}
                                    disabled={processingId === offer.id}
                                    className={dangerButtonClass}
                                >
                                    Reject
                                </button>
                            </div>
                        )}
                    </li>
                ))}
            </ul>

            <PaginationNav links={offers.meta.links} />
        </main>
    );
}

function PaginationNav({ links }: { links: { url: string | null; label: string; active: boolean }[] }) {
    return (
        <nav className="mt-4 flex flex-wrap gap-2 text-sm">
            {links.map((link, index) =>
                link.url ? (
                    <Link
                        key={index}
                        href={link.url}
                        preserveScroll
                        className={link.active ? `${linkClass} font-bold` : linkClass}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ) : (
                    <span key={index} className="text-gray-400" dangerouslySetInnerHTML={{ __html: link.label }} />
                ),
            )}
        </nav>
    );
}
