import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { LegalFooter } from '@/Components/LegalFooter';

let mockLocale = 'en';

vi.mock('@inertiajs/react', () => ({
    Link: ({ href, children }: Record<string, unknown> & { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
    usePage: () => ({ props: { locale: mockLocale, auth: { user: null }, flash: { status: null, warning: null } } }),
}));

describe('LegalFooter', () => {
    it('links to the Terms of Service and Privacy Policy pages', () => {
        mockLocale = 'en';
        render(<LegalFooter />);

        expect(screen.getByRole('link', { name: 'Terms of Service' })).toHaveAttribute('href', '/terms');
        expect(screen.getByRole('link', { name: 'Privacy Policy' })).toHaveAttribute('href', '/privacy');
    });

    it('renders localized link labels in every supported locale', () => {
        mockLocale = 'ja';
        const ja = render(<LegalFooter />);
        expect(screen.getByRole('link', { name: '利用規約' })).toHaveAttribute('href', '/terms');
        expect(screen.getByRole('link', { name: 'プライバシーポリシー' })).toHaveAttribute('href', '/privacy');
        ja.unmount();

        mockLocale = 'vi';
        render(<LegalFooter />);
        expect(screen.getByRole('link', { name: 'Điều Khoản Dịch Vụ' })).toHaveAttribute('href', '/terms');
        expect(screen.getByRole('link', { name: 'Chính Sách Quyền Riêng Tư' })).toHaveAttribute('href', '/privacy');
    });
});
