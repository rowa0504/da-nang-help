import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import Show from '@/Pages/Jobs/Show';
import { JobData } from '@/types';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ href, children }: Record<string, unknown> & { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
    usePage: () => ({ url: '/jobs/1', props: { locale: 'en', auth: { user: { role: 'customer' } }, flash: { status: null, warning: null } } }),
    router: { patch: vi.fn(), on: vi.fn(() => () => {}) },
    useForm: (initial: Record<string, unknown>) => ({
        data: initial,
        setData: vi.fn(),
        post: vi.fn(),
        processing: false,
        errors: {},
    }),
}));

function job(overrides: Partial<JobData> = {}): JobData {
    return {
        id: 1,
        agreed_price: '150.00',
        currency: 'USD',
        status: 'assigned',
        provider_completed_at: null,
        customer_confirmed_at: null,
        auto_confirm_at: null,
        completed_at: null,
        cancelled_at: null,
        created_at: '2026-01-01T00:00:00Z',
        service_request: { id: 1, title: 'Fix my sink', address_text: '123 Main St', lat: null, lng: null },
        customer: { name: 'Jane Doe', phone: null },
        provider: { name: 'John Smith', phone: null, business_name: 'Fixit Co.', verification_status: 'approved' },
        review: null,
        ...overrides,
    };
}

describe('Jobs/Show provider verification status badge', () => {
    it('shows the "under re-review" badge when the provider is pending', () => {
        render(<Show job={job({ provider: { name: 'John Smith', phone: null, business_name: 'Fixit Co.', verification_status: 'pending' } })} />);

        expect(screen.getByText('Profile under re-review')).toBeInTheDocument();
    });

    it('does not show the badge when the provider is approved', () => {
        render(<Show job={job()} />);

        expect(screen.queryByText('Profile under re-review')).not.toBeInTheDocument();
    });

    // The badge is Pending-only under the current spec — rejected and
    // suspended providers on an existing job must not show it either.
    it.each([['rejected'], ['suspended']] as const)('does not show the badge when the provider is %s', (status) => {
        render(<Show job={job({ provider: { name: 'John Smith', phone: null, business_name: 'Fixit Co.', verification_status: status } })} />);

        expect(screen.queryByText('Profile under re-review')).not.toBeInTheDocument();
    });
});
