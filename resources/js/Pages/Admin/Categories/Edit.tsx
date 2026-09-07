import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { AdminCategoryDetail, CategoryOptionWithDepth } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { useTranslation } from '@/hooks/useTranslation';

interface Props {
    category: AdminCategoryDetail;
    categoryOptions: CategoryOptionWithDepth[];
}

type CategoryEditForm = {
    parent_id: number | null;
    is_active: boolean;
    sort_order: number;
    names: { en: string; ja: string; vi: string };
};

const fieldClass =
    'mt-1 block w-full max-w-xl rounded border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';
const labelClass = 'block text-sm font-medium text-gray-700';
const errorClass = 'mt-1 text-sm text-red-600';
const primaryButtonClass =
    'rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50';
const linkClass = 'text-blue-600 underline hover:text-blue-800';

export default function Edit({ category, categoryOptions }: Props) {
    const { t } = useTranslation();
    const { data, setData, patch, processing, errors } = useForm<CategoryEditForm>({
        parent_id: category.parent_id,
        is_active: category.is_active,
        sort_order: category.sort_order,
        names: { ...category.names },
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        patch(`/admin/categories/${category.id}`);
    }

    return (
        <AppLayout>
            <div className="mx-auto max-w-xl p-6 font-sans">
                <Head title={t('admin.categories.edit.title')} />
                <h1 className="mb-4 text-2xl font-semibold text-gray-900">{t('admin.categories.edit.title')}</h1>

                <p className="text-sm text-gray-600">
                    {t('common.slug')}: <span className="font-mono">{category.slug}</span>
                </p>

                <form onSubmit={submit} className="mt-4 flex flex-col gap-4">
                    <div>
                        <label className={labelClass}>
                            {t('admin.categories.form.parent')}
                            <select
                                className={fieldClass}
                                value={data.parent_id ?? ''}
                                onChange={(e) => setData('parent_id', e.target.value === '' ? null : Number(e.target.value))}
                            >
                                <option value="">{t('admin.categories.form.parent_none')}</option>
                                {categoryOptions.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {'— '.repeat(option.depth) + option.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        {errors.parent_id && <div className={errorClass}>{errors.parent_id}</div>}
                    </div>

                    <div>
                        <label className={labelClass}>
                            {t('admin.categories.form.name_en')}
                            <input
                                type="text"
                                className={fieldClass}
                                value={data.names.en}
                                onChange={(e) => setData('names', { ...data.names, en: e.target.value })}
                            />
                        </label>
                        {errors['names.en'] && <div className={errorClass}>{errors['names.en']}</div>}
                    </div>

                    <div>
                        <label className={labelClass}>
                            {t('admin.categories.form.name_ja')}
                            <input
                                type="text"
                                className={fieldClass}
                                value={data.names.ja}
                                onChange={(e) => setData('names', { ...data.names, ja: e.target.value })}
                            />
                        </label>
                        {errors['names.ja'] && <div className={errorClass}>{errors['names.ja']}</div>}
                    </div>

                    <div>
                        <label className={labelClass}>
                            {t('admin.categories.form.name_vi')}
                            <input
                                type="text"
                                className={fieldClass}
                                value={data.names.vi}
                                onChange={(e) => setData('names', { ...data.names, vi: e.target.value })}
                            />
                        </label>
                        {errors['names.vi'] && <div className={errorClass}>{errors['names.vi']}</div>}
                    </div>

                    <div>
                        <label className={labelClass}>
                            {t('common.sort_order')}
                            <input
                                type="number"
                                min={0}
                                className={fieldClass}
                                value={data.sort_order}
                                onChange={(e) => setData('sort_order', Number(e.target.value))}
                            />
                        </label>
                        {errors.sort_order && <div className={errorClass}>{errors.sort_order}</div>}
                    </div>

                    <label className="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input
                            type="checkbox"
                            checked={data.is_active}
                            onChange={(e) => setData('is_active', e.target.checked)}
                        />
                        {t('common.active')}
                    </label>

                    <button type="submit" disabled={processing} className={`${primaryButtonClass} self-start`}>
                        {t('common.save_changes')}
                    </button>
                </form>

                <p className="mt-4">
                    <Link href="/admin/categories" className={linkClass}>
                        {t('nav.back_to_categories')}
                    </Link>
                </p>
            </div>
        </AppLayout>
    );
}
