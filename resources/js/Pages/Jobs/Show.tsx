import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { JobData, JobStatus, SharedProps } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/PageHeader';
import { Card } from '@/Components/Card';
import { Badge, BadgeVariant } from '@/Components/Badge';
import { FormField } from '@/Components/FormField';
import { Textarea } from '@/Components/Textarea';
import { Button } from '@/Components/Button';
import { StarRating } from '@/Components/StarRating';
import { useTranslation } from '@/hooks/useTranslation';
import { useLocaleFormat } from '@/hooks/useLocaleFormat';
import { useConfirm } from '@/hooks/useConfirm';
import { TranslationKey } from '@/lang/en';

interface Props {
    job: JobData;
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

// The non-cancelled progression, in order — used to render a step
// indicator. Only used by this page, so it stays page-local rather than
// becoming a 16th shared component.
const PROGRESS_STEPS: { status: JobStatus; labelKey: TranslationKey }[] = [
    { status: 'assigned', labelKey: 'status.job.assigned' },
    { status: 'in_progress', labelKey: 'status.job.in_progress' },
    { status: 'awaiting_confirmation', labelKey: 'status.job.awaiting_confirmation' },
    { status: 'completed', labelKey: 'status.job.completed' },
];

function JobProgressSteps({ status }: { status: JobStatus }) {
    const { t } = useTranslation();

    if (status === 'cancelled') {
        return null;
    }

    const currentIndex = PROGRESS_STEPS.findIndex((step) => step.status === status);

    return (
        <ol className="mt-4 flex flex-wrap items-center gap-2 text-sm">
            {PROGRESS_STEPS.map((step, index) => {
                const isDone = index < currentIndex;
                const isCurrent = index === currentIndex;
                return (
                    <li key={step.status} className="flex items-center gap-2">
                        <span
                            aria-hidden="true"
                            className={[
                                'flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold',
                                isDone ? 'bg-green-600 text-white' : isCurrent ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500',
                            ].join(' ')}
                        >
                            {isDone ? '✓' : index + 1}
                        </span>
                        <span className={isCurrent ? 'font-semibold text-gray-900' : 'text-gray-600'}>{t(step.labelKey)}</span>
                        {index < PROGRESS_STEPS.length - 1 && (
                            <span aria-hidden="true" className="text-gray-300">
                                —
                            </span>
                        )}
                    </li>
                );
            })}
        </ol>
    );
}

export default function Show({ job }: Props) {
    const { auth } = usePage<SharedProps>().props;
    const { t } = useTranslation();
    const { formatDateTime, formatCurrency } = useLocaleFormat();
    const { confirm, confirmDialog } = useConfirm();
    const [processing, setProcessing] = useState(false);

    function patch(path: string) {
        setProcessing(true);
        router.patch(path, {}, { onFinish: () => setProcessing(false) });
    }

    async function patchWithConfirm(path: string, confirmMessage: string) {
        const ok = await confirm({ title: t('common.confirm'), body: confirmMessage, confirmVariant: 'danger' });
        if (!ok) {
            return;
        }
        patch(path);
    }

    const isProvider = auth.user?.role === 'provider';
    const isCustomer = auth.user?.role === 'customer';
    // Admin can view every job but never changes its state (Phase 6 scope;
    // Admin Job moderation is Phase 9).
    const canCancel = (isCustomer || isProvider) && (job.status === 'assigned' || job.status === 'in_progress');

    return (
        <AppLayout>
            <div className="mx-auto max-w-3xl p-4 sm:p-6">
                <Head title={t('jobs.show.title_prefix', { title: job.service_request.title })} />
                <PageHeader
                    title={job.service_request.title}
                    actions={<Badge variant={JOB_STATUS_VARIANTS[job.status]}>{t(JOB_STATUS_KEYS[job.status])}</Badge>}
                />
                <JobProgressSteps status={job.status} />
                <p className="mt-2 text-lg text-gray-900">{formatCurrency(job.agreed_price, job.currency)}</p>

                <Card className="mt-4">
                    <dl className="space-y-3 text-sm">
                        <div>
                            <dt className="font-medium text-gray-700">{t('common.address')}</dt>
                            <dd className="text-gray-800">{job.service_request.address_text}</dd>
                        </div>
                        <div>
                            <dt className="font-medium text-gray-700">{t('common.customer')}</dt>
                            <dd className="text-gray-800">
                                {job.customer.name}
                                {job.customer.phone ? ` (${job.customer.phone})` : ''}
                            </dd>
                        </div>
                        <div>
                            <dt className="font-medium text-gray-700">{t('common.provider')}</dt>
                            <dd className="text-gray-800">
                                {job.provider.business_name ?? job.provider.name}
                                {job.provider.phone ? ` (${job.provider.phone})` : ''}
                            </dd>
                        </div>
                    </dl>
                </Card>

                {job.provider_completed_at && (
                    <p className="mt-2 text-sm text-gray-600">{t('jobs.show.provider_completed', { date: formatDateTime(job.provider_completed_at) })}</p>
                )}
                {job.auto_confirm_at && job.status === 'awaiting_confirmation' && (
                    <p className="mt-1 text-sm text-gray-600">{t('jobs.show.auto_confirm_by', { date: formatDateTime(job.auto_confirm_at) })}</p>
                )}
                {job.completed_at && (
                    <p className="mt-1 text-sm text-gray-600">{t('jobs.show.completed_at', { date: formatDateTime(job.completed_at) })}</p>
                )}
                {job.cancelled_at && (
                    <p className="mt-1 text-sm text-gray-600">{t('jobs.show.cancelled_at', { date: formatDateTime(job.cancelled_at) })}</p>
                )}

                <div className="mt-4 flex flex-wrap gap-2">
                    {isProvider && job.status === 'assigned' && (
                        <Button loading={processing} onClick={() => patch(`/jobs/${job.id}/start`)}>
                            {t('jobs.show.start')}
                        </Button>
                    )}
                    {isProvider && job.status === 'in_progress' && (
                        <Button loading={processing} onClick={() => patch(`/jobs/${job.id}/report-completion`)}>
                            {t('jobs.show.report_completion')}
                        </Button>
                    )}
                    {isCustomer && job.status === 'awaiting_confirmation' && (
                        <Button loading={processing} onClick={() => patch(`/jobs/${job.id}/confirm-completion`)}>
                            {t('jobs.show.confirm_completion')}
                        </Button>
                    )}
                    {canCancel && (
                        <Button
                            variant="danger"
                            loading={processing}
                            onClick={() => patchWithConfirm(`/jobs/${job.id}/cancel`, t('jobs.show.confirm_cancel'))}
                        >
                            {t('jobs.show.cancel')}
                        </Button>
                    )}
                </div>

                {job.status === 'completed' && <ReviewSection isCustomer={isCustomer} jobId={job.id} review={job.review} />}

                <p className="mt-6">
                    <Link href={`/requests/${job.service_request.id}`} className="text-blue-600 underline hover:text-blue-800">
                        {t('nav.back_to_request')}
                    </Link>
                    {' · '}
                    <Link href="/jobs" className="text-blue-600 underline hover:text-blue-800">
                        {t('nav.all_my_jobs')}
                    </Link>
                </p>

                {confirmDialog}
            </div>
        </AppLayout>
    );
}

type ReviewForm = {
    rating: number;
    comment: string;
};

function ReviewSection({ isCustomer, jobId, review }: { isCustomer: boolean; jobId: number; review: JobData['review'] }) {
    const { t } = useTranslation();

    if (review !== null) {
        return (
            <Card className="mt-6">
                <h2 className="text-lg font-semibold text-gray-900">{t('jobs.show.review_heading')}</h2>
                <div className="mt-1">
                    <StarRating value={review.rating} readOnly />
                </div>
                {review.comment && <p className="mt-2 text-gray-800">{review.comment}</p>}
            </Card>
        );
    }

    if (!isCustomer) {
        return null;
    }

    return <ReviewForm jobId={jobId} />;
}

function ReviewForm({ jobId }: { jobId: number }) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm<ReviewForm>({
        rating: 5,
        comment: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post(`/jobs/${jobId}/review`);
    }

    return (
        <Card className="mt-6">
            <h2 className="text-lg font-semibold text-gray-900">{t('jobs.show.leave_review')}</h2>
            <form onSubmit={submit} className="mt-3 flex flex-col gap-4">
                <FormField label={t('common.rating')} htmlFor="rating" error={errors.rating}>
                    <StarRating value={data.rating} onChange={(value) => setData('rating', value)} ariaLabel={t('common.rating')} />
                </FormField>

                <FormField label={t('jobs.show.comment_optional')} htmlFor="comment" error={errors.comment}>
                    <Textarea value={data.comment} onChange={(e) => setData('comment', e.target.value)} />
                </FormField>

                <Button type="submit" loading={processing} className="self-start">
                    {t('jobs.show.submit_review')}
                </Button>
            </form>
        </Card>
    );
}
