import { Head, Link } from '@inertiajs/react';
import { JobData, JobStatus, PaginatedData } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/PageHeader';
import { Card } from '@/Components/Card';
import { Badge, BadgeVariant } from '@/Components/Badge';
import { EmptyState } from '@/Components/EmptyState';
import { PaginationNav } from '@/Components/PaginationNav';
import { useTranslation } from '@/hooks/useTranslation';
import { useLocaleFormat } from '@/hooks/useLocaleFormat';
import { TranslationKey } from '@/lang/en';

interface Props {
    jobs: PaginatedData<JobData>;
}

// Explicit, exhaustive correspondence table — a missing case here is a
// compile error, never a silent fallback to an untranslated raw value.
const JOB_STATUS_KEYS: Record<JobStatus, TranslationKey> = {
    assigned: 'status.job.assigned',
    in_progress: 'status.job.in_progress',
    awaiting_confirmation: 'status.job.awaiting_confirmation',
    completed: 'status.job.completed',
    cancelled: 'status.job.cancelled',
};

const JOB_STATUS_VARIANTS: Record<JobStatus, BadgeVariant> = {
    assigned: 'info',
    in_progress: 'info',
    awaiting_confirmation: 'warning',
    completed: 'success',
    cancelled: 'danger',
};

export default function Index({ jobs }: Props) {
    const { t } = useTranslation();
    const { formatCurrency } = useLocaleFormat();

    return (
        <AppLayout>
            <div className="mx-auto max-w-3xl p-4 sm:p-6">
                <Head title={t('jobs.index.title')} />
                <PageHeader title={t('jobs.index.title')} />

                {jobs.data.length === 0 ? (
                    <EmptyState message={t('jobs.index.empty')} />
                ) : (
                    <ul className="mt-6 space-y-3">
                        {jobs.data.map((job) => (
                            <Card as="li" key={job.id}>
                                <div className="flex items-center justify-between gap-4">
                                    <Link href={`/jobs/${job.id}`} className="text-lg font-semibold text-gray-900 hover:underline">
                                        {job.service_request.title}
                                    </Link>
                                    <Badge variant={JOB_STATUS_VARIANTS[job.status]}>{t(JOB_STATUS_KEYS[job.status])}</Badge>
                                </div>
                                <p className="mt-1 text-gray-800">{formatCurrency(job.agreed_price, job.currency)}</p>
                                <p className="mt-1 text-sm text-gray-600">
                                    {t('jobs.index.customer_provider', {
                                        customer: job.customer.name,
                                        provider: job.provider.business_name ?? job.provider.name,
                                    })}
                                </p>
                                <p className="mt-2">
                                    <Link href={`/jobs/${job.id}`} className="text-blue-600 underline hover:text-blue-800">
                                        {t('jobs.view')}
                                    </Link>
                                </p>
                            </Card>
                        ))}
                    </ul>
                )}

                <PaginationNav links={jobs.meta.links} />
            </div>
        </AppLayout>
    );
}
