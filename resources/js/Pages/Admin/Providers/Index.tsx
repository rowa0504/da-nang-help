import { Head, Link } from '@inertiajs/react';
import { AppLayout } from '@/Layouts/AppLayout';
import { useTranslation } from '@/hooks/useTranslation';

interface ProviderListItem {
    id: number;
    business_name: string;
    applicant_name: string;
    applicant_email: string;
    created_at: string | null;
}

interface Props {
    profiles: ProviderListItem[];
}

export default function Index({ profiles }: Props) {
    const { t } = useTranslation();

    return (
        <AppLayout>
            <div style={{ fontFamily: 'sans-serif', padding: '2rem' }}>
                <Head title={t('admin.providers.index.title')} />
                <h1>{t('admin.providers.index.heading')}</h1>

                {profiles.length === 0 ? (
                    <p>{t('admin.providers.index.empty')}</p>
                ) : (
                    <table style={{ borderCollapse: 'collapse', width: '100%' }}>
                        <thead>
                            <tr>
                                <th style={{ textAlign: 'left', borderBottom: '1px solid #ccc' }}>{t('admin.providers.index.business_name')}</th>
                                <th style={{ textAlign: 'left', borderBottom: '1px solid #ccc' }}>{t('admin.providers.index.applicant')}</th>
                                <th style={{ textAlign: 'left', borderBottom: '1px solid #ccc' }}>{t('admin.providers.index.submitted')}</th>
                                <th style={{ borderBottom: '1px solid #ccc' }}></th>
                            </tr>
                        </thead>
                        <tbody>
                            {profiles.map((profile) => (
                                <tr key={profile.id}>
                                    <td>{profile.business_name}</td>
                                    <td>
                                        {profile.applicant_name} ({profile.applicant_email})
                                    </td>
                                    <td>{profile.created_at}</td>
                                    <td>
                                        <Link href={`/admin/providers/${profile.id}`}>{t('common.review_action')}</Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}

                <p>
                    <Link href="/dashboard">{t('nav.back_to_dashboard')}</Link>
                </p>
            </div>
        </AppLayout>
    );
}
