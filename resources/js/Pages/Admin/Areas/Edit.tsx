import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { AdminAreaData } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/PageHeader';
import { FormField } from '@/Components/FormField';
import { Input } from '@/Components/Input';
import { Checkbox } from '@/Components/Checkbox';
import { Button } from '@/Components/Button';
import { useTranslation } from '@/hooks/useTranslation';

interface Props {
    area: AdminAreaData;
}

type AreaEditForm = {
    name: string;
    is_active: boolean;
};

export default function Edit({ area }: Props) {
    const { t } = useTranslation();
    const { data, setData, patch, processing, errors } = useForm<AreaEditForm>({
        name: area.name,
        is_active: area.is_active,
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        patch(`/admin/areas/${area.id}`);
    }

    return (
        <AppLayout>
            <div className="mx-auto max-w-3xl p-4 sm:p-6">
                <Head title={t('admin.areas.edit.title')} />
                <PageHeader title={t('admin.areas.edit.title')} />

                <p className="mt-2 text-sm text-gray-600">
                    {t('common.slug')}: <span className="font-mono">{area.slug}</span>
                </p>

                <form onSubmit={submit} className="mt-4 flex flex-col gap-4">
                    <FormField label={t('common.name')} htmlFor="name" error={errors.name}>
                        <Input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                    </FormField>

                    <Checkbox label={t('common.active')} checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />

                    <Button type="submit" loading={processing} className="self-start">
                        {t('common.save_changes')}
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
