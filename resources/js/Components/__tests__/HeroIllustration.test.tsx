import { describe, expect, it } from 'vitest';
import { render } from '@testing-library/react';
import { HeroIllustration } from '@/Components/HeroIllustration';

describe('HeroIllustration', () => {
    it('renders as a purely decorative graphic', () => {
        const { container } = render(<HeroIllustration />);
        const svg = container.querySelector('svg');
        expect(svg).not.toBeNull();
        expect(svg).toHaveAttribute('aria-hidden', 'true');
    });

    it('forwards className to the root svg', () => {
        const { container } = render(<HeroIllustration className="w-full" />);
        expect(container.querySelector('svg')).toHaveClass('w-full');
    });
});
