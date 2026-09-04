import { Head, Link, usePage } from '@inertiajs/react';
import { SharedProps } from '@/types';

// NOTE: This role-based content switch is a *display* convenience only —
// it is not a security boundary. Anyone can read/modify client-side JS, so
// it must never be relied on to hide data or actions a role shouldn't have.
// When real Provider/Admin-only features are added (Phase 3+), enforce
// access on the server via Laravel Middleware/Policies, and only use this
// switch to choose which already-authorized UI to render.
export default function Dashboard() {
    const { auth } = usePage<SharedProps>().props;
    const user = auth.user;

    if (!user) {
        // Should be unreachable: this route is behind the `auth` middleware.
        return null;
    }

    return (
        <main style={{ fontFamily: 'sans-serif', padding: '2rem' }}>
            <Head title="Dashboard" />
            <h1>Dashboard</h1>
            <p>
                Signed in as <strong>{user.name}</strong> ({user.email}) — role: <strong>{user.role}</strong>
            </p>

            {user.role === 'customer' && (
                <p>
                    <Link href="/requests/create">Post a new request</Link> · <Link href="/requests">View my requests</Link>
                </p>
            )}

            {user.role === 'provider' && (
                <>
                    <ProviderStatus status={user.provider_verification_status} />
                    {user.provider_verification_status === 'approved' && (
                        <p>
                            <Link href="/provider/requests">Browse the request feed</Link>
                        </p>
                    )}
                </>
            )}

            {user.role === 'admin' && (
                <p>
                    Admin dashboard placeholder — Phase 9 will add moderation tools here. <Link href="/admin/providers">Review pending providers</Link>
                </p>
            )}

            <Link href="/logout" method="post" as="button">
                Log out
            </Link>
        </main>
    );
}

function ProviderStatus({ status }: { status?: string | null }) {
    if (!status) {
        return (
            <p>
                You haven&apos;t set up a provider profile yet. <Link href="/provider/profile">Set up your profile</Link>
            </p>
        );
    }

    switch (status) {
        case 'pending':
            return (
                <p>
                    Your provider profile is under review. <Link href="/provider/profile">View your submission</Link>
                </p>
            );
        case 'approved':
            return (
                <p>
                    Your provider profile is approved. <Link href="/provider/profile">View your profile</Link>
                </p>
            );
        case 'rejected':
            return (
                <p>
                    Your provider profile was rejected. <Link href="/provider/profile">Update and resubmit</Link>
                </p>
            );
        case 'suspended':
            return <p>Your provider account is currently suspended.</p>;
        default:
            return null;
    }
}
