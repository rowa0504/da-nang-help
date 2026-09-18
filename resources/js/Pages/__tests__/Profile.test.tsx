import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import Profile from '@/Pages/Provider/Profile';
import { ProviderProfileData } from '@/types';

vi.mock('@inertiajs/react', () => ({
    // Static — these tests only check rendering driven by the initial
    // `profile` prop (which category_ids/other_service_details it carries
    // in), not live form interaction.
    useForm: (initial: Record<string, unknown>) => ({
        data: initial,
        setData: vi.fn(),
        post: vi.fn(),
        processing: false,
        errors: {},
    }),
    Head: () => null,
    usePage: () => ({ props: { locale: 'en', auth: { user: null }, flash: { status: null, warning: null } } }),
    Link: ({ href, children }: Record<string, unknown> & { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
    router: { patch: vi.fn(), on: vi.fn(() => () => {}) },
}));

const categories = [
    { id: 1, slug: 'cleaning', name: 'Cleaning' },
    { id: 2, slug: 'other', name: 'Other' },
];
const areas = [{ id: 1, slug: 'hai-chau', name: 'Hai Chau' }];

function editableProfile(overrides: Partial<ProviderProfileData> = {}): ProviderProfileData {
    return {
        business_name: 'Da Nang Fixit Co.',
        bio: null,
        verification_status: 'rejected',
        category_ids: [],
        area_ids: [],
        category_names: [],
        area_names: [],
        other_service_details: null,
        avg_rating: '0.00',
        completed_jobs_count: 0,
        ...overrides,
    };
}

describe('Provider/Profile edit form', () => {
    it('shows the other-service-details field when the "other" category is already selected', () => {
        render(<Profile profile={editableProfile({ category_ids: [2] })} categories={categories} areas={areas} />);
        expect(screen.getByLabelText('Other service details')).toBeInTheDocument();
    });

    it('hides the other-service-details field when "other" is not selected', () => {
        render(<Profile profile={editableProfile({ category_ids: [1] })} categories={categories} areas={areas} />);
        expect(screen.queryByLabelText('Other service details')).not.toBeInTheDocument();
    });
});

describe('Provider/Profile read-only summary', () => {
    it('shows other service details when present', () => {
        render(
            <Profile
                profile={editableProfile({ verification_status: 'approved', other_service_details: 'Custom carpentry work' })}
                categories={categories}
                areas={areas}
            />,
        );
        expect(screen.getByText('Custom carpentry work')).toBeInTheDocument();
    });

    it('does not show the other-service-details row when absent', () => {
        render(
            <Profile
                profile={editableProfile({ verification_status: 'approved', other_service_details: null })}
                categories={categories}
                areas={areas}
            />,
        );
        expect(screen.queryByText('Other service details')).not.toBeInTheDocument();
    });
});
