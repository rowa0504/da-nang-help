import { useForm, Head } from '@inertiajs/react';
import { FormEvent } from 'react';

type LoginForm = {
    email: string;
    password: string;
};

export default function Login() {
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
        <main style={{ fontFamily: 'sans-serif', padding: '2rem', maxWidth: 420 }}>
            <Head title="Log in" />
            <h1>Log in</h1>

            <form onSubmit={submit} style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
                <label>
                    Email
                    <input
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        autoComplete="username"
                    />
                </label>
                {errors.email && <div style={{ color: 'crimson' }}>{errors.email}</div>}

                <label>
                    Password
                    <input
                        type="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        autoComplete="current-password"
                    />
                </label>
                {errors.password && <div style={{ color: 'crimson' }}>{errors.password}</div>}

                <button type="submit" disabled={processing}>
                    Log in
                </button>
            </form>
        </main>
    );
}
