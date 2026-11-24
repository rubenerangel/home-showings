<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { onMounted } from 'vue';

import { dropPush } from '../push';

defineProps<{ title: string; lead: string }>();

const page = usePage();

// Nobody is signed in here, so this browser must not keep receiving what was for someone else.
onMounted(() => {
    if (!page.props.user) void dropPush();
});
</script>

<template>
    <Head :title="title" />
    <main class="page-narrow sm:pt-20">
        <Link href="/" class="rounded text-lg font-extrabold tracking-tight text-sign">Home Showings</Link>
        <h1 class="title mt-3">{{ title }}</h1>
        <p class="lead">{{ lead }}</p>
        <div class="panel mt-8 max-w-xl p-5 sm:p-6"><slot /></div>
        <p class="mt-6"><slot name="other" /></p>
    </main>
</template>
