import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface ProviderDetail {
    id: number;
    business_name: string;
    bio: string | null;
    verification_status: string;
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

export default function Show({ profile }: Props) {
    const approveForm = useForm({});
    const rejectForm = useForm({ note: '' });

    function approve(e: FormEvent) {
        e.preventDefault();
        approveForm.patch(`/admin/providers/${profile.id}/approve`);
    }

    function reject(e: FormEvent) {
        e.preventDefault();
        rejectForm.patch(`/admin/providers/${profile.id}/reject`);
    }

    const canDecide = profile.verification_status === 'pending';

    return (
        <main style={{ fontFamily: 'sans-serif', padding: '2rem', maxWidth: 480 }}>
            <Head title={`Provider: ${profile.business_name}`} />
            <h1>{profile.business_name}</h1>
            <p>Status: {profile.verification_status}</p>

            <dl>
                <dt>Bio</dt>
                <dd>{profile.bio || '—'}</dd>
                <dt>Categories</dt>
                <dd>{profile.categories.join(', ') || '—'}</dd>
                <dt>Areas</dt>
                <dd>{profile.areas.join(', ') || '—'}</dd>
                <dt>Applicant</dt>
                <dd>
                    {profile.applicant_name} ({profile.applicant_email}
                    {profile.applicant_phone ? `, ${profile.applicant_phone}` : ''})
                </dd>
                {profile.verification_note && (
                    <>
                        <dt>Internal note (Admin only)</dt>
                        <dd>{profile.verification_note}</dd>
                    </>
                )}
            </dl>

            {canDecide && (
                <>
                    <form onSubmit={approve}>
                        <button type="submit" disabled={approveForm.processing}>
                            Approve
                        </button>
                    </form>

                    <form onSubmit={reject} style={{ marginTop: '1rem' }}>
                        <label>
                            Rejection reason (internal)
                            <textarea
                                value={rejectForm.data.note}
                                onChange={(e) => rejectForm.setData('note', e.target.value)}
                            />
                        </label>
                        {rejectForm.errors.note && <div style={{ color: 'crimson' }}>{rejectForm.errors.note}</div>}
                        <button type="submit" disabled={rejectForm.processing}>
                            Reject
                        </button>
                    </form>
                </>
            )}

            <p>
                <Link href="/admin/providers">Back to list</Link>
            </p>
        </main>
    );
}
