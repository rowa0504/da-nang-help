import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { JobData, SharedProps } from '@/types';

interface Props {
    job: JobData;
}

const linkClass = 'text-blue-600 underline hover:text-blue-800';
const primaryButtonClass =
    'rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50';
const dangerButtonClass =
    'rounded bg-red-600 px-4 py-2 text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50';

export default function Show({ job }: Props) {
    const { auth } = usePage<SharedProps>().props;
    const [processing, setProcessing] = useState(false);

    function patch(path: string, confirmMessage?: string) {
        if (confirmMessage && !confirm(confirmMessage)) {
            return;
        }
        setProcessing(true);
        router.patch(path, {}, { onFinish: () => setProcessing(false) });
    }

    const isProvider = auth.user?.role === 'provider';
    const isCustomer = auth.user?.role === 'customer';
    // Admin can view every job but never changes its state (Phase 6 scope;
    // Admin Job moderation is Phase 9).
    const canCancel = (isCustomer || isProvider) && (job.status === 'assigned' || job.status === 'in_progress');

    return (
        <main className="mx-auto max-w-2xl p-6 font-sans">
            <Head title={`Job: ${job.service_request.title}`} />
            <h1 className="text-2xl font-semibold text-gray-900">{job.service_request.title}</h1>
            <p className="mt-1 text-sm text-gray-600">
                <strong className="font-medium text-gray-800">Status:</strong> {job.status}
            </p>
            <p className="mt-2 text-lg text-gray-900">
                {job.currency} {job.agreed_price}
            </p>

            <dl className="mt-4 space-y-1 rounded border border-gray-200 p-4 text-sm">
                <dt className="font-medium text-gray-700">Address</dt>
                <dd className="text-gray-800">{job.service_request.address_text}</dd>
                <dt className="mt-2 font-medium text-gray-700">Customer</dt>
                <dd className="text-gray-800">
                    {job.customer.name}
                    {job.customer.phone ? ` (${job.customer.phone})` : ''}
                </dd>
                <dt className="mt-2 font-medium text-gray-700">Provider</dt>
                <dd className="text-gray-800">
                    {job.provider.business_name ?? job.provider.name}
                    {job.provider.phone ? ` (${job.provider.phone})` : ''}
                </dd>
            </dl>

            {job.provider_completed_at && (
                <p className="mt-2 text-sm text-gray-600">
                    Provider reported completion: {new Date(job.provider_completed_at).toLocaleString()}
                </p>
            )}
            {job.auto_confirm_at && job.status === 'awaiting_confirmation' && (
                <p className="mt-1 text-sm text-gray-600">
                    Auto-confirms by: {new Date(job.auto_confirm_at).toLocaleString()}
                </p>
            )}
            {job.completed_at && (
                <p className="mt-1 text-sm text-gray-600">Completed: {new Date(job.completed_at).toLocaleString()}</p>
            )}
            {job.cancelled_at && (
                <p className="mt-1 text-sm text-gray-600">Cancelled: {new Date(job.cancelled_at).toLocaleString()}</p>
            )}

            <div className="mt-4 flex flex-wrap gap-2">
                {isProvider && job.status === 'assigned' && (
                    <button onClick={() => patch(`/jobs/${job.id}/start`)} disabled={processing} className={primaryButtonClass}>
                        Start
                    </button>
                )}
                {isProvider && job.status === 'in_progress' && (
                    <button
                        onClick={() => patch(`/jobs/${job.id}/report-completion`)}
                        disabled={processing}
                        className={primaryButtonClass}
                    >
                        Report completion
                    </button>
                )}
                {isCustomer && job.status === 'awaiting_confirmation' && (
                    <button
                        onClick={() => patch(`/jobs/${job.id}/confirm-completion`)}
                        disabled={processing}
                        className={primaryButtonClass}
                    >
                        Confirm completion
                    </button>
                )}
                {canCancel && (
                    <button
                        onClick={() => patch(`/jobs/${job.id}/cancel`, 'Cancel this job? This cannot be undone.')}
                        disabled={processing}
                        className={dangerButtonClass}
                    >
                        Cancel job
                    </button>
                )}
            </div>

            <p className="mt-6">
                <Link href={`/requests/${job.service_request.id}`} className={linkClass}>
                    Back to request
                </Link>
                {' · '}
                <Link href="/jobs" className={linkClass}>
                    All my jobs
                </Link>
            </p>
        </main>
    );
}
