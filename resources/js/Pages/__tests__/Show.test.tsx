import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import Show from '@/Pages/Requests/Show';
import { ServiceRequestData } from '@/types';

const mockUsePage = vi.fn();

vi.mock('@inertiajs/react', () => ({
    // Covers both call sites: Show's own top-level useForm() (cancel
    // button, only needs patch/processing) and OfferForm's
    // useForm<OfferForm>({...}) (needs the full shape) — one mock shape
    // that's a superset of both, extra fields are simply unused.
    useForm: (initial: Record<string, unknown> = {}) => ({
        data: initial,
        setData: vi.fn(),
        post: vi.fn(),
        patch: vi.fn(),
        transform: vi.fn(),
        processing: false,
        errors: {},
    }),
    Head: () => null,
    usePage: () => mockUsePage(),
    Link: ({ href, children }: Record<string, unknown> & { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
    router: { patch: vi.fn(), on: vi.fn(() => () => {}) },
}));

beforeEach(() => {
    mockUsePage.mockReturnValue({
        url: '/requests/1',
        props: { locale: 'en', auth: { user: null }, flash: { status: null, warning: null } },
    });
});

function asProviderViewer() {
    mockUsePage.mockReturnValue({
        url: '/requests/1',
        props: {
            locale: 'en',
            auth: { user: { id: 1, name: 'P', email: 'p@example.test', role: 'provider', locale: 'en' } },
            flash: { status: null, warning: null },
        },
    });
}

function baseRequest(overrides: Partial<ServiceRequestData> = {}): ServiceRequestData {
    return {
        id: 1,
        title: 'Fix my leaking AC',
        description: 'Water is dripping from the unit.',
        title_translation: { status: null, source_locale: 'en', original: 'Fix my leaking AC' },
        description_translation: { status: null, source_locale: 'en', original: 'Water is dripping from the unit.' },
        category: { id: 1, name: 'Air-con Repair' },
        area: { id: 1, name: 'Hai Chau' },
        urgency: 'normal',
        status: 'open',
        created_at: '2026-01-01T00:00:00Z',
        photos: [],
        ...overrides,
    };
}

describe('Requests/Show private info block', () => {
    it('shows the address but no raw coordinates label when private fields are present', () => {
        render(
            <Show
                request={baseRequest({ address_text: '123 Example Street', lat: null, lng: null })}
                myOffer={null}
                canOffer={false}
                job={null}
                can_edit={false}
            />,
        );

        expect(screen.getByText('123 Example Street')).toBeInTheDocument();
        expect(screen.queryByText(/coordinates/i)).not.toBeInTheDocument();
    });

    it('shows nothing private when address_text is absent (feed/unmatched viewer shape)', () => {
        render(<Show request={baseRequest()} myOffer={null} canOffer={false} job={null}
                can_edit={false} />);

        expect(screen.queryByText('123 Example Street')).not.toBeInTheDocument();
        expect(screen.queryByText(/coordinates/i)).not.toBeInTheDocument();
    });
});

describe('Requests/Show offer price field (VND-only)', () => {
    it('renders no currency field at all, and a text/numeric MoneyInput for price', () => {
        asProviderViewer();
        render(<Show request={baseRequest()} myOffer={null} canOffer={true} job={null} can_edit={false} />);

        expect(screen.queryByLabelText(/currency/i)).not.toBeInTheDocument();
        const priceField = screen.getByLabelText('Price (VND)');
        expect(priceField).toHaveAttribute('type', 'text');
        expect(priceField).toHaveAttribute('inputMode', 'numeric');
    });

    it('pre-fills the price field with the integer part only when editing an existing pending offer (no decimal point)', () => {
        asProviderViewer();
        const pendingOffer = {
            id: 5,
            price: '450000.00',
            currency: 'VND',
            message: 'x',
            message_translation: { status: null, source_locale: 'en', original: 'x' },
            available_at: null,
            status: 'pending' as const,
            created_at: '2026-01-01T00:00:00Z',
            provider: { id: 1, business_name: 'Fixit Co.', avg_rating: '0.00', completed_jobs_count: 0, verification_status: 'approved' as const, other_service_details: null },
            original_message: 'x',
            source_locale: 'en' as const,
        };
        render(<Show request={baseRequest()} myOffer={pendingOffer} canOffer={false} job={null} can_edit={false} />);

        expect(screen.getByLabelText('Price (VND)')).toHaveValue('450,000');
    });

    it('displays a confirmed/accepted offer\'s amount through formatCurrency (grouped, with VND suffix), not a raw concatenation', () => {
        asProviderViewer();
        const acceptedOffer = {
            id: 6,
            price: '450000.00',
            currency: 'VND',
            message: 'x',
            message_translation: { status: null, source_locale: 'en', original: 'x' },
            available_at: null,
            status: 'accepted' as const,
            created_at: '2026-01-01T00:00:00Z',
            provider: { id: 1, business_name: 'Fixit Co.', avg_rating: '0.00', completed_jobs_count: 0, verification_status: 'approved' as const, other_service_details: null },
        };
        render(<Show request={baseRequest()} myOffer={acceptedOffer} canOffer={false} job={null} can_edit={false} />);

        expect(screen.getByText(/450,000 VND/)).toBeInTheDocument();
    });
});

describe('Requests/Show moderation notice', () => {
    it('shows a warning notice when the request has been hidden by an admin', () => {
        render(<Show request={baseRequest({ moderation_status: 'hidden' })} myOffer={null} canOffer={false} job={null}
                can_edit={false} />);

        expect(
            screen.getByText(
                'This request has been hidden following an administrator review. It is not shown in providers’ request feeds and cannot receive new offers.',
            ),
        ).toBeInTheDocument();
    });

    it('shows no notice when the request is visible', () => {
        render(<Show request={baseRequest({ moderation_status: 'visible' })} myOffer={null} canOffer={false} job={null}
                can_edit={false} />);

        expect(screen.queryByText(/administrator review/)).not.toBeInTheDocument();
    });

    it('shows no notice when moderation_status is absent (e.g. a non-owner shape)', () => {
        render(<Show request={baseRequest()} myOffer={null} canOffer={false} job={null} can_edit={false} />);

        expect(screen.queryByText(/administrator review/)).not.toBeInTheDocument();
    });
});

describe('Requests/Show edit link', () => {
    it('shows the edit link when can_edit is true', () => {
        render(<Show request={baseRequest()} myOffer={null} canOffer={false} job={null} can_edit={true} />);

        expect(screen.getByRole('link', { name: 'Edit request' })).toHaveAttribute('href', '/requests/1/edit');
    });

    it('hides the edit link when can_edit is false', () => {
        render(<Show request={baseRequest()} myOffer={null} canOffer={false} job={null} can_edit={false} />);

        expect(screen.queryByRole('link', { name: 'Edit request' })).not.toBeInTheDocument();
    });
});

describe('Requests/Show match-level warning before sending an offer', () => {
    it('shows the mismatch warning for a partial match, without disabling the submit button', () => {
        asProviderViewer();
        render(<Show request={baseRequest({ match_level: 'partial' })} myOffer={null} canOffer={true} job={null}
                can_edit={false} />);

        expect(screen.getByText('This request does not fully match your registered categories/areas.')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Send offer' })).not.toBeDisabled();
    });

    it('shows the mismatch warning for no match at all', () => {
        asProviderViewer();
        render(<Show request={baseRequest({ match_level: 'none' })} myOffer={null} canOffer={true} job={null}
                can_edit={false} />);

        expect(screen.getByText('This request does not fully match your registered categories/areas.')).toBeInTheDocument();
    });

    it('does not show a warning for a full match', () => {
        asProviderViewer();
        render(<Show request={baseRequest({ match_level: 'full' })} myOffer={null} canOffer={true} job={null}
                can_edit={false} />);

        expect(screen.queryByText('This request does not fully match your registered categories/areas.')).not.toBeInTheDocument();
    });
});

describe('Requests/Show title/description translation toggle', () => {
    function completedRequest(): ServiceRequestData {
        return baseRequest({
            title: '[ja] Fix my leaking AC',
            description: '[ja] Water is dripping from the unit.',
            title_translation: { status: 'completed', source_locale: 'en', original: 'Fix my leaking AC' },
            description_translation: { status: 'completed', source_locale: 'en', original: 'Water is dripping from the unit.' },
        });
    }

    it('shows the translation by default with the machine-translated badge', () => {
        render(<Show request={completedRequest()} myOffer={null} canOffer={false} job={null}
                can_edit={false} />);

        expect(screen.getByText('[ja] Fix my leaking AC')).toBeInTheDocument();
        expect(screen.getByText('[ja] Water is dripping from the unit.')).toBeInTheDocument();
        expect(screen.getByText('(machine-translated)')).toBeInTheDocument();
    });

    it('toggles the title and description together via a single button, and hides the badge while showing the original', () => {
        render(<Show request={completedRequest()} myOffer={null} canOffer={false} job={null}
                can_edit={false} />);

        const toggle = screen.getByRole('button', { name: 'Show original' });
        expect(screen.getAllByRole('button', { name: /Show original|Show translation/ })).toHaveLength(1);

        fireEvent.click(toggle);

        expect(screen.getByText('Fix my leaking AC')).toBeInTheDocument();
        expect(screen.getByText('Water is dripping from the unit.')).toBeInTheDocument();
        expect(screen.queryByText('(machine-translated)')).not.toBeInTheDocument();
        expect(screen.getByText('Original · English')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Show translation' })).toHaveAttribute('aria-pressed', 'true');
    });

    it('shows "pending" with no toggle button while the translation is not ready', () => {
        render(
            <Show
                request={baseRequest({
                    title_translation: { status: 'pending', source_locale: 'en', original: 'Fix my leaking AC' },
                    description_translation: { status: 'pending', source_locale: 'en', original: 'Water is dripping from the unit.' },
                })}
                myOffer={null}
                canOffer={false}
                job={null}
                can_edit={false}
            />,
        );

        expect(screen.getByText('Translation in progress')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Show original|Show translation/ })).not.toBeInTheDocument();
    });

    it('shows "unavailable" with no toggle button when translation failed', () => {
        render(
            <Show
                request={baseRequest({
                    title_translation: { status: 'failed', source_locale: 'en', original: 'Fix my leaking AC' },
                    description_translation: { status: 'failed', source_locale: 'en', original: 'Water is dripping from the unit.' },
                })}
                myOffer={null}
                canOffer={false}
                job={null}
                can_edit={false}
            />,
        );

        expect(screen.getByText('Translation unavailable')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Show original|Show translation/ })).not.toBeInTheDocument();
    });

    it('shows no badge, status text, or toggle when the viewer locale matches the source locale', () => {
        render(<Show request={baseRequest()} myOffer={null} canOffer={false} job={null}
                can_edit={false} />);

        expect(screen.queryByText('(machine-translated)')).not.toBeInTheDocument();
        expect(screen.queryByText('Translation in progress')).not.toBeInTheDocument();
        expect(screen.queryByText('Translation unavailable')).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Show original|Show translation/ })).not.toBeInTheDocument();
    });
});
