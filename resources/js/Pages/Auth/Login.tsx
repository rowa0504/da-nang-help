import { useForm, Head } from '@inertiajs/react';
import { FormEvent } from 'react';
import { AppLayout } from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/PageHeader';
import { FormField } from '@/Components/FormField';
import { Input } from '@/Components/Input';
import { Button } from '@/Components/Button';
import { useTranslation } from '@/hooks/useTranslation';

type LoginForm = {
    email: string;
    password: string;
};

export default function Login() {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors, reset } = useForm<LoginForm>({
        email: '',
        password: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/login', {
            onFinish: () => reset('password'),
        });
    }

    return (
        <AppLayout>
            <div className="mx-auto max-w-xl p-4 sm:p-6">
                <Head title={t('auth.login.title')} />
                <PageHeader title={t('auth.login.heading')} />

                <form onSubmit={submit} className="mt-6 flex flex-col gap-4">
                    <FormField label={t('auth.email')} htmlFor="email" error={errors.email}>
                        <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} autoComplete="username" />
                    </FormField>

                    <FormField label={t('auth.password')} htmlFor="password" error={errors.password}>
                        <Input
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            autoComplete="current-password"
                        />
                    </FormField>

                    <Button type="submit" loading={processing} className="self-start">
                        {t('auth.login.submit')}
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
