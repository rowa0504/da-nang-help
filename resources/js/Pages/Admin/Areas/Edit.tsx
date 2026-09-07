import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { AdminAreaData } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { useTranslation } from '@/hooks/useTranslation';

interface Props {
    area: AdminAreaData;
}

type AreaEditForm = {
    name: string;
    is_active: boolean;
};

const fieldClass =
    'mt-1 block w-full max-w-xl rounded border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';
const labelClass = 'block text-sm font-medium text-gray-700';
const errorClass = 'mt-1 text-sm text-red-600';
const primaryButtonClass =
    'rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50';
const linkClass = 'text-blue-600 underline hover:text-blue-800';

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
            <div className="mx-auto max-w-xl p-6 font-sans">
                <Head title={t('admin.areas.edit.title')} />
                <h1 className="mb-4 text-2xl font-semibold text-gray-900">{t('admin.areas.edit.title')}</h1>

                <p className="text-sm text-gray-600">
                    {t('common.slug')}: <span className="font-mono">{area.slug}</span>
                </p>

                <form onSubmit={submit} className="mt-4 flex flex-col gap-4">
                    <div>
                        <label className={labelClass}>
                            {t('common.name')}
                            <input
                                type="text"
                                className={fieldClass}
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                            />
                        </label>
                        {errors.name && <div className={errorClass}>{errors.name}</div>}
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
                    <Link href="/admin/areas" className={linkClass}>
                        {t('nav.back_to_areas')}
                    </Link>
                </p>
            </div>
        </AppLayout>
    );
}
