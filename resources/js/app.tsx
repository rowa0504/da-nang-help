import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { ReactNode } from 'react';

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob<{ default: (props: unknown) => ReactNode }>('./Pages/**/*.tsx', {
            eager: true,
        });
        return pages[`./Pages/${name}.tsx`];
    },
    setup({ el, App, props }) {
        if (!el) {
            throw new Error('Inertia root element (#app) not found.');
        }
        createRoot(el).render(<App {...props} />);
    },
});
