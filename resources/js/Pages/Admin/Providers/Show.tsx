import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { ProviderVerificationStatus } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { useTranslation } from '@/hooks/useTranslation';
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

export default function Show({ profile }: Props) {
    const { t } = useTranslation();
    const approveForm = useForm({});
    const rejectForm = useForm({ note: '' });
    const suspendForm = useForm({ note: '' });

    function approve(e: FormEvent) {
        e.preventDefault();
        approveForm.patch(`/admin/providers/${profile.id}/approve`);
    }

    function reject(e: FormEvent) {
        e.preventDefault();
        rejectForm.patch(`/admin/providers/${profile.id}/reject`);
    }

    function suspend(e: FormEvent) {
        e.preventDefault();
        suspendForm.patch(`/admin/providers/${profile.id}/suspend`);
    }

    const canDecide = profile.verification_status === 'pending';
    const canSuspend = profile.verification_status === 'approved';

    return (
        <AppLayout>
            <div style={{ fontFamily: 'sans-serif', padding: '2rem', maxWidth: 480 }}>
                <Head title={t('admin.providers.show.title_prefix', { name: profile.business_name })} />
                <h1>{profile.business_name}</h1>
                <p>{t('common.status_label', { status: t(VERIFICATION_STATUS_KEYS[profile.verification_status]) })}</p>

                <dl>
                    <dt>{t('common.bio')}</dt>
                    <dd>{profile.bio || t('common.none')}</dd>
                    <dt>{t('common.categories')}</dt>
                    <dd>{profile.categories.join(', ') || t('common.none')}</dd>
                    <dt>{t('common.areas')}</dt>
                    <dd>{profile.areas.join(', ') || t('common.none')}</dd>
                    <dt>{t('admin.providers.show.applicant')}</dt>
                    <dd>
                        {profile.applicant_name} ({profile.applicant_email}
                        {profile.applicant_phone ? `, ${profile.applicant_phone}` : ''})
                    </dd>
                    {profile.verification_note && (
                        <>
                            <dt>{t('admin.providers.show.internal_note')}</dt>
                            <dd>{profile.verification_note}</dd>
                        </>
                    )}
                </dl>

                {canDecide && (
                    <>
                        <form onSubmit={approve}>
                            <button type="submit" disabled={approveForm.processing}>
                                {t('common.approve')}
                            </button>
                        </form>

                        <form onSubmit={reject} style={{ marginTop: '1rem' }}>
                            <label>
                                {t('admin.providers.show.rejection_reason')}
                                <textarea
                                    value={rejectForm.data.note}
                                    onChange={(e) => rejectForm.setData('note', e.target.value)}
                                />
                            </label>
                            {rejectForm.errors.note && <div style={{ color: 'crimson' }}>{rejectForm.errors.note}</div>}
                            <button type="submit" disabled={rejectForm.processing}>
                                {t('common.reject')}
                            </button>
                        </form>
                    </>
                )}

                {canSuspend && (
                    <form onSubmit={suspend} style={{ marginTop: '1rem' }}>
                        <label>
                            {t('admin.providers.show.suspension_reason')}
                            <textarea
                                value={suspendForm.data.note}
                                onChange={(e) => suspendForm.setData('note', e.target.value)}
                            />
                        </label>
                        {suspendForm.errors.note && <div style={{ color: 'crimson' }}>{suspendForm.errors.note}</div>}
                        <button type="submit" disabled={suspendForm.processing}>
                            {t('admin.providers.show.suspend')}
                        </button>
                    </form>
                )}

                <p>
                    <Link href="/admin/providers">{t('nav.back_to_list')}</Link>
                </p>
            </div>
        </AppLayout>
    );
}
