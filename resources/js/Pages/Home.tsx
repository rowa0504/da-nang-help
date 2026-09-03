import { Head, Link, usePage } from '@inertiajs/react';
import { SharedProps } from '@/types';

export default function Home() {
    const { auth } = usePage<SharedProps>().props;

    return (
        <main style={{ fontFamily: 'sans-serif', padding: '2rem' }}>
            <Head title="Da Nang Help" />
            <h1>Da Nang Help</h1>
            <p>Phase 1 environment OK — Laravel + Inertia.js + React + TypeScript</p>

            <nav style={{ display: 'flex', gap: '1rem' }}>
                {auth.user ? (
                    <>
                        <Link href="/dashboard">Dashboard</Link>
                        <Link href="/logout" method="post" as="button">
                            Log out
                        </Link>
                    </>
                ) : (
                    <>
                        <Link href="/login">Log in</Link>
                        <Link href="/register">Register</Link>
                    </>
                )}
            </nav>
        </main>
    );
}
