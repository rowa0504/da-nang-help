import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import Profile from '@/Pages/Provider/Profile';
import { ProviderProfileData } from '@/types';

const mockPost = vi.fn();
const mockPatch = vi.fn();

vi.mock('@inertiajs/react', () => ({
    // Static — these tests only check rendering driven by the initial
    // `profile` prop (which category_ids/other_service_details it carries
    // in), not live form interaction — except mockPost/mockPatch below,
    // which are inspected to confirm submit() picks the right HTTP verb.
    useForm: (initial: Record<string, unknown>) => ({
        data: initial,
        setData: vi.fn(),
        post: mockPost,
        patch: mockPatch,
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

afterEach(() => {
    mockPost.mockClear();
    mockPatch.mockClear();
});

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
    // Only Suspended is still read-only — Pending/Rejected/Approved are all
    // editable now (see EditableForm tests below).
    it('shows other service details when present', () => {
        render(
            <Profile
                profile={editableProfile({ verification_status: 'suspended', other_service_details: 'Custom carpentry work' })}
                categories={categories}
                areas={areas}
            />,
        );
        expect(screen.getByText('Custom carpentry work')).toBeInTheDocument();
    });

    it('does not show the other-service-details row when absent', () => {
        render(
            <Profile
                profile={editableProfile({ verification_status: 'suspended', other_service_details: null })}
                categories={categories}
                areas={areas}
            />,
        );
        expect(screen.queryByText('Other service details')).not.toBeInTheDocument();
    });
});

describe('Provider/Profile editing an approved profile', () => {
    it('shows the current status badge', () => {
        render(<Profile profile={editableProfile({ verification_status: 'approved' })} categories={categories} areas={areas} />);
        expect(screen.getByText('Approved')).toBeInTheDocument();
    });

    it('shows the approved-edit notice banner only when the current status is approved', () => {
        const { unmount } = render(<Profile profile={editableProfile({ verification_status: 'approved' })} categories={categories} areas={areas} />);
        expect(screen.getByText(/Editing your profile sends it back for review/)).toBeInTheDocument();
        unmount();

        render(<Profile profile={editableProfile({ verification_status: 'pending' })} categories={categories} areas={areas} />);
        expect(screen.queryByText(/Editing your profile sends it back for review/)).not.toBeInTheDocument();
    });

    it('shows a confirmation dialog before submitting, and only calls patch() after confirming', async () => {
        render(<Profile profile={editableProfile({ verification_status: 'approved' })} categories={categories} areas={areas} />);

        fireEvent.submit(screen.getByRole('button', { name: 'Submit for review' }).closest('form') as HTMLFormElement);

        const confirmBody = await screen.findByText(/Your profile will go back to pending review/);
        expect(confirmBody).toBeInTheDocument();
        expect(mockPatch).not.toHaveBeenCalled();

        fireEvent.click(screen.getByRole('button', { name: 'Confirm' }));
        await waitFor(() => expect(mockPatch).toHaveBeenCalledWith('/provider/profile'));
    });

    it('submits directly via post() for a brand-new profile, with no confirmation dialog', () => {
        render(<Profile profile={null} categories={categories} areas={areas} />);

        fireEvent.submit(screen.getByRole('button', { name: 'Submit for review' }).closest('form') as HTMLFormElement);

        expect(mockPost).toHaveBeenCalledWith('/provider/profile');
        expect(mockPatch).not.toHaveBeenCalled();
    });

    it('submits directly via patch() for a pending edit, with no confirmation dialog', () => {
        render(<Profile profile={editableProfile({ verification_status: 'pending' })} categories={categories} areas={areas} />);

        fireEvent.submit(screen.getByRole('button', { name: 'Submit for review' }).closest('form') as HTMLFormElement);

        expect(mockPatch).toHaveBeenCalledWith('/provider/profile');
    });
});

describe('Provider/Profile inactive category and area retention', () => {
    it('renders an already-attached but now-inactive category as a checked, still-toggleable checkbox', () => {
        render(
            <Profile
                profile={editableProfile({
                    verification_status: 'pending',
                    category_ids: [1, 99],
                    category_names: ['Cleaning', 'Retired Category'],
                })}
                categories={categories}
                areas={areas}
            />,
        );

        expect(screen.getByText('Currently registered (no longer accepting new applications)')).toBeInTheDocument();
        const inactiveCheckbox = screen.getByLabelText('Retired Category') as HTMLInputElement;
        expect(inactiveCheckbox.checked).toBe(true);
    });

    it('does not show the inactive-retained heading when every category and area is currently active', () => {
        render(
            <Profile
                profile={editableProfile({ verification_status: 'pending', category_ids: [1], category_names: ['Cleaning'] })}
                categories={categories}
                areas={areas}
            />,
        );

        expect(screen.queryByText('Currently registered (no longer accepting new applications)')).not.toBeInTheDocument();
    });
});
