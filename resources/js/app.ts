import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';

import SiteLayout from './layouts/SiteLayout.vue';

createInertiaApp({
    title: (title) => (title ? `${title} · Home Showings` : 'Home Showings'),
    layout: (name) => (/^((seller|buyer)\/|(Notifications|Homes|Home)$)/.test(name) ? SiteLayout : null),
});
