import { describe, expect, it, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { useState } from 'react';
import Create from '@/Pages/Requests/Create';

vi.mock('@inertiajs/react', () => ({
    // A stateful mock (unlike the static echo-back mock used for
    // Register.test.tsx) — this page hardcodes category_id: '' as its own
    // initial value, so exercising the "other" category branch requires a
    // real setData()-driven re-render, not just a different initial prop.
    useForm: (initial: Record<string, unknown>) => {
        const [data, setDataState] = useState(initial);
        const setData = (key: string, value: unknown) => setDataState((prev) => ({ ...prev, [key]: value }));
        return { data, setData, post: vi.fn(), processing: false, errors: {} };
    },
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

describe('Requests/Create', () => {
    it('shows the default title label before any category is selected', () => {
        render(<Create categories={categories} areas={areas} />);
        expect(screen.getByLabelText('Title')).toBeInTheDocument();
    });

    it('switches the title label and placeholder when the "other" category is selected', () => {
        render(<Create categories={categories} areas={areas} />);
        fireEvent.change(screen.getByLabelText('Category'), { target: { value: '2' } });

        expect(screen.getByLabelText('Service needed')).toBeInTheDocument();
        expect(screen.getByPlaceholderText('Describe the specific service you need')).toBeInTheDocument();
    });

    it('reverts to the default title label when a non-"other" category is selected', () => {
        render(<Create categories={categories} areas={areas} />);
        fireEvent.change(screen.getByLabelText('Category'), { target: { value: '2' } });
        fireEvent.change(screen.getByLabelText('Category'), { target: { value: '1' } });

        expect(screen.getByLabelText('Title')).toBeInTheDocument();
    });

    it('has an address field but no latitude/longitude inputs', () => {
        render(<Create categories={categories} areas={areas} />);

        expect(screen.getByLabelText('Address')).toBeInTheDocument();
        expect(screen.queryByLabelText(/latitude/i)).not.toBeInTheDocument();
        expect(screen.queryByLabelText(/longitude/i)).not.toBeInTheDocument();
    });
});
