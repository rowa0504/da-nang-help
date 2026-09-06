import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ChangeEvent, FormEvent } from 'react';
import { AreaOption, CategoryOption, SharedProps, SupportedLocale } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
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

// Minimal Phase 4 form styling (Tailwind utility classes only, no new
// components/packages) so inputs are actually visible against Tailwind's
// base reset.
const fieldClass =
    'mt-1 block w-full max-w-xl rounded border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';
const textareaClass = `${fieldClass} min-h-32`;
const labelClass = 'block text-sm font-medium text-gray-700';
const errorClass = 'mt-1 text-sm text-red-600';
const primaryButtonClass =
    'rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50';
const linkClass = 'text-blue-600 underline hover:text-blue-800';

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
            <div className="mx-auto max-w-xl p-6 font-sans">
                <Head title={t('requests.create.title')} />
                <h1 className="mb-4 text-2xl font-semibold text-gray-900">{t('requests.create.heading')}</h1>

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div>
                        <label className={labelClass}>
                            {t('common.title')}
                            <input
                                type="text"
                                className={fieldClass}
                                value={data.title}
                                onChange={(e) => setData('title', e.target.value)}
                            />
                        </label>
                        {errors.title && <div className={errorClass}>{errors.title}</div>}
                    </div>

                    <div>
                        <label className={labelClass}>
                            {t('common.description')}
                            <textarea
                                className={textareaClass}
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                            />
                        </label>
                        {errors.description && <div className={errorClass}>{errors.description}</div>}
                    </div>

                    <div>
                        <label className={labelClass}>
                            {t('common.category')}
                            <select
                                className={fieldClass}
                                value={data.category_id}
                                onChange={(e) => setData('category_id', Number(e.target.value))}
                            >
                                <option value="">{t('requests.create.select_category')}</option>
                                {categories.map((category) => (
                                    <option key={category.id} value={category.id}>
                                        {category.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        {errors.category_id && <div className={errorClass}>{errors.category_id}</div>}
                    </div>

                    <div>
                        <label className={labelClass}>
                            {t('common.area')}
                            <select
                                className={fieldClass}
                                value={data.area_id}
                                onChange={(e) => setData('area_id', Number(e.target.value))}
                            >
                                <option value="">{t('requests.create.select_area')}</option>
                                {areas.map((area) => (
                                    <option key={area.id} value={area.id}>
                                        {area.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        {errors.area_id && <div className={errorClass}>{errors.area_id}</div>}
                    </div>

                    <div>
                        <label className={labelClass}>
                            {t('common.address')}
                            <input
                                type="text"
                                className={fieldClass}
                                value={data.address_text}
                                onChange={(e) => setData('address_text', e.target.value)}
                            />
                        </label>
                        {errors.address_text && <div className={errorClass}>{errors.address_text}</div>}
                    </div>

                    <div>
                        <label className={labelClass}>
                            {t('requests.create.latitude')}
                            <input
                                type="number"
                                inputMode="decimal"
                                step="0.0000001"
                                min="-90"
                                max="90"
                                className={fieldClass}
                                value={data.lat}
                                onChange={(e) => setData('lat', e.target.value)}
                            />
                        </label>
                        {errors.lat && <div className={errorClass}>{errors.lat}</div>}
                    </div>

                    <div>
                        <label className={labelClass}>
                            {t('requests.create.longitude')}
                            <input
                                type="number"
                                inputMode="decimal"
                                step="0.0000001"
                                min="-180"
                                max="180"
                                className={fieldClass}
                                value={data.lng}
                                onChange={(e) => setData('lng', e.target.value)}
                            />
                        </label>
                        {errors.lng && <div className={errorClass}>{errors.lng}</div>}
                    </div>

                    <fieldset className="border-0 p-0">
                        <legend className={labelClass}>{t('common.urgency')}</legend>
                        <label className="mr-4 inline-flex items-center gap-1 text-sm text-gray-700">
                            <input
                                type="radio"
                                name="urgency"
                                checked={data.urgency === 'normal'}
                                onChange={() => setData('urgency', 'normal')}
                            />
                            {t('status.urgency.normal')}
                        </label>
                        <label className="inline-flex items-center gap-1 text-sm text-gray-700">
                            <input
                                type="radio"
                                name="urgency"
                                checked={data.urgency === 'urgent'}
                                onChange={() => setData('urgency', 'urgent')}
                            />
                            {t('status.urgency.urgent')}
                        </label>
                    </fieldset>
                    {errors.urgency && <div className={errorClass}>{errors.urgency}</div>}

                    <div>
                        <label className={labelClass}>
                            {t('requests.create.language_label')}
                            <select
                                className={fieldClass}
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
                            </select>
                        </label>
                        {errors.source_locale && <div className={errorClass}>{errors.source_locale}</div>}
                    </div>

                    <div>
                        <label className={labelClass}>
                            {t('requests.create.photos_label')}
                            <div className="mt-1 max-w-xl rounded border border-dashed border-gray-300 p-3">
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    multiple
                                    onChange={onPhotosChange}
                                    className="block w-full text-sm text-gray-700"
                                />
                            </div>
                        </label>
                        {errors.photos && <div className={errorClass}>{errors.photos}</div>}
                        <p className="mt-1 text-xs text-gray-500">{t('requests.create.photos_help')}</p>
                    </div>

                    <button type="submit" disabled={processing} className={`${primaryButtonClass} self-start`}>
                        {t('requests.create.submit')}
                    </button>
                </form>

                <p className="mt-4">
                    <Link href="/requests" className={linkClass}>
                        {t('nav.view_my_requests')}
                    </Link>
                </p>
            </div>
        </AppLayout>
    );
}
