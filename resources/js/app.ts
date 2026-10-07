import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { initializeTheme } from './composables/useAppearance';
import { applySgcAppearance, type SgcAppearance } from './lib/sgc-appearance';

const appName = import.meta.env.VITE_APP_NAME || 'SGC SmartTrack';

const pageAppearance = (page: { props?: { sgc?: { appearance?: { current?: SgcAppearance } } } }) => page?.props?.sgc?.appearance?.current;

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const current = pageAppearance(props.initialPage as { props?: { sgc?: { appearance?: { current?: SgcAppearance } } } });
        if (current) {
            applySgcAppearance(current);
        }

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#2aa7a0',
    },
});

router.on('navigate', (event) => {
    const current = pageAppearance(event.detail.page);
    if (current) {
        applySgcAppearance(current);
    }
});

initializeTheme();
