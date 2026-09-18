import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { Logo, LogoMark } from '@/Components/Logo';

describe('Logo', () => {
    it('renders a decorative mark image alongside the "Da Nang Help" text', () => {
        const { container } = render(<Logo />);
        // alt="" makes this a presentational image (no accessible "img"
        // role), so it's queried directly rather than via getByRole.
        const image = container.querySelector('img');
        expect(image).not.toBeNull();
        expect(image).toHaveAttribute('src', '/favicon.svg');
        expect(image).toHaveAttribute('alt', '');
        expect(screen.getByText('Da Nang')).toBeInTheDocument();
        expect(screen.getByText('Help')).toBeInTheDocument();
    });

    it('LogoMark accepts an explicit alt for standalone use', () => {
        render(<LogoMark alt="Da Nang Help" />);
        expect(screen.getByRole('img', { name: 'Da Nang Help' })).toBeInTheDocument();
    });
});
