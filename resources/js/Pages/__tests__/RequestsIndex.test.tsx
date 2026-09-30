import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import Index from '@/Pages/Requests/Index';
import { PaginatedData, ServiceRequestData } from '@/types';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ href, children }: Record<string, unknown> & { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
    usePage: () => ({
        url: '/requests',
        props: { locale: 'en', auth: { user: null }, flash: { status: null, warning: null } },
    }),
    router: { on: vi.fn(() => () => {}) },
}));

function request(overrides: Partial<ServiceRequestData> = {}): ServiceRequestData {
    return {
        id: 1,
        title: 'Fix my leaking AC',
        description: 'Water is dripping from the unit.',
        title_translation: { status: null, source_locale: 'en', original: 'x' },
        description_translation: { status: null, source_locale: 'en', original: 'x' },
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
        meta: { current_page: 1, from: null, last_page: 1, links: [], path: '/requests', per_page: 20, to: null, total: data.length },
    };
}

describe('Requests/Index moderation badge', () => {
    it('shows a "Hidden" badge for a request the admin has hidden', () => {
        render(<Index requests={paginated([request({ moderation_status: 'hidden' })])} />);

        expect(screen.getByText('Hidden')).toBeInTheDocument();
    });

    it('shows no "Hidden" badge for a visible request', () => {
        render(<Index requests={paginated([request({ moderation_status: 'visible' })])} />);

        expect(screen.queryByText('Hidden')).not.toBeInTheDocument();
    });

    it('shows no "Hidden" badge when moderation_status is absent', () => {
        render(<Index requests={paginated([request()])} />);

        expect(screen.queryByText('Hidden')).not.toBeInTheDocument();
    });
});
