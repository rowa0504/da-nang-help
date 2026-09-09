import { useForm, Head } from '@inertiajs/react';
import { FormEvent } from 'react';
import { AppLayout } from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/PageHeader';
import { FormField } from '@/Components/FormField';
import { Input } from '@/Components/Input';
import { Button } from '@/Components/Button';
import { useTranslation } from '@/hooks/useTranslation';

type RegisterForm = {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    phone: string;
    role: 'customer' | 'provider';
};

export default function Register() {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors, reset } = useForm<RegisterForm>({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        phone: '',
        role: 'customer',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/register', {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    }

    return (
        <AppLayout>
            <div className="mx-auto max-w-xl p-4 sm:p-6">
                <Head title={t('auth.register.title')} />
                <PageHeader title={t('auth.register.heading')} />

                <form onSubmit={submit} className="mt-6 flex flex-col gap-4">
                    <fieldset className="flex gap-4 border-0 p-0">
                        <legend className="block text-sm font-medium text-gray-700">{t('auth.register.role_legend')}</legend>
                        <label className="flex items-center gap-2 text-sm text-gray-800">
                            <input
                                type="radio"
                                name="role"
                                value="customer"
                                checked={data.role === 'customer'}
                                onChange={() => setData('role', 'customer')}
                                className="h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                            {t('role.customer')}
                        </label>
                        <label className="flex items-center gap-2 text-sm text-gray-800">
                            <input
                                type="radio"
                                name="role"
                                value="provider"
                                checked={data.role === 'provider'}
                                onChange={() => setData('role', 'provider')}
                                className="h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                            {t('role.provider')}
                        </label>
                    </fieldset>
                    {errors.role && <p className="text-sm text-red-600">{errors.role}</p>}

                    <FormField label={t('auth.name')} htmlFor="name" error={errors.name}>
                        <Input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} autoComplete="name" />
                    </FormField>

                    <FormField label={t('auth.email')} htmlFor="email" error={errors.email}>
                        <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} autoComplete="username" />
                    </FormField>

                    <FormField label={t('auth.phone_optional')} htmlFor="phone" error={errors.phone}>
                        <Input type="tel" value={data.phone} onChange={(e) => setData('phone', e.target.value)} autoComplete="tel" />
                    </FormField>

                    <FormField label={t('auth.password')} htmlFor="password" error={errors.password}>
                        <Input
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            autoComplete="new-password"
                        />
                    </FormField>

                    <FormField label={t('auth.confirm_password')} htmlFor="password_confirmation">
                        <Input
                            type="password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            autoComplete="new-password"
                        />
                    </FormField>

                    <Button type="submit" loading={processing} className="self-start">
                        {t('auth.register.submit')}
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
