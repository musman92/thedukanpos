import '../css/app.css';
import './bootstrap';
import { initTheme } from './theme';
import {
    installBranchTabTransport,
    syncTabBranchFromPage,
} from './lib/branchTab';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

initTheme();
installBranchTabTransport(router);

const appName = import.meta.env.VITE_APP_NAME || 'DukanPOS';

export function bootInertia(pageGlob) {
    createInertiaApp({
        title: (title) => (title ? `${title} · ${appName}` : appName),
        resolve: (name) =>
            resolvePageComponent(`./Pages/${name}.jsx`, pageGlob),
        setup({ el, App, props }) {
            const page = props.initialPage;
            const branch = page?.props?.branch;
            syncTabBranchFromPage(branch, router);

            createRoot(el).render(<App {...props} />);
        },
        progress: {
            color: '#0f766e',
        },
    });
}
