import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ServiceRequestData, SharedProps } from '@/types';

interface Props {
    request: ServiceRequestData;
}

const linkClass = 'text-blue-600 underline hover:text-blue-800';
const dangerButtonClass =
    'rounded bg-red-600 px-4 py-2 text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50';

export default function Show({ request }: Props) {
    const { flash } = usePage<SharedProps>().props;
    const { patch, processing } = useForm();

    function cancel() {
        if (confirm('Withdraw this request? This cannot be undone.')) {
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
        <main className="mx-auto max-w-2xl p-6 font-sans">
            <Head title={request.title} />

            {flash.warning && (
                <p className="mb-4 rounded border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-800">
                    {flash.warning}
                </p>
            )}

            <h1 className="text-2xl font-semibold text-gray-900">{request.title}</h1>
            <p className="mt-1 text-sm text-gray-600">
                <strong className="font-medium text-gray-800">Status:</strong> {request.status} ·{' '}
                <strong className="font-medium text-gray-800">Urgency:</strong> {request.urgency}
            </p>
            <p className="mt-3 text-gray-800">{request.description}</p>
            <p className="mt-1 text-sm text-gray-600">
                <strong className="font-medium text-gray-800">Category:</strong> {request.category.name} ·{' '}
                <strong className="font-medium text-gray-800">Area:</strong> {request.area.name}
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
                    <dt className="font-medium text-gray-700">Address</dt>
                    <dd className="text-gray-800">{request.address_text}</dd>
                    <dt className="font-medium text-gray-700">Coordinates</dt>
                    <dd className="text-gray-800">
                        {request.lat}, {request.lng}
                    </dd>
                    {request.customer && (
                        <>
                            <dt className="font-medium text-gray-700">Customer</dt>
                            <dd className="text-gray-800">
                                {request.customer.name} ({request.customer.email}
                                {request.customer.phone ? `, ${request.customer.phone}` : ''})
                            </dd>
                        </>
                    )}
                </dl>
            )}

            {request.status === 'open' && (
                <button onClick={cancel} disabled={processing} className={`${dangerButtonClass} mt-4`}>
                    Withdraw request
                </button>
            )}

            <p className="mt-4">
                <Link href="/requests" className={linkClass}>
                    Back to my requests
                </Link>
            </p>
        </main>
    );
}
