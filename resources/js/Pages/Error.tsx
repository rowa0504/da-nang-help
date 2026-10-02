import { Head } from '@inertiajs/react';
import { AppLayout } from '@/Layouts/AppLayout';
import { PageHeader } from '@/Components/PageHeader';
import { Alert } from '@/Components/Alert';

interface Props {
    status: number;
    // Already localized server-side (see bootstrap/app.php's exceptions->
    // respond() — this mirrors how flash.warning is always a pre-rendered
    // string, never a translation key resolved client-side).
    message: string;
    retryAfter: number | null;
}

// A generic Inertia-rendered error page. Currently only reached for 429
// (rate-limited) responses to an Inertia request — see bootstrap/app.php —
// but the shape is intentionally generic enough to extend to other status
// codes later without a new component.
export default function Error({ status, message }: Props) {
    return (
        <AppLayout>
            <div className="mx-auto max-w-xl p-4 sm:p-6">
                <Head title={String(status)} />
                <PageHeader title={status} />
                <Alert variant="warning">{message}</Alert>
            </div>
        </AppLayout>
    );
}
