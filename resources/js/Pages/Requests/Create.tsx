import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ChangeEvent, FormEvent } from 'react';
import { AreaOption, CategoryOption, SharedProps, SupportedLocale } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/PageHeader';
import { FormField } from '@/Components/FormField';
import { Input } from '@/Components/Input';
import { Textarea } from '@/Components/Textarea';
import { Select } from '@/Components/Select';
import { Button } from '@/Components/Button';
import { useTranslation } from '@/hooks/useTranslation';

interface Props {
    categories: CategoryOption[];
    areas: AreaOption[];
}

type CreateForm = {
    title: string;
    description: string;
    category_id: number | '';
    area_id: number | '';
    address_text: string;
    lat: string;
    lng: string;
    urgency: 'normal' | 'urgent';
    source_locale: SupportedLocale;
    photos: File[];
};

// Explicit, exhaustive correspondence table — avoids casting the raw
// <select> value to SupportedLocale.
const SOURCE_LOCALE_OPTIONS: { value: SupportedLocale; labelKey: 'language.en' | 'language.ja' | 'language.vi' }[] = [
    { value: 'en', labelKey: 'language.en' },
    { value: 'ja', labelKey: 'language.ja' },
    { value: 'vi', labelKey: 'language.vi' },
];

function isSupportedLocale(value: string): value is SupportedLocale {
    return SOURCE_LOCALE_OPTIONS.some((option) => option.value === value);
}

export default function Create({ categories, areas }: Props) {
    const { auth } = usePage<SharedProps>().props;
    const { t } = useTranslation();
    const rawLocale = auth.user?.locale;
    const defaultLocale: SupportedLocale = rawLocale !== undefined && isSupportedLocale(rawLocale) ? rawLocale : 'en';

    const { data, setData, post, processing, errors } = useForm<CreateForm>({
        title: '',
        description: '',
        category_id: '',
        area_id: '',
        address_text: '',
        lat: '',
        lng: '',
        urgency: 'normal',
        source_locale: defaultLocale,
        photos: [],
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        // forceFormData is required because `photos` is an array of File
        // objects (Inertia's automatic FormData detection only inspects
        // top-level values).
        post('/requests', { forceFormData: true });
    }

    function onPhotosChange(e: ChangeEvent<HTMLInputElement>) {
        const files = e.target.files ? Array.from(e.target.files).slice(0, 5) : [];
        setData('photos', files);
    }

    return (
        <AppLayout>
            <div className="mx-auto max-w-3xl p-4 sm:p-6">
                <Head title={t('requests.create.title')} />
                <PageHeader title={t('requests.create.heading')} />

                <form onSubmit={submit} className="mt-6 flex flex-col gap-4">
                    <FormField label={t('common.title')} htmlFor="title" error={errors.title}>
                        <Input type="text" value={data.title} onChange={(e) => setData('title', e.target.value)} />
                    </FormField>

                    <FormField label={t('common.description')} htmlFor="description" error={errors.description}>
                        <Textarea value={data.description} onChange={(e) => setData('description', e.target.value)} />
                    </FormField>

                    <FormField label={t('common.category')} htmlFor="category_id" error={errors.category_id}>
                        <Select value={data.category_id} onChange={(e) => setData('category_id', Number(e.target.value))}>
                            <option value="">{t('requests.create.select_category')}</option>
                            {categories.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.name}
                                </option>
                            ))}
                        </Select>
                    </FormField>

                    <FormField label={t('common.area')} htmlFor="area_id" error={errors.area_id}>
                        <Select value={data.area_id} onChange={(e) => setData('area_id', Number(e.target.value))}>
                            <option value="">{t('requests.create.select_area')}</option>
                            {areas.map((area) => (
                                <option key={area.id} value={area.id}>
                                    {area.name}
                                </option>
                            ))}
                        </Select>
                    </FormField>

                    <FormField label={t('common.address')} htmlFor="address_text" error={errors.address_text}>
                        <Input type="text" value={data.address_text} onChange={(e) => setData('address_text', e.target.value)} />
                    </FormField>

                    <FormField label={t('requests.create.latitude')} htmlFor="lat" error={errors.lat}>
                        <Input
                            type="number"
                            inputMode="decimal"
                            step="0.0000001"
                            min="-90"
                            max="90"
                            value={data.lat}
                            onChange={(e) => setData('lat', e.target.value)}
                        />
                    </FormField>

                    <FormField label={t('requests.create.longitude')} htmlFor="lng" error={errors.lng}>
                        <Input
                            type="number"
                            inputMode="decimal"
                            step="0.0000001"
                            min="-180"
                            max="180"
                            value={data.lng}
                            onChange={(e) => setData('lng', e.target.value)}
                        />
                    </FormField>

                    <fieldset className="border-0 p-0">
                        <legend className="block text-sm font-medium text-gray-700">{t('common.urgency')}</legend>
                        <label className="mr-4 inline-flex items-center gap-1 text-sm text-gray-700">
                            <input
                                type="radio"
                                name="urgency"
                                checked={data.urgency === 'normal'}
                                onChange={() => setData('urgency', 'normal')}
                                className="h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                            {t('status.urgency.normal')}
                        </label>
                        <label className="inline-flex items-center gap-1 text-sm text-gray-700">
                            <input
                                type="radio"
                                name="urgency"
                                checked={data.urgency === 'urgent'}
                                onChange={() => setData('urgency', 'urgent')}
                                className="h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                            {t('status.urgency.urgent')}
                        </label>
                    </fieldset>
                    {errors.urgency && <p className="text-sm text-red-600">{errors.urgency}</p>}

                    <FormField label={t('requests.create.language_label')} htmlFor="source_locale" error={errors.source_locale}>
                        <Select
                            value={data.source_locale}
                            onChange={(e) => {
                                if (isSupportedLocale(e.target.value)) {
                                    setData('source_locale', e.target.value);
                                }
                            }}
                        >
                            {SOURCE_LOCALE_OPTIONS.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {t(option.labelKey)}
                                </option>
                            ))}
                        </Select>
                    </FormField>

                    <div>
                        <label htmlFor="photos" className="block text-sm font-medium text-gray-700">
                            {t('requests.create.photos_label')}
                        </label>
                        <div className="mt-1 rounded border border-dashed border-gray-300 p-3">
                            <input
                                id="photos"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                multiple
                                onChange={onPhotosChange}
                                className="block w-full text-sm text-gray-700"
                            />
                        </div>
                        {errors.photos && <p className="mt-1 text-sm text-red-600">{errors.photos}</p>}
                        <p className="mt-1 text-xs text-gray-500">{t('requests.create.photos_help')}</p>
                    </div>

                    <Button type="submit" loading={processing} className="self-start">
                        {t('requests.create.submit')}
                    </Button>
                </form>

                <p className="mt-4">
                    <Link href="/requests" className="text-blue-600 underline hover:text-blue-800">
                        {t('nav.view_my_requests')}
                    </Link>
                </p>
            </div>
        </AppLayout>
    );
}
