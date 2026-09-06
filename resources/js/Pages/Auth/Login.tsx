import { useForm, Head } from '@inertiajs/react';
import { FormEvent } from 'react';
import { AppLayout } from '@/Layouts/AppLayout';
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
            <div style={{ fontFamily: 'sans-serif', padding: '2rem', maxWidth: 420 }}>
                <Head title={t('auth.login.title')} />
                <h1>{t('auth.login.heading')}</h1>

                <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
                    <label>
                        {t('auth.email')}
                        <input
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            autoComplete="username"
                        />
                    </label>
                    {errors.email && <div style={{ color: 'crimson' }}>{errors.email}</div>}

                    <label>
                        {t('auth.password')}
                        <input
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            autoComplete="current-password"
                        />
                    </label>
                    {errors.password && <div style={{ color: 'crimson' }}>{errors.password}</div>}

                    <button type="submit" disabled={processing}>
                        {t('auth.login.submit')}
                    </button>
                </form>
            </div>
        </AppLayout>
    );
}
