import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';

import AreaLayout from './layouts/AreaLayout.vue';

createInertiaApp({
    title: (title) => (title ? `${title} · Home Showings` : 'Home Showings'),
    layout: (name) => (/^(seller|buyer)\//.test(name) ? AreaLayout : null),
});
