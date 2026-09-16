import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        // Keep <html data-theme> in sync on client-side navigation: the Blade
        // shell only stamps it on a full page load, so without this a visit
        // between pages with different themes (#[UsesTheme]) leaves the old
        // theme's tokens applied.
        router.on('navigate', (event) => {
            document.documentElement.dataset.theme = event.detail.page.props.theme;
        });

        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});
