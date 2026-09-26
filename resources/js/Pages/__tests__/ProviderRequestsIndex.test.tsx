import { describe, expect, it, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import Index from '@/Pages/Provider/Requests/Index';
import { PaginatedData, ServiceRequestData } from '@/types';

const routerGet = vi.fn();

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ href, children }: Record<string, unknown> & { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
    usePage: () => ({
        url: '/provider/requests',
        props: { locale: 'en', auth: { user: null }, flash: { status: null, warning: null } },
    }),
    router: { get: (...args: unknown[]) => routerGet(...args), on: vi.fn(() => () => {}) },
}));

function request(overrides: Partial<ServiceRequestData> = {}): ServiceRequestData {
    return {
        id: 1,
        title: 'Fix my leaking AC',
        description: 'Water is dripping from the unit.',
        title_translation: { is_translated: false, source_locale: 'en', original: 'x' },
        description_translation: { is_translated: false, source_locale: 'en', original: 'x' },
        category: { id: 1, name: 'Air-con Repair' },
        area: { id: 1, name: 'Hai Chau' },
        urgency: 'normal',
        status: 'open',
        created_at: '2026-01-01T00:00:00Z',
        photos: [],
        ...overrides,
    };
}

function paginated(data: ServiceRequestData[]): PaginatedData<ServiceRequestData> {
    return {
        data,
        links: { first: null, last: null, prev: null, next: null },
        meta: { current_page: 1, from: null, last_page: 1, links: [], path: '/provider/requests', per_page: 20, to: null, total: data.length },
    };
}

const noFilters = { recommended: false, category_id: null, area_id: null };

describe('Provider/Requests/Index', () => {
    it('shows a "Recommended" badge for a full match, "Partial match" for partial, and none for no match', () => {
        render(
            <Index
                requests={paginated([
                    request({ id: 1, match_level: 'full' }),
                    request({ id: 2, match_level: 'partial' }),
                    request({ id: 3, match_level: 'none' }),
                ])}
                filters={noFilters}
                categoryOptions={[]}
                areaOptions={[]}
            />,
        );

        expect(screen.getByText('Recommended')).toBeInTheDocument();
        expect(screen.getByText('Partial match')).toBeInTheDocument();
        // "none" renders no badge at all — exactly the two above, no third.
        expect(screen.queryAllByText(/^(Recommended|Partial match)$/)).toHaveLength(2);
    });

    it('triggers router.get with recommended=1 when the toggle is checked', () => {
        render(<Index requests={paginated([request()])} filters={noFilters} categoryOptions={[]} areaOptions={[]} />);

        fireEvent.click(screen.getByLabelText('Recommended only'));

        expect(routerGet).toHaveBeenCalledWith(
            '/provider/requests',
            expect.objectContaining({ recommended: '1' }),
            expect.objectContaining({ preserveState: true, preserveScroll: true }),
        );
    });

    it('shows the empty state when there are no requests', () => {
        render(<Index requests={paginated([])} filters={noFilters} categoryOptions={[]} areaOptions={[]} />);

        expect(screen.getByText('No open requests right now.')).toBeInTheDocument();
    });
});
