import { describe, expect, it, vi, afterEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import Register from '@/Pages/Auth/Register';

let mockLocale = 'en';

vi.mock('@inertiajs/react', () => ({
    // Static — this test only checks the initial `data.role` selection
    // driven by resolveInitialRole(window.location.search), not live form
    // interaction, so useForm doesn't need real state.
    useForm: (initial: Record<string, unknown>) => ({
        data: initial,
        setData: vi.fn(),
        post: vi.fn(),
        processing: false,
        errors: {},
        reset: vi.fn(),
    }),
    Head: () => null,
    usePage: () => ({ props: { locale: mockLocale, auth: { user: null }, flash: { status: null, warning: null } }, url: '/register' }),
    Link: ({ href, children, ...rest }: Record<string, unknown> & { href: string; children: React.ReactNode }) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
    router: { patch: vi.fn(), on: vi.fn(() => () => {}) },
}));

afterEach(() => {
    window.history.pushState({}, '', '/register');
    mockLocale = 'en';
});

describe('Register', () => {
    it('defaults to the Customer role when there is no role query param', () => {
        window.history.pushState({}, '', '/register');
        render(<Register />);
        expect(screen.getByRole('radio', { name: 'Customer' })).toBeChecked();
        expect(screen.getByRole('radio', { name: 'Provider' })).not.toBeChecked();
    });

    it('pre-selects the Provider role when ?role=provider is present', () => {
        window.history.pushState({}, '', '/register?role=provider');
        render(<Register />);
        expect(screen.getByRole('radio', { name: 'Provider' })).toBeChecked();
        expect(screen.getByRole('radio', { name: 'Customer' })).not.toBeChecked();
    });

    it('falls back to Customer for an unrecognized role value', () => {
        window.history.pushState({}, '', '/register?role=admin');
        render(<Register />);
        expect(screen.getByRole('radio', { name: 'Customer' })).toBeChecked();
    });

    it('renders the phone field with tel-appropriate input attributes', () => {
        window.history.pushState({}, '', '/register');
        render(<Register />);
        const phoneInput = screen.getByLabelText('Phone (optional)');
        expect(phoneInput).toHaveAttribute('type', 'tel');
        expect(phoneInput).toHaveAttribute('inputMode', 'tel');
        expect(phoneInput).toHaveAttribute('autoComplete', 'tel');
        expect(phoneInput).toHaveAttribute('maxLength', '30');
    });

    it('renders both consent checkboxes, unchecked by default', () => {
        window.history.pushState({}, '', '/register');
        render(<Register />);
        const checkboxes = screen.getAllByRole('checkbox');
        // The role radio buttons use type="radio", not "checkbox", so this
        // is exactly the two consent boxes (age confirmation, legal
        // agreement) — both must start unchecked.
        expect(checkboxes).toHaveLength(2);
        checkboxes.forEach((checkbox) => expect(checkbox).not.toBeChecked());
    });

    it('links to Terms of Service and Privacy Policy in a new tab, without losing the rest of the form', () => {
        window.history.pushState({}, '', '/register');
        render(<Register />);

        // AppLayout's own LegalFooter also links to Terms/Privacy (without
        // target="_blank"), so scope to the consent checkbox's own link —
        // the first of each in document order, since the form comes
        // before the footer.
        const termsLink = screen.getAllByRole('link', { name: 'Terms of Service' })[0];
        expect(termsLink).toHaveAttribute('href', '/terms');
        expect(termsLink).toHaveAttribute('target', '_blank');
        expect(termsLink).toHaveAttribute('rel', 'noopener noreferrer');

        const privacyLink = screen.getAllByRole('link', { name: 'Privacy Policy' })[0];
        expect(privacyLink).toHaveAttribute('href', '/privacy');
        expect(privacyLink).toHaveAttribute('target', '_blank');
        expect(privacyLink).toHaveAttribute('rel', 'noopener noreferrer');
    });

    it('renders without error in every supported locale (en/ja/vi), with localized consent text and a footer', () => {
        window.history.pushState({}, '', '/register');

        mockLocale = 'en';
        const en = render(<Register />);
        expect(screen.getByText('I confirm that I am 18 years of age or older.')).toBeInTheDocument();
        expect(screen.getAllByRole('link', { name: 'Terms of Service' })).toHaveLength(2); // consent text + footer
        en.unmount();

        mockLocale = 'ja';
        const ja = render(<Register />);
        expect(screen.getByText('18歳以上であることを確認します。')).toBeInTheDocument();
        expect(ja.container.querySelectorAll('a[href="/terms"]')).toHaveLength(2);
        ja.unmount();

        mockLocale = 'vi';
        const vi_ = render(<Register />);
        expect(screen.getByText('Tôi xác nhận đã đủ 18 tuổi trở lên.')).toBeInTheDocument();
        vi_.unmount();
    });
});
