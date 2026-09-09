import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { AdminCategoryDetail, CategoryOptionWithDepth } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/PageHeader';
import { FormField } from '@/Components/FormField';
import { Input } from '@/Components/Input';
import { Select } from '@/Components/Select';
import { Checkbox } from '@/Components/Checkbox';
import { Button } from '@/Components/Button';
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
            <div className="mx-auto max-w-3xl p-4 sm:p-6">
                <Head title={t('admin.categories.edit.title')} />
                <PageHeader title={t('admin.categories.edit.title')} />

                <p className="mt-2 text-sm text-gray-600">
                    {t('common.slug')}: <span className="font-mono">{category.slug}</span>
                </p>

                <form onSubmit={submit} className="mt-4 flex flex-col gap-4">
                    <FormField label={t('admin.categories.form.parent')} htmlFor="parent_id" error={errors.parent_id}>
                        <Select
                            value={data.parent_id ?? ''}
                            onChange={(e) => setData('parent_id', e.target.value === '' ? null : Number(e.target.value))}
                        >
                            <option value="">{t('admin.categories.form.parent_none')}</option>
                            {categoryOptions.map((option) => (
                                <option key={option.id} value={option.id}>
                                    {'— '.repeat(option.depth) + option.name}
                                </option>
                            ))}
                        </Select>
                    </FormField>

                    <FormField label={t('admin.categories.form.name_en')} htmlFor="name_en" error={errors['names.en']}>
                        <Input type="text" value={data.names.en} onChange={(e) => setData('names', { ...data.names, en: e.target.value })} />
                    </FormField>

                    <FormField label={t('admin.categories.form.name_ja')} htmlFor="name_ja" error={errors['names.ja']}>
                        <Input type="text" value={data.names.ja} onChange={(e) => setData('names', { ...data.names, ja: e.target.value })} />
                    </FormField>

                    <FormField label={t('admin.categories.form.name_vi')} htmlFor="name_vi" error={errors['names.vi']}>
                        <Input type="text" value={data.names.vi} onChange={(e) => setData('names', { ...data.names, vi: e.target.value })} />
                    </FormField>

                    <FormField label={t('common.sort_order')} htmlFor="sort_order" error={errors.sort_order}>
                        <Input type="number" min={0} value={data.sort_order} onChange={(e) => setData('sort_order', Number(e.target.value))} />
                    </FormField>

                    <Checkbox label={t('common.active')} checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />

                    <Button type="submit" loading={processing} className="self-start">
                        {t('common.save_changes')}
                    </Button>
                </form>

                <p className="mt-4">
                    <Link href="/admin/categories" className="text-blue-600 underline hover:text-blue-800">
                        {t('nav.back_to_categories')}
                    </Link>
                </p>
            </div>
        </AppLayout>
    );
}
