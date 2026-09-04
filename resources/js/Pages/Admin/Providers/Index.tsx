import { Head, Link } from '@inertiajs/react';

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
    return (
        <main style={{ fontFamily: 'sans-serif', padding: '2rem' }}>
            <Head title="Pending Providers" />
            <h1>Pending Provider Applications</h1>

            {profiles.length === 0 ? (
                <p>No pending applications.</p>
            ) : (
                <table style={{ borderCollapse: 'collapse', width: '100%' }}>
                    <thead>
                        <tr>
                            <th style={{ textAlign: 'left', borderBottom: '1px solid #ccc' }}>Business name</th>
                            <th style={{ textAlign: 'left', borderBottom: '1px solid #ccc' }}>Applicant</th>
                            <th style={{ textAlign: 'left', borderBottom: '1px solid #ccc' }}>Submitted</th>
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
                                    <Link href={`/admin/providers/${profile.id}`}>Review</Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}

            <p>
                <Link href="/dashboard">Back to dashboard</Link>
            </p>
        </main>
    );
}
