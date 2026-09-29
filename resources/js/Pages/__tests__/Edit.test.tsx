import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import Edit from '@/Pages/Requests/Edit';
import { AreaOption, CategoryOption, ServiceRequestEditData } from '@/types';

const mockSetData = vi.fn();

vi.mock('@inertiajs/react', () => ({
    // Static — these tests only check rendering driven by the initial
    // props, not live form interaction (same convention as Profile.test.tsx)
    // — except mockSetData, inspected by the photo-selection regression
    // test below, since that bug could only be observed through what
    // setData() was actually called with.
    useForm: (initial: Record<string, unknown>) => ({
        data: initial,
        setData: mockSetData,
        post: vi.fn(),
        transform: vi.fn(),
        processing: false,
        errors: {},
    }),
    Head: () => null,
    usePage: () => ({ props: { locale: 'en', auth: { user: null }, flash: { status: null, warning: null } } }),
    Link: ({ href, children }: Record<string, unknown> & { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
    router: { patch: vi.fn(), on: vi.fn(() => () => {}) },
}));

afterEach(() => {
    mockSetData.mockClear();
});

const categories: CategoryOption[] = [
    { id: 1, slug: 'cleaning', name: 'Cleaning' },
    { id: 2, slug: 'other', name: 'Other' },
];
const areas: AreaOption[] = [{ id: 1, slug: 'hai-chau', name: 'Hai Chau' }];

function baseRequest(overrides: Partial<ServiceRequestEditData> = {}): ServiceRequestEditData {
    return {
        id: 1,
        title: 'Fix my leaking AC',
        description: 'Water is dripping from the unit.',
        category_id: 1,
        area_id: 1,
        address_text: '123 Example Street',
        urgency: 'normal',
        source_locale: 'en',
        photos: [],
        ...overrides,
    };
}

describe('Requests/Edit "other" category title label', () => {
    it('uses the "other" title label when the current category is already "other"', () => {
        render(<Edit serviceRequest={baseRequest({ category_id: 2 })} categories={categories} areas={areas} />);
        expect(screen.getByLabelText('Service needed')).toBeInTheDocument();
    });

    it('uses the default title label for a normal category', () => {
        render(<Edit serviceRequest={baseRequest({ category_id: 1 })} categories={categories} areas={areas} />);
        expect(screen.getByLabelText('Title')).toBeInTheDocument();
    });
});

describe('Requests/Edit existing photos', () => {
    it('shows each existing photo with a "Keep" checkbox, unchecked by default', () => {
        render(
            <Edit
                serviceRequest={baseRequest({ photos: [{ id: 10, url: 'https://example.test/a.jpg' }] })}
                categories={categories}
                areas={areas}
            />,
        );

        const checkbox = screen.getByRole('checkbox', { name: 'Keep' }) as HTMLInputElement;
        expect(checkbox.checked).toBe(false);
    });

    it('disables the add-photos input once 5 photos are already present', () => {
        const photos = Array.from({ length: 5 }, (_, i) => ({ id: i + 1, url: `https://example.test/${i}.jpg` }));
        render(<Edit serviceRequest={baseRequest({ photos })} categories={categories} areas={areas} />);

        expect(screen.getByLabelText('Add photos')).toBeDisabled();
    });

    it('passes every selected file to setData, not just however many currently "fit" — the server enforces the real cap', () => {
        // Regression test: onPhotosChange used to slice(0, remainingSlots),
        // which silently dropped files the user had just picked whenever a
        // removal was already checked, with zero visible feedback. It must
        // now hand off everything the user selected (up to the flat
        // MAX_PHOTOS ceiling) and let the server's cross-field validation
        // be the actual, visible rejection.
        const photos = [
            { id: 1, url: 'https://example.test/0.jpg' },
            { id: 2, url: 'https://example.test/1.jpg' },
            { id: 3, url: 'https://example.test/2.jpg' },
        ];
        render(<Edit serviceRequest={baseRequest({ photos })} categories={categories} areas={areas} />);

        // Mark one of the three existing photos for removal — leaving only
        // 1 apparent "slot" — then select 2 new files anyway.
        fireEvent.click(screen.getAllByRole('checkbox', { name: 'Keep' })[0]);

        const file1 = new File(['a'], 'a.jpg', { type: 'image/jpeg' });
        const file2 = new File(['b'], 'b.jpg', { type: 'image/jpeg' });
        const input = screen.getByLabelText('Add photos') as HTMLInputElement;
        fireEvent.change(input, { target: { files: [file1, file2] } });

        const photosCall = mockSetData.mock.calls.find((call) => call[0] === 'photos');
        expect(photosCall?.[1]).toHaveLength(2);
    });

    it('leaves the add-photos input enabled when there is room for more', () => {
        const photos = Array.from({ length: 3 }, (_, i) => ({ id: i + 1, url: `https://example.test/${i}.jpg` }));
        render(<Edit serviceRequest={baseRequest({ photos })} categories={categories} areas={areas} />);

        expect(screen.getByLabelText('Add photos')).not.toBeDisabled();
    });
});

describe('Requests/Edit original language display', () => {
    it('shows the source_locale as a read-only label, not an editable field', () => {
        render(<Edit serviceRequest={baseRequest({ source_locale: 'ja' })} categories={categories} areas={areas} />);

        const label = screen.getByText('Original language');
        expect(label).toBeInTheDocument();
        // Scoped to the label's own container: AppLayout's LocaleSwitcher
        // also renders a "Japanese" <option>, so an unscoped query would
        // match both.
        expect(label.parentElement).toHaveTextContent('Japanese');
        // No <select>/<input> named after the source locale exists — only
        // category_id/area_id/urgency use interactive controls here.
        expect(screen.queryByLabelText('Original language')).not.toBeInTheDocument();
    });
});

describe('Requests/Edit inactive category/area option suffix', () => {
    it('appends the "no longer accepting" suffix to an is_inactive option, translated client-side', () => {
        // Regression test: the suffix used to be baked into the option
        // text server-side via Laravel's own __(), which only has the
        // *backend* validation.php dictionary — a key that exists only in
        // the frontend dictionary (resources/js/lang/*.ts) resolved to the
        // literal, untranslated key string. The controller now sends a
        // plain is_inactive flag and the frontend applies its own,
        // already-correct useTranslation() string.
        const inactiveCategories: CategoryOption[] = [
            ...categories,
            { id: 99, slug: 'retired', name: 'Retired Category', is_inactive: true },
        ];
        render(<Edit serviceRequest={baseRequest({ category_id: 99 })} categories={inactiveCategories} areas={areas} />);

        expect(screen.getByRole('option', { name: 'Retired Category (no longer accepting new requests)' })).toBeInTheDocument();
    });

    it('does not append the suffix to a normal, active option', () => {
        render(<Edit serviceRequest={baseRequest()} categories={categories} areas={areas} />);

        expect(screen.getByRole('option', { name: 'Cleaning' })).toBeInTheDocument();
    });
});
