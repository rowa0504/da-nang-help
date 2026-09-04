import { Head, Link } from '@inertiajs/react';
import { PaginatedData, ServiceRequestData } from '@/types';

interface Props {
    requests: PaginatedData<ServiceRequestData>;
}

const linkClass = 'text-blue-600 underline hover:text-blue-800';

export default function Index({ requests }: Props) {
    return (
        <main className="mx-auto max-w-2xl p-6 font-sans">
            <Head title="My Requests" />
            <h1 className="text-2xl font-semibold text-gray-900">My Requests</h1>
            <p className="mt-2">
                <Link href="/requests/create" className={linkClass}>
                    Post a new request
                </Link>
            </p>

            {requests.data.length === 0 && <p className="mt-4 text-gray-600">You haven&apos;t posted any requests yet.</p>}

            <ul className="mt-4 list-none divide-y divide-gray-200 p-0">
                {requests.data.map((request) => (
                    <li key={request.id} className="py-3">
                        <Link href={`/requests/${request.id}`} className={linkClass}>
                            {request.title}
                        </Link>{' '}
                        <span className="text-sm text-gray-600">
                            — <strong className="font-medium text-gray-800">{request.status}</strong> ·{' '}
                            {new Date(request.created_at).toLocaleString()}
                        </span>
                    </li>
                ))}
            </ul>

            <PaginationNav links={requests.meta.links} />
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
