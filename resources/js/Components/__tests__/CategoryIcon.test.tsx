import { describe, expect, it } from 'vitest';
import { render } from '@testing-library/react';
import { CategoryIcon } from '@/Components/CategoryIcon';

describe('CategoryIcon', () => {
    it('renders a known slug without throwing', () => {
        const { container } = render(<CategoryIcon slug="cleaning" />);
        expect(container.querySelector('svg')).not.toBeNull();
    });

    it.each(['motorbike-repair', 'education'])('renders the newly added slug "%s" without throwing', (slug) => {
        const { container } = render(<CategoryIcon slug={slug} />);
        expect(container.querySelector('svg')).not.toBeNull();
    });

    it('falls back to a generic icon for an unrecognized slug', () => {
        // Production category content may not match the seeded dev/test
        // slugs at all — this must never throw or render nothing.
        const { container } = render(<CategoryIcon slug="some-future-category" />);
        expect(container.querySelector('svg')).not.toBeNull();
    });

    it('marks the icon as decorative', () => {
        const { container } = render(<CategoryIcon slug="cleaning" />);
        expect(container.querySelector('svg')).toHaveAttribute('aria-hidden', 'true');
    });
});
