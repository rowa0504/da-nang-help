import { Head } from '@inertiajs/react';
import { AppLayout } from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/PageHeader';
import { useTranslation } from '@/hooks/useTranslation';

// AppLayout's header now owns all navigation (role-based nav, login/
// register for guests, logout) — this page just needs its own content.
export default function Home() {
    const { t } = useTranslation();

    return (
        <AppLayout>
            <div className="mx-auto max-w-xl p-4 sm:p-6">
                <Head title={t('app.name')} />
                <PageHeader title={t('app.name')} description={t('home.tagline')} />
            </div>
        </AppLayout>
    );
}
