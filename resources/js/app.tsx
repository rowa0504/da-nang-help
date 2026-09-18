import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { ReactNode } from 'react';

createInertiaApp({
    resolve: (name) => {
        // The negated second pattern keeps Vitest specs living alongside
        // pages (e.g. Pages/__tests__/Home.test.tsx) out of this glob —
        // without it, `eager: true` would pull vitest/testing-library
        // straight into the production bundle.
        const pages = import.meta.glob<{ default: (props: unknown) => ReactNode }>(
            ['./Pages/**/*.tsx', '!./Pages/**/__tests__/**'],
            { eager: true },
        );
        return pages[`./Pages/${name}.tsx`];
    },
    setup({ el, App, props }) {
        if (!el) {
            throw new Error('Inertia root element (#app) not found.');
        }
        createRoot(el).render(<App {...props} />);
    },
});
