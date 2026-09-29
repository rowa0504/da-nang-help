import { Head, Link, useForm } from '@inertiajs/react';
import { ChangeEvent, FormEvent } from 'react';
import { AreaOption, CategoryOption, ServiceRequestEditData, SupportedLocale } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/PageHeader';
import { FormField } from '@/Components/FormField';
import { Input } from '@/Components/Input';
import { Textarea } from '@/Components/Textarea';
import { Select } from '@/Components/Select';
import { Checkbox } from '@/Components/Checkbox';
import { Button } from '@/Components/Button';
import { useTranslation } from '@/hooks/useTranslation';

interface Props {
    serviceRequest: ServiceRequestEditData;
    categories: CategoryOption[];
    areas: AreaOption[];
}

type EditForm = {
    title: string;
    description: string;
    category_id: number | '';
    area_id: number | '';
    address_text: string;
    urgency: 'normal' | 'urgent';
    remove_photo_ids: number[];
    photos: File[];
};

const LANGUAGE_LABEL_KEYS: Record<SupportedLocale, 'language.en' | 'language.ja' | 'language.vi'> = {
    en: 'language.en',
    ja: 'language.ja',
    vi: 'language.vi',
};

const MAX_PHOTOS = 5;

export default function Edit({ serviceRequest, categories, areas }: Props) {
    const { t } = useTranslation();

    const { data, setData, transform, post, processing, errors } = useForm<EditForm>({
        title: serviceRequest.title,
        description: serviceRequest.description,
        category_id: serviceRequest.category_id,
        area_id: serviceRequest.area_id,
        address_text: serviceRequest.address_text,
        urgency: serviceRequest.urgency,
        remove_photo_ids: [],
        photos: [],
    });

    const keptPhotoCount = serviceRequest.photos.length - data.remove_photo_ids.length;
    const remainingSlots = Math.max(0, MAX_PHOTOS - keptPhotoCount);

    function submit(e: FormEvent) {
        e.preventDefault();
        // Laravel can't parse a multipart PATCH body natively, so — same
        // workaround Inertia's own docs recommend for file uploads on an
        // update — this sends a real POST with a _method field spoofing
        // PATCH; the route itself is still declared as PATCH.
        transform((current) => ({ ...current, _method: 'patch' }));
        post(`/requests/${serviceRequest.id}`, { forceFormData: true });
    }

    function toggleRemovePhoto(photoId: number) {
        setData(
            'remove_photo_ids',
            data.remove_photo_ids.includes(photoId)
                ? data.remove_photo_ids.filter((id) => id !== photoId)
                : [...data.remove_photo_ids, photoId],
        );
    }

    function onPhotosChange(e: ChangeEvent<HTMLInputElement>) {
        // Capped at MAX_PHOTOS (not the dynamic remainingSlots): slicing to
        // the *current* remaining-slot count would silently drop files the
        // user just selected whenever they've already checked a removal —
        // with no visible feedback at all. Selecting more than fits is
        // instead caught by the server's cross-field validation (see
        // UpdateServiceRequestRequest::withValidator()), which shows a real
        // error message under the field.
        const files = e.target.files ? Array.from(e.target.files).slice(0, MAX_PHOTOS) : [];
        setData('photos', files);
    }

    const isOtherCategorySelected = categories.some((category) => category.slug === 'other' && category.id === data.category_id);

    return (
        <AppLayout>
            <div className="mx-auto max-w-3xl p-4 sm:p-6">
                <Head title={t('requests.edit.title')} />
                <PageHeader title={t('requests.edit.heading')} />

                <form onSubmit={submit} className="mt-6 flex flex-col gap-4">
                    <FormField
                        label={isOtherCategorySelected ? t('requests.create.other_title_label') : t('common.title')}
                        htmlFor="title"
                        error={errors.title}
                        hint={isOtherCategorySelected ? t('requests.create.other_title_hint') : undefined}
                    >
                        <Input
                            type="text"
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            placeholder={isOtherCategorySelected ? t('requests.create.other_title_placeholder') : undefined}
                        />
                    </FormField>

                    <FormField label={t('common.description')} htmlFor="description" error={errors.description}>
                        <Textarea value={data.description} onChange={(e) => setData('description', e.target.value)} />
                    </FormField>

                    <FormField label={t('common.category')} htmlFor="category_id" error={errors.category_id}>
                        <Select value={data.category_id} onChange={(e) => setData('category_id', Number(e.target.value))}>
                            {categories.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.is_inactive ? `${category.name} ${t('requests.edit.inactive_option_suffix')}` : category.name}
                                </option>
                            ))}
                        </Select>
                    </FormField>

                    <FormField label={t('common.area')} htmlFor="area_id" error={errors.area_id}>
                        <Select value={data.area_id} onChange={(e) => setData('area_id', Number(e.target.value))}>
                            {areas.map((area) => (
                                <option key={area.id} value={area.id}>
                                    {area.is_inactive ? `${area.name} ${t('requests.edit.inactive_option_suffix')}` : area.name}
                                </option>
                            ))}
                        </Select>
                    </FormField>

                    <FormField label={t('common.address')} htmlFor="address_text" error={errors.address_text}>
                        <Input type="text" value={data.address_text} onChange={(e) => setData('address_text', e.target.value)} />
                    </FormField>

                    <fieldset className="border-0 p-0">
                        <legend className="block text-sm font-medium text-gray-700">{t('common.urgency')}</legend>
                        <label className="mr-4 inline-flex items-center gap-1 text-sm text-gray-700">
                            <input
                                type="radio"
                                name="urgency"
                                checked={data.urgency === 'normal'}
                                onChange={() => setData('urgency', 'normal')}
                                className="h-4 w-4 border-gray-300 text-brand-600 focus:ring-brand-600"
                            />
                            {t('status.urgency.normal')}
                        </label>
                        <label className="inline-flex items-center gap-1 text-sm text-gray-700">
                            <input
                                type="radio"
                                name="urgency"
                                checked={data.urgency === 'urgent'}
                                onChange={() => setData('urgency', 'urgent')}
                                className="h-4 w-4 border-gray-300 text-brand-600 focus:ring-brand-600"
                            />
                            {t('status.urgency.urgent')}
                        </label>
                    </fieldset>
                    {errors.urgency && <p className="text-sm text-red-600">{errors.urgency}</p>}

                    <div>
                        <span className="block text-sm font-medium text-gray-700">{t('requests.edit.original_language_label')}</span>
                        <p className="mt-1 text-sm text-gray-800">{t(LANGUAGE_LABEL_KEYS[serviceRequest.source_locale])}</p>
                        <p className="mt-1 text-xs text-gray-500">{t('requests.edit.original_language_hint')}</p>
                    </div>

                    {serviceRequest.photos.length > 0 && (
                        <div>
                            <span className="block text-sm font-medium text-gray-700">{t('requests.edit.existing_photos_label')}</span>
                            <div className="mt-2 flex flex-wrap gap-3">
                                {serviceRequest.photos.map((photo) => {
                                    const markedForRemoval = data.remove_photo_ids.includes(photo.id);
                                    return (
                                        <div key={photo.id} className="flex flex-col items-center gap-1">
                                            <img
                                                src={photo.url}
                                                alt=""
                                                className={['h-32 w-32 rounded border object-cover', markedForRemoval ? 'border-red-400 opacity-50' : 'border-gray-200'].join(
                                                    ' ',
                                                )}
                                            />
                                            <Checkbox
                                                label={markedForRemoval ? t('requests.edit.remove_photo') : t('requests.edit.keep_photo')}
                                                checked={markedForRemoval}
                                                onChange={() => toggleRemovePhoto(photo.id)}
                                            />
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    )}

                    <div>
                        <label htmlFor="photos" className="block text-sm font-medium text-gray-700">
                            {t('requests.edit.add_photos_label')}
                        </label>
                        <div className="mt-1 rounded border border-dashed border-gray-300 p-3">
                            <input
                                id="photos"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                multiple
                                disabled={remainingSlots === 0}
                                onChange={onPhotosChange}
                                className="block w-full text-sm text-gray-700 disabled:cursor-not-allowed disabled:opacity-50"
                            />
                        </div>
                        {errors.photos && <p className="mt-1 text-sm text-red-600">{errors.photos}</p>}
                        {errors.remove_photo_ids && <p className="mt-1 text-sm text-red-600">{errors.remove_photo_ids}</p>}
                        <p className="mt-1 text-xs text-gray-500">{t('requests.edit.photos_limit_hint')}</p>
                    </div>

                    <Button type="submit" loading={processing} className="self-start">
                        {t('requests.edit.submit')}
                    </Button>
                </form>

                <p className="mt-4">
                    <Link href={`/requests/${serviceRequest.id}`} className="text-brand-600 underline hover:text-brand-700">
                        {t('nav.back_to_my_requests')}
                    </Link>
                </p>
            </div>
        </AppLayout>
    );
}
