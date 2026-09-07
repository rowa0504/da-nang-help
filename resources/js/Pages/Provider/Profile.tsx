import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { AreaOption, CategoryOption, ProviderProfileData, ProviderVerificationStatus } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { TranslationKey } from '@/lang/en';

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

// Explicit, exhaustive correspondence table — a missing case here is a
// compile error, never a silent fallback to an untranslated raw value.
// 'rejected' is unreachable in ReadOnlyStatus (Profile() routes rejected
// profiles to EditableForm instead) but is included so the table stays
// total over ProviderVerificationStatus.
const STATUS_MESSAGE_KEYS: Record<ProviderVerificationStatus, TranslationKey> = {
    pending: 'provider.profile.status_message.pending',
    approved: 'provider.profile.status_message.approved',
    suspended: 'provider.profile.status_message.suspended',
    rejected: 'provider.profile.rejected_notice',
};

export default function Profile({ profile, categories, areas }: Props) {
    const isEditable = profile === null || profile.verification_status === 'rejected';

    if (!isEditable) {
        return <ReadOnlyStatus profile={profile as ProviderProfileData} />;
    }

    return <EditableForm profile={profile} categories={categories} areas={areas} />;
}

function ReadOnlyStatus({ profile }: { profile: ProviderProfileData }) {
    const { t } = useTranslation();

    return (
        <AppLayout>
            <div style={{ fontFamily: 'sans-serif', padding: '2rem', maxWidth: 480 }}>
                <Head title={t('provider.profile.title')} />
                <h1>{t('provider.profile.heading')}</h1>
                <p>{t(STATUS_MESSAGE_KEYS[profile.verification_status])}</p>
                <dl>
                    <dt>{t('provider.profile.business_name')}</dt>
                    <dd>{profile.business_name}</dd>
                    <dt>{t('common.bio')}</dt>
                    <dd>{profile.bio || t('common.none')}</dd>
                    <dt>{t('common.categories')}</dt>
                    <dd>{profile.category_names.join(', ') || t('common.none')}</dd>
                    <dt>{t('common.areas')}</dt>
                    <dd>{profile.area_names.join(', ') || t('common.none')}</dd>
                    <dt>{t('provider.profile.avg_rating')}</dt>
                    <dd>{profile.avg_rating} / 5</dd>
                    <dt>{t('provider.profile.completed_jobs')}</dt>
                    <dd>{profile.completed_jobs_count}</dd>
                </dl>
                <Link href="/dashboard">{t('nav.back_to_dashboard')}</Link>
            </div>
        </AppLayout>
    );
}

function EditableForm({ profile, categories, areas }: { profile: ProviderProfileData | null; categories: CategoryOption[]; areas: AreaOption[] }) {
    const { t } = useTranslation();
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
        <AppLayout>
            <div style={{ fontFamily: 'sans-serif', padding: '2rem', maxWidth: 480 }}>
                <Head title={t('provider.profile.title')} />
                <h1>{profile ? t('provider.profile.heading_update') : t('provider.profile.heading_setup')}</h1>
                {profile?.verification_status === 'rejected' && <p>{t('provider.profile.rejected_notice')}</p>}

                <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
                    <label>
                        {t('provider.profile.business_name')}
                        <input
                            type="text"
                            value={data.business_name}
                            onChange={(e) => setData('business_name', e.target.value)}
                        />
                    </label>
                    {errors.business_name && <div style={{ color: 'crimson' }}>{errors.business_name}</div>}

                    <label>
                        {t('provider.profile.bio_optional')}
                        <textarea value={data.bio} onChange={(e) => setData('bio', e.target.value)} />
                    </label>
                    {errors.bio && <div style={{ color: 'crimson' }}>{errors.bio}</div>}

                    <fieldset style={{ border: 0, padding: 0 }}>
                        <legend>{t('provider.profile.categories_legend')}</legend>
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
                        <legend>{t('provider.profile.areas_legend')}</legend>
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
                        {t('provider.profile.submit')}
                    </button>
                </form>
            </div>
        </AppLayout>
    );
}
