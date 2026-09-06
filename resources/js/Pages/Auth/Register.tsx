import { useForm, Head } from '@inertiajs/react';
import { FormEvent } from 'react';
import { AppLayout } from '@/Layouts/AppLayout';
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
            <div style={{ fontFamily: 'sans-serif', padding: '2rem', maxWidth: 420 }}>
                <Head title={t('auth.register.title')} />
                <h1>{t('auth.register.heading')}</h1>

                <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
                    <fieldset style={{ border: 0, padding: 0, display: 'flex', gap: '1rem' }}>
                        <legend>{t('auth.register.role_legend')}</legend>
                        <label>
                            <input
                                type="radio"
                                name="role"
                                value="customer"
                                checked={data.role === 'customer'}
                                onChange={() => setData('role', 'customer')}
                            />{' '}
                            {t('role.customer')}
                        </label>
                        <label>
                            <input
                                type="radio"
                                name="role"
                                value="provider"
                                checked={data.role === 'provider'}
                                onChange={() => setData('role', 'provider')}
                            />{' '}
                            {t('role.provider')}
                        </label>
                    </fieldset>
                    {errors.role && <div style={{ color: 'crimson' }}>{errors.role}</div>}

                    <label>
                        {t('auth.name')}
                        <input
                            type="text"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            autoComplete="name"
                        />
                    </label>
                    {errors.name && <div style={{ color: 'crimson' }}>{errors.name}</div>}

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
                        {t('auth.phone_optional')}
                        <input
                            type="tel"
                            value={data.phone}
                            onChange={(e) => setData('phone', e.target.value)}
                            autoComplete="tel"
                        />
                    </label>
                    {errors.phone && <div style={{ color: 'crimson' }}>{errors.phone}</div>}

                    <label>
                        {t('auth.password')}
                        <input
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            autoComplete="new-password"
                        />
                    </label>
                    {errors.password && <div style={{ color: 'crimson' }}>{errors.password}</div>}

                    <label>
                        {t('auth.confirm_password')}
                        <input
                            type="password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            autoComplete="new-password"
                        />
                    </label>

                    <button type="submit" disabled={processing}>
                        {t('auth.register.submit')}
                    </button>
                </form>
            </div>
        </AppLayout>
    );
}
