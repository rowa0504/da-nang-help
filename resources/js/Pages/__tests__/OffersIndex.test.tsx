import { describe, expect, it, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import Index from '@/Pages/Requests/Offers/Index';
import { OfferData, PaginatedData } from '@/types';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({ props: { locale: 'en', auth: { user: null }, flash: { status: null, warning: null } } }),
    Link: ({ href, children }: Record<string, unknown> & { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
    router: { patch: vi.fn(), on: vi.fn(() => () => {}) },
}));

function offer(overrides: Partial<OfferData> = {}): OfferData {
    return {
        id: 1,
        price: '100.00',
        currency: 'USD',
        message: 'I can help.',
        message_translation: { status: null, source_locale: 'en', original: 'I can help.' },
        available_at: null,
        status: 'pending',
        created_at: '2026-01-01T00:00:00Z',
        provider: { id: 1, business_name: 'Fixit Co.', avg_rating: '4.50', completed_jobs_count: 3, other_service_details: null },
        ...overrides,
    };
}

function paginated(items: OfferData[]): PaginatedData<OfferData> {
    return {
        data: items,
        links: { first: null, last: null, prev: null, next: null },
        meta: { current_page: 1, from: 1, last_page: 1, links: [], path: '/', per_page: 20, to: items.length, total: items.length },
    };
}

describe('Requests/Offers/Index other_service_details display', () => {
    it('shows the provider\'s other service details when the request category is "other" and a value is present', () => {
        render(
            <Index
                serviceRequest={{ id: 1, title: 'Need something odd', category_slug: 'other' }}
                offers={paginated([
                    offer({
                        provider: { id: 1, business_name: 'Fixit Co.', avg_rating: '4.50', completed_jobs_count: 3, other_service_details: 'Custom furniture repair' },
                    }),
                ])}
            />,
        );
        expect(screen.getByText('Custom furniture repair')).toBeInTheDocument();
    });

    it('hides the row when the request category is not "other", even if a value is present', () => {
        render(
            <Index
                serviceRequest={{ id: 1, title: 'Fix my sink', category_slug: 'plumbing' }}
                offers={paginated([
                    offer({
                        provider: { id: 1, business_name: 'Fixit Co.', avg_rating: '4.50', completed_jobs_count: 3, other_service_details: 'Should not show' },
                    }),
                ])}
            />,
        );
        expect(screen.queryByText('Should not show')).not.toBeInTheDocument();
    });

    it('hides the row when there is no value, even under the "other" category', () => {
        render(<Index serviceRequest={{ id: 1, title: 'Need something odd', category_slug: 'other' }} offers={paginated([offer()])} />);
        expect(screen.queryByText('Other service details:', { exact: false })).not.toBeInTheDocument();
    });
});

describe('Requests/Offers/Index translation toggle independence', () => {
    it('toggles each offer independently — switching one to the original leaves the other showing its translation', () => {
        render(
            <Index
                serviceRequest={{ id: 1, title: 'Need something odd', category_slug: 'other' }}
                offers={paginated([
                    offer({
                        id: 1,
                        message: '[ja] I can help.',
                        message_translation: { status: 'completed', source_locale: 'en', original: 'I can help.' },
                    }),
                    offer({
                        id: 2,
                        message: '[ja] I can also help.',
                        message_translation: { status: 'completed', source_locale: 'en', original: 'I can also help.' },
                    }),
                ])}
            />,
        );

        const toggles = screen.getAllByRole('button', { name: 'Show original' });
        expect(toggles).toHaveLength(2);

        fireEvent.click(toggles[0]);

        expect(screen.getByText('I can help.')).toBeInTheDocument();
        expect(screen.getByText('[ja] I can also help.')).toBeInTheDocument();
        expect(screen.getAllByRole('button', { name: 'Show original' })).toHaveLength(1);
        expect(screen.getAllByRole('button', { name: 'Show translation' })).toHaveLength(1);
    });
});
