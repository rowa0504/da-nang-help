import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { AreaOption, CategoryOption, ProviderProfileData, ProviderVerificationStatus } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/PageHeader';
import { Card } from '@/Components/Card';
import { Badge, BadgeVariant } from '@/Components/Badge';
import { FormField } from '@/Components/FormField';
import { Input } from '@/Components/Input';
import { Textarea } from '@/Components/Textarea';
import { Checkbox } from '@/Components/Checkbox';
import { Button } from '@/Components/Button';
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
            <div className="mx-auto max-w-xl p-4 sm:p-6">
                <Head title={t('provider.profile.title')} />
                <PageHeader
                    title={t('provider.profile.heading')}
                    actions={
                        <Badge variant={VERIFICATION_STATUS_VARIANTS[profile.verification_status]}>
                            {t(VERIFICATION_STATUS_KEYS[profile.verification_status])}
                        </Badge>
                    }
                />
                <p className="mt-2 text-sm text-gray-600">{t(STATUS_MESSAGE_KEYS[profile.verification_status])}</p>

                <Card className="mt-6">
                    <dl className="space-y-3 text-sm">
                        <div>
                            <dt className="font-medium text-gray-700">{t('provider.profile.business_name')}</dt>
                            <dd className="text-gray-800">{profile.business_name}</dd>
                        </div>
                        <div>
                            <dt className="font-medium text-gray-700">{t('common.bio')}</dt>
                            <dd className="text-gray-800">{profile.bio || t('common.none')}</dd>
                        </div>
                        <div>
                            <dt className="font-medium text-gray-700">{t('common.categories')}</dt>
                            <dd className="text-gray-800">{profile.category_names.join(', ') || t('common.none')}</dd>
                        </div>
                        <div>
                            <dt className="font-medium text-gray-700">{t('common.areas')}</dt>
                            <dd className="text-gray-800">{profile.area_names.join(', ') || t('common.none')}</dd>
                        </div>
                        <div>
                            <dt className="font-medium text-gray-700">{t('provider.profile.avg_rating')}</dt>
                            <dd className="text-gray-800">{profile.avg_rating} / 5</dd>
                        </div>
                        <div>
                            <dt className="font-medium text-gray-700">{t('provider.profile.completed_jobs')}</dt>
                            <dd className="text-gray-800">{profile.completed_jobs_count}</dd>
                        </div>
                    </dl>
                </Card>

                <p className="mt-6">
                    <Link href="/dashboard" className="text-blue-600 underline hover:text-blue-800">
                        {t('nav.back_to_dashboard')}
                    </Link>
                </p>
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
        setData(field, current.includes(id) ? current.filter((existing) => existing !== id) : [...current, id]);
    }

    return (
        <AppLayout>
            <div className="mx-auto max-w-xl p-4 sm:p-6">
                <Head title={t('provider.profile.title')} />
                <PageHeader title={profile ? t('provider.profile.heading_update') : t('provider.profile.heading_setup')} />
                {profile?.verification_status === 'rejected' && <p className="mt-2 text-sm text-red-600">{t('provider.profile.rejected_notice')}</p>}

                <form onSubmit={submit} className="mt-6 flex flex-col gap-4">
                    <FormField label={t('provider.profile.business_name')} htmlFor="business_name" error={errors.business_name}>
                        <Input type="text" value={data.business_name} onChange={(e) => setData('business_name', e.target.value)} />
                    </FormField>

                    <FormField label={t('provider.profile.bio_optional')} htmlFor="bio" error={errors.bio}>
                        <Textarea value={data.bio} onChange={(e) => setData('bio', e.target.value)} />
                    </FormField>

                    <fieldset className="border-0 p-0">
                        <legend className="block text-sm font-medium text-gray-700">{t('provider.profile.categories_legend')}</legend>
                        <div className="mt-2 flex flex-col gap-2">
                            {categories.map((category) => (
                                <Checkbox
                                    key={category.id}
                                    label={category.name}
                                    checked={data.category_ids.includes(category.id)}
                                    onChange={() => toggle('category_ids', category.id)}
                                />
                            ))}
                        </div>
                        {errors.category_ids && <p className="mt-1 text-sm text-red-600">{errors.category_ids}</p>}
                    </fieldset>

                    <fieldset className="border-0 p-0">
                        <legend className="block text-sm font-medium text-gray-700">{t('provider.profile.areas_legend')}</legend>
                        <div className="mt-2 flex flex-col gap-2">
                            {areas.map((area) => (
                                <Checkbox
                                    key={area.id}
                                    label={area.name}
                                    checked={data.area_ids.includes(area.id)}
                                    onChange={() => toggle('area_ids', area.id)}
                                />
                            ))}
                        </div>
                        {errors.area_ids && <p className="mt-1 text-sm text-red-600">{errors.area_ids}</p>}
                    </fieldset>

                    <Button type="submit" loading={processing} className="self-start">
                        {t('provider.profile.submit')}
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
