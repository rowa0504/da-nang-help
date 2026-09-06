import { Head, Link } from '@inertiajs/react';
import { JobData, JobStatus, PaginatedData } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { PaginationNav } from '@/Components/PaginationNav';
import { useTranslation } from '@/hooks/useTranslation';
import { TranslationKey } from '@/lang/en';

interface Props {
    jobs: PaginatedData<JobData>;
}

const linkClass = 'text-blue-600 underline hover:text-blue-800';

// Explicit, exhaustive correspondence table — a missing case here is a
// compile error, never a silent fallback to an untranslated raw value.
const JOB_STATUS_KEYS: Record<JobStatus, TranslationKey> = {
    assigned: 'status.job.assigned',
    in_progress: 'status.job.in_progress',
    awaiting_confirmation: 'status.job.awaiting_confirmation',
    completed: 'status.job.completed',
    cancelled: 'status.job.cancelled',
};

export default function Index({ jobs }: Props) {
    const { t } = useTranslation();

    return (
        <AppLayout>
            <div className="mx-auto max-w-3xl p-6 font-sans">
                <Head title={t('jobs.index.title')} />
                <h1 className="text-2xl font-semibold text-gray-900">{t('jobs.index.title')}</h1>

                {jobs.data.length === 0 && <p className="mt-4 text-gray-600">{t('jobs.index.empty')}</p>}

                <ul className="mt-4 list-none space-y-4 p-0">
                    {jobs.data.map((job) => (
                        <li key={job.id} className="rounded border border-gray-200 p-4">
                            <div className="flex items-center justify-between">
                                <Link href={`/jobs/${job.id}`} className="text-lg font-semibold text-gray-900 hover:underline">
                                    {job.service_request.title}
                                </Link>
                                <span className="text-sm text-gray-600">{t(JOB_STATUS_KEYS[job.status])}</span>
                            </div>
                            <p className="mt-1 text-gray-800">
                                {job.currency} {job.agreed_price}
                            </p>
                            <p className="mt-1 text-sm text-gray-600">
                                {t('jobs.index.customer_provider', {
                                    customer: job.customer.name,
                                    provider: job.provider.business_name ?? job.provider.name,
                                })}
                            </p>
                            <p className="mt-2">
                                <Link href={`/jobs/${job.id}`} className={linkClass}>
                                    {t('jobs.view')}
                                </Link>
                            </p>
                        </li>
                    ))}
                </ul>

                <PaginationNav links={jobs.meta.links} />
            </div>
        </AppLayout>
    );
}
