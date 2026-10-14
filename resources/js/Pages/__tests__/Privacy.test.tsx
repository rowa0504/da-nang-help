import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import Privacy from '@/Pages/Privacy';

let mockLocale = 'en';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ href, children }: Record<string, unknown> & { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
    usePage: () => ({
        url: '/privacy',
        props: { locale: mockLocale, auth: { user: null }, flash: { status: null, warning: null } },
    }),
    router: { on: vi.fn(() => () => {}) },
}));

describe('Privacy page', () => {
    it('describes Provider feed visibility accurately, never claiming requests are shown in full', () => {
        mockLocale = 'en';
        render(<Privacy operatorName="Op" contactEmail="privacy@danang-help.com" effectiveDate={null} governingLocale="vi" />);

        expect(screen.queryByText(/shown in full/)).not.toBeInTheDocument();
        expect(screen.getByText(/private fields such as the exact address and phone number are not shown until a Job is formed/)).toBeInTheDocument();
    });

    it('never renders the literal string "null" or a broken mailto link when operator/contact props are null', () => {
        mockLocale = 'en';
        render(<Privacy operatorName={null} contactEmail={null} effectiveDate={null} governingLocale="vi" />);

        expect(screen.queryByText(/\bnull\b/i)).not.toBeInTheDocument();
        expect(document.querySelector('a[href^="mailto:"]')).not.toBeInTheDocument();
    });

    it('omits the effective date while it is null (draft stage)', () => {
        mockLocale = 'en';
        render(<Privacy operatorName="Op" contactEmail="privacy@danang-help.com" effectiveDate={null} governingLocale="vi" />);

        expect(screen.queryByText('Effective date:')).not.toBeInTheDocument();
    });

    it('shows the effective date once it is set', () => {
        mockLocale = 'en';
        render(<Privacy operatorName="Op" contactEmail="privacy@danang-help.com" effectiveDate="2027-01-01" governingLocale="vi" />);

        expect(screen.getByText(/2027-01-01/)).toBeInTheDocument();
    });

    it('renders without error in every supported locale (en/ja/vi), each with real localized body text', () => {
        mockLocale = 'en';
        const { rerender } = render(
            <Privacy operatorName="Op" contactEmail="privacy@danang-help.com" effectiveDate={null} governingLocale="vi" />,
        );
        expect(screen.getByText('Privacy Policy (Draft)')).toBeInTheDocument();

        mockLocale = 'ja';
        rerender(<Privacy operatorName="Op" contactEmail="privacy@danang-help.com" effectiveDate={null} governingLocale="vi" />);
        expect(screen.getByText('プライバシーポリシー（草案）')).toBeInTheDocument();

        mockLocale = 'vi';
        rerender(<Privacy operatorName="Op" contactEmail="privacy@danang-help.com" effectiveDate={null} governingLocale="vi" />);
        expect(screen.getByText('Chính Sách Quyền Riêng Tư (Bản thảo)')).toBeInTheDocument();
    });
});
