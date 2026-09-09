import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { AppLayout } from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/PageHeader';
import { FormField } from '@/Components/FormField';
import { Input } from '@/Components/Input';
import { Checkbox } from '@/Components/Checkbox';
import { Button } from '@/Components/Button';
import { useTranslation } from '@/hooks/useTranslation';

type AreaCreateForm = {
    name: string;
    slug: string;
    is_active: boolean;
};

export default function Create() {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm<AreaCreateForm>({
        name: '',
        slug: '',
        is_active: true,
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/admin/areas');
    }

    return (
        <AppLayout>
            <div className="mx-auto max-w-3xl p-4 sm:p-6">
                <Head title={t('admin.areas.create.title')} />
                <PageHeader title={t('admin.areas.create.title')} />

                <form onSubmit={submit} className="mt-6 flex flex-col gap-4">
                    <FormField label={t('common.name')} htmlFor="name" error={errors.name}>
                        <Input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                    </FormField>

                    <FormField label={t('common.slug')} htmlFor="slug" error={errors.slug}>
                        <Input type="text" value={data.slug} onChange={(e) => setData('slug', e.target.value)} />
                    </FormField>

                    <Checkbox label={t('common.active')} checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />

                    <Button type="submit" loading={processing} className="self-start">
                        {t('admin.areas.form.submit_create')}
                    </Button>
                </form>

                <p className="mt-4">
                    <Link href="/admin/areas" className="text-blue-600 underline hover:text-blue-800">
                        {t('nav.back_to_areas')}
                    </Link>
                </p>
            </div>
        </AppLayout>
    );
}
