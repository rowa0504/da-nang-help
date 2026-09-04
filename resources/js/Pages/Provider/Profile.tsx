import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { AreaOption, CategoryOption, ProviderProfileData } from '@/types';

interface Props {
    profile: ProviderProfileData | null;
    categories: CategoryOption[];
    areas: AreaOption[];
}

type ProfileForm = {
    business_name: string;
    bio: string;
    category_ids: number[];
    area_ids: number[];
};

export default function Profile({ profile, categories, areas }: Props) {
    const isEditable = profile === null || profile.verification_status === 'rejected';

    if (!isEditable) {
        return <ReadOnlyStatus profile={profile as ProviderProfileData} categories={categories} areas={areas} />;
    }

    return <EditableForm profile={profile} categories={categories} areas={areas} />;
}

function ReadOnlyStatus({ profile, categories, areas }: { profile: ProviderProfileData; categories: CategoryOption[]; areas: AreaOption[] }) {
    const categoryNames = categories.filter((c) => profile.category_ids.includes(c.id)).map((c) => c.name);
    const areaNames = areas.filter((a) => profile.area_ids.includes(a.id)).map((a) => a.name);

    const statusMessage: Record<string, string> = {
        pending: 'Your profile is under review by our team.',
        approved: 'Your profile has been approved.',
        suspended: 'Your provider account is currently suspended.',
    };

    return (
        <main style={{ fontFamily: 'sans-serif', padding: '2rem', maxWidth: 480 }}>
            <Head title="Provider Profile" />
            <h1>Provider Profile</h1>
            <p>{statusMessage[profile.verification_status]}</p>
            <dl>
                <dt>Business name</dt>
                <dd>{profile.business_name}</dd>
                <dt>Bio</dt>
                <dd>{profile.bio || '—'}</dd>
                <dt>Categories</dt>
                <dd>{categoryNames.join(', ') || '—'}</dd>
                <dt>Areas</dt>
                <dd>{areaNames.join(', ') || '—'}</dd>
            </dl>
            <Link href="/dashboard">Back to dashboard</Link>
        </main>
    );
}

function EditableForm({ profile, categories, areas }: { profile: ProviderProfileData | null; categories: CategoryOption[]; areas: AreaOption[] }) {
    const { data, setData, post, processing, errors } = useForm<ProfileForm>({
        business_name: profile?.business_name ?? '',
        bio: profile?.bio ?? '',
        category_ids: profile?.category_ids ?? [],
        area_ids: profile?.area_ids ?? [],
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/provider/profile');
    }

    function toggle(field: 'category_ids' | 'area_ids', id: number) {
        const current = data[field];
        setData(
            field,
            current.includes(id) ? current.filter((existing) => existing !== id) : [...current, id],
        );
    }

    return (
        <main style={{ fontFamily: 'sans-serif', padding: '2rem', maxWidth: 480 }}>
            <Head title="Provider Profile" />
            <h1>{profile ? 'Update and resubmit your profile' : 'Set up your provider profile'}</h1>
            {profile?.verification_status === 'rejected' && (
                <p>Your previous application was rejected. Please review your details and resubmit.</p>
            )}

            <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
                <label>
                    Business name
                    <input
                        type="text"
                        value={data.business_name}
                        onChange={(e) => setData('business_name', e.target.value)}
                    />
                </label>
                {errors.business_name && <div style={{ color: 'crimson' }}>{errors.business_name}</div>}

                <label>
                    Bio (optional)
                    <textarea value={data.bio} onChange={(e) => setData('bio', e.target.value)} />
                </label>
                {errors.bio && <div style={{ color: 'crimson' }}>{errors.bio}</div>}

                <fieldset style={{ border: 0, padding: 0 }}>
                    <legend>Categories you can handle</legend>
                    {categories.map((category) => (
                        <label key={category.id} style={{ display: 'block' }}>
                            <input
                                type="checkbox"
                                checked={data.category_ids.includes(category.id)}
                                onChange={() => toggle('category_ids', category.id)}
                            />{' '}
                            {category.name}
                        </label>
                    ))}
                </fieldset>
                {errors.category_ids && <div style={{ color: 'crimson' }}>{errors.category_ids}</div>}

                <fieldset style={{ border: 0, padding: 0 }}>
                    <legend>Areas you can serve</legend>
                    {areas.map((area) => (
                        <label key={area.id} style={{ display: 'block' }}>
                            <input
                                type="checkbox"
                                checked={data.area_ids.includes(area.id)}
                                onChange={() => toggle('area_ids', area.id)}
                            />{' '}
                            {area.name}
                        </label>
                    ))}
                </fieldset>
                {errors.area_ids && <div style={{ color: 'crimson' }}>{errors.area_ids}</div>}

                <button type="submit" disabled={processing}>
                    Submit for review
                </button>
            </form>
        </main>
    );
}
