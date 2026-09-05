import { Head, Link } from '@inertiajs/react';
import { JobData, PaginatedData } from '@/types';

interface Props {
    jobs: PaginatedData<JobData>;
}

const linkClass = 'text-blue-600 underline hover:text-blue-800';

export default function Index({ jobs }: Props) {
    return (
        <main className="mx-auto max-w-3xl p-6 font-sans">
            <Head title="My jobs" />
            <h1 className="text-2xl font-semibold text-gray-900">My jobs</h1>

            {jobs.data.length === 0 && <p className="mt-4 text-gray-600">No jobs yet.</p>}

            <ul className="mt-4 list-none space-y-4 p-0">
                {jobs.data.map((job) => (
                    <li key={job.id} className="rounded border border-gray-200 p-4">
                        <div className="flex items-center justify-between">
                            <Link href={`/jobs/${job.id}`} className="text-lg font-semibold text-gray-900 hover:underline">
                                {job.service_request.title}
                            </Link>
                            <span className="text-sm text-gray-600">{job.status}</span>
                        </div>
                        <p className="mt-1 text-gray-800">
                            {job.currency} {job.agreed_price}
                        </p>
                        <p className="mt-1 text-sm text-gray-600">
                            Customer: {job.customer.name} · Provider: {job.provider.business_name ?? job.provider.name}
                        </p>
                        <p className="mt-2">
                            <Link href={`/jobs/${job.id}`} className={linkClass}>
                                View job
                            </Link>
                        </p>
                    </li>
                ))}
            </ul>

            <PaginationNav links={jobs.meta.links} />
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
