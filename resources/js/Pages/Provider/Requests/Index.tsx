import { Head, Link } from '@inertiajs/react';
import { PaginatedData, ServiceRequestData } from '@/types';

interface Props {
    requests: PaginatedData<ServiceRequestData>;
}

const linkClass = 'text-blue-600 underline hover:text-blue-800';

export default function Index({ requests }: Props) {
    return (
        <main className="mx-auto max-w-2xl p-6 font-sans">
            <Head title="Request Feed" />
            <h1 className="text-2xl font-semibold text-gray-900">Request Feed</h1>

            {requests.data.length === 0 && (
                <p className="mt-4 text-gray-600">No open requests match your categories and areas right now.</p>
            )}

            <ul className="mt-4 list-none divide-y divide-gray-200 p-0">
                {requests.data.map((request) => (
                    <li key={request.id} className="py-3">
                        <Link href={`/requests/${request.id}`} className={linkClass}>
                            {request.title}
                        </Link>{' '}
                        <span className="text-sm text-gray-600">
                            — {request.category.name} · {request.area.name} · {request.urgency}
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
