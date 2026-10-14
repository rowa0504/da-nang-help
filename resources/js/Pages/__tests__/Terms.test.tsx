import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import Terms from '@/Pages/Terms';

let mockLocale = 'en';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ href, children }: Record<string, unknown> & { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
    usePage: () => ({
        url: '/terms',
        props: { locale: mockLocale, auth: { user: null }, flash: { status: null, warning: null } },
    }),
    router: { on: vi.fn(() => () => {}) },
}));

describe('Terms page', () => {
    it('renders the operator name and omits the effective date while it is null (draft stage)', () => {
        mockLocale = 'en';
        render(<Terms operatorName="Da Nang Help Co., Ltd." contactEmail="privacy@danang-help.com" effectiveDate={null} governingLocale="vi" />);

        expect(screen.getAllByText(/Da Nang Help Co., Ltd\./).length).toBeGreaterThan(0);
        expect(screen.queryByText('Effective date:')).not.toBeInTheDocument();
    });

    it('never renders the literal string "null" or a broken mailto link when operator/contact props are null', () => {
        mockLocale = 'en';
        render(<Terms operatorName={null} contactEmail={null} effectiveDate={null} governingLocale="vi" />);

        expect(screen.queryByText(/\bnull\b/i)).not.toBeInTheDocument();
        expect(document.querySelector('a[href^="mailto:"]')).not.toBeInTheDocument();
    });

    it('shows the effective date once it is set', () => {
        mockLocale = 'en';
        render(
            <Terms operatorName="Da Nang Help Co., Ltd." contactEmail="privacy@danang-help.com" effectiveDate="2027-01-01" governingLocale="vi" />,
        );

        expect(screen.getByText(/2027-01-01/)).toBeInTheDocument();
    });

    it('localizes the governing-locale language name by the viewer’s own locale, not the document’s', () => {
        mockLocale = 'en';
        const { rerender } = render(
            <Terms operatorName="Op" contactEmail="privacy@danang-help.com" effectiveDate={null} governingLocale="vi" />,
        );
        expect(screen.getByText(/the Vietnamese version prevails/)).toBeInTheDocument();

        mockLocale = 'ja';
        rerender(<Terms operatorName="Op" contactEmail="privacy@danang-help.com" effectiveDate={null} governingLocale="vi" />);
        expect(screen.getByText(/ベトナム語版を優先/)).toBeInTheDocument();

        mockLocale = 'vi';
        rerender(<Terms operatorName="Op" contactEmail="privacy@danang-help.com" effectiveDate={null} governingLocale="vi" />);
        expect(screen.getByText(/phiên bản tiếng Việt sẽ được ưu tiên/)).toBeInTheDocument();
    });
});
