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

            {user.role === 'customer' && <p>Customer dashboard placeholder — Phase 4+ will add request posting here.</p>}
            {user.role === 'provider' && <p>Provider dashboard placeholder — Phase 3+ will add profile/category/area setup here.</p>}
            {user.role === 'admin' && <p>Admin dashboard placeholder — Phase 9 will add moderation tools here.</p>}

            <Link href="/logout" method="post" as="button">
                Log out
            </Link>
        </main>
    );
}
