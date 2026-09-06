import { Head, Link, usePage } from '@inertiajs/react';
import { SharedProps } from '@/types';
import { AppLayout } from '@/Layouts/AppLayout';
import { useTranslation } from '@/hooks/useTranslation';

export default function Home() {
    const { auth } = usePage<SharedProps>().props;
    const { t } = useTranslation();

    return (
        <AppLayout>
            <div style={{ fontFamily: 'sans-serif', padding: '2rem' }}>
                <Head title={t('app.name')} />
                <h1>{t('app.name')}</h1>
                <p>{t('home.tagline')}</p>

                <nav style={{ display: 'flex', gap: '1rem' }}>
                    {auth.user ? (
                        <>
                            <Link href="/dashboard">{t('nav.dashboard')}</Link>
                            <Link href="/logout" method="post" as="button">
                                {t('nav.logout')}
                            </Link>
                        </>
                    ) : (
                        <>
                            <Link href="/login">{t('nav.login')}</Link>
                            <Link href="/register">{t('nav.register')}</Link>
                        </>
                    )}
                </nav>
            </div>
        </AppLayout>
    );
}
