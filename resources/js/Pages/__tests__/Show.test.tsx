import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import Show from '@/Pages/Requests/Show';
import { ServiceRequestData } from '@/types';

vi.mock('@inertiajs/react', () => ({
    useForm: () => ({ patch: vi.fn(), processing: false }),
    Head: () => null,
    usePage: () => ({ props: { locale: 'en', auth: { user: null }, flash: { status: null, warning: null } } }),
    Link: ({ href, children }: Record<string, unknown> & { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
    router: { patch: vi.fn(), on: vi.fn(() => () => {}) },
}));

function baseRequest(overrides: Partial<ServiceRequestData> = {}): ServiceRequestData {
    return {
        id: 1,
        title: 'Fix my leaking AC',
        description: 'Water is dripping from the unit.',
        title_translation: { is_translated: false, source_locale: 'en', original: 'Fix my leaking AC' },
        description_translation: { is_translated: false, source_locale: 'en', original: 'Water is dripping from the unit.' },
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
            />,
        );

        expect(screen.getByText('123 Example Street')).toBeInTheDocument();
        expect(screen.queryByText(/coordinates/i)).not.toBeInTheDocument();
    });

    it('shows nothing private when address_text is absent (feed/unmatched viewer shape)', () => {
        render(<Show request={baseRequest()} myOffer={null} canOffer={false} job={null} />);

        expect(screen.queryByText('123 Example Street')).not.toBeInTheDocument();
        expect(screen.queryByText(/coordinates/i)).not.toBeInTheDocument();
    });
});
