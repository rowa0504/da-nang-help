import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { ProviderVerificationStatus } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/PageHeader';
import { Card } from '@/Components/Card';
import { Badge, BadgeVariant } from '@/Components/Badge';
import { FormField } from '@/Components/FormField';
import { Textarea } from '@/Components/Textarea';
import { Button } from '@/Components/Button';
import { useTranslation } from '@/hooks/useTranslation';
import { useConfirm } from '@/hooks/useConfirm';
import { TranslationKey } from '@/lang/en';

interface ProviderDetail {
    id: number;
    business_name: string;
    bio: string | null;
    verification_status: ProviderVerificationStatus;
    verification_note: string | null;
    categories: string[];
    areas: string[];
    applicant_name: string;
    applicant_email: string;
    applicant_phone: string | null;
}

interface Props {
    profile: ProviderDetail;
}

// Explicit, exhaustive correspondence table — a missing case here is a
// compile error, never a silent fallback to an untranslated raw value.
const VERIFICATION_STATUS_KEYS: Record<ProviderVerificationStatus, TranslationKey> = {
    pending: 'status.provider_verification.pending',
    approved: 'status.provider_verification.approved',
    rejected: 'status.provider_verification.rejected',
    suspended: 'status.provider_verification.suspended',
};

const VERIFICATION_STATUS_VARIANTS: Record<ProviderVerificationStatus, BadgeVariant> = {
    pending: 'warning',
    approved: 'success',
    rejected: 'danger',
    suspended: 'danger',
};

export default function Show({ profile }: Props) {
    const { t } = useTranslation();
    const { confirm, confirmDialog } = useConfirm();
    const approveForm = useForm({});
    const rejectForm = useForm({ note: '' });
    const suspendForm = useForm({ note: '' });

    async function approve(e: FormEvent) {
        e.preventDefault();
        const ok = await confirm({ title: t('common.confirm'), body: t('admin.providers.show.confirm_approve_body') });
        if (!ok) {
            return;
        }
        approveForm.patch(`/admin/providers/${profile.id}/approve`);
    }

    async function reject(e: FormEvent) {
        e.preventDefault();
        const ok = await confirm({
            title: t('common.confirm'),
            body: t('admin.providers.show.confirm_reject_body'),
            confirmVariant: 'danger',
        });
        if (!ok) {
            return;
        }
        rejectForm.patch(`/admin/providers/${profile.id}/reject`);
    }

    async function suspend(e: FormEvent) {
        e.preventDefault();
        const ok = await confirm({
            title: t('common.confirm'),
            body: t('admin.providers.show.confirm_suspend_body'),
            confirmVariant: 'danger',
        });
        if (!ok) {
            return;
        }
        suspendForm.patch(`/admin/providers/${profile.id}/suspend`);
    }

    const canDecide = profile.verification_status === 'pending';
    const canSuspend = profile.verification_status === 'approved';

    return (
        <AppLayout>
            <div className="mx-auto max-w-3xl p-4 sm:p-6">
                <Head title={t('admin.providers.show.title_prefix', { name: profile.business_name })} />
                <PageHeader
                    title={profile.business_name}
                    actions={
                        <Badge variant={VERIFICATION_STATUS_VARIANTS[profile.verification_status]}>
                            {t(VERIFICATION_STATUS_KEYS[profile.verification_status])}
                        </Badge>
                    }
                />

                <Card className="mt-6">
                    <dl className="space-y-3 text-sm">
                        <div>
                            <dt className="font-medium text-gray-700">{t('common.bio')}</dt>
                            <dd className="text-gray-800">{profile.bio || t('common.none')}</dd>
                        </div>
                        <div>
                            <dt className="font-medium text-gray-700">{t('common.categories')}</dt>
                            <dd className="text-gray-800">{profile.categories.join(', ') || t('common.none')}</dd>
                        </div>
                        <div>
                            <dt className="font-medium text-gray-700">{t('common.areas')}</dt>
                            <dd className="text-gray-800">{profile.areas.join(', ') || t('common.none')}</dd>
                        </div>
                        <div>
                            <dt className="font-medium text-gray-700">{t('admin.providers.show.applicant')}</dt>
                            <dd className="text-gray-800">
                                {profile.applicant_name} ({profile.applicant_email}
                                {profile.applicant_phone ? `, ${profile.applicant_phone}` : ''})
                            </dd>
                        </div>
                        {profile.verification_note && (
                            <div>
                                <dt className="font-medium text-gray-700">{t('admin.providers.show.internal_note')}</dt>
                                <dd className="text-gray-800">{profile.verification_note}</dd>
                            </div>
                        )}
                    </dl>
                </Card>

                {canDecide && (
                    <div className="mt-6 flex flex-col gap-4 sm:flex-row sm:items-start">
                        <form onSubmit={approve}>
                            <Button type="submit" variant="success" loading={approveForm.processing}>
                                {t('common.approve')}
                            </Button>
                        </form>

                        <form onSubmit={reject} className="flex flex-1 flex-col gap-3">
                            <FormField label={t('admin.providers.show.rejection_reason')} htmlFor="reject_note" error={rejectForm.errors.note}>
                                <Textarea value={rejectForm.data.note} onChange={(e) => rejectForm.setData('note', e.target.value)} />
                            </FormField>
                            <Button type="submit" variant="danger" loading={rejectForm.processing} className="self-start">
                                {t('common.reject')}
                            </Button>
                        </form>
                    </div>
                )}

                {canSuspend && (
                    <form onSubmit={suspend} className="mt-6 flex flex-col gap-3">
                        <FormField label={t('admin.providers.show.suspension_reason')} htmlFor="suspend_note" error={suspendForm.errors.note}>
                            <Textarea value={suspendForm.data.note} onChange={(e) => suspendForm.setData('note', e.target.value)} />
                        </FormField>
                        <Button type="submit" variant="danger" loading={suspendForm.processing} className="self-start">
                            {t('admin.providers.show.suspend')}
                        </Button>
                    </form>
                )}

                <p className="mt-6">
                    <Link href="/admin/providers" className="text-blue-600 underline hover:text-blue-800">
                        {t('nav.back_to_list')}
                    </Link>
                </p>

                {confirmDialog}
            </div>
        </AppLayout>
    );
}
