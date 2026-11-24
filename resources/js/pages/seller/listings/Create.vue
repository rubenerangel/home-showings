<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import ListingForm from '../../../components/ListingForm.vue';
import type { ListingOptions } from '../../../types';

defineProps<{ options: ListingOptions }>();
</script>

<template>
    <Head title="Add a home" />
    <main class="mx-auto max-w-3xl px-4 py-8">
        <h1 class="mb-6 text-2xl font-semibold tracking-tight">Add a home</h1>
        <ListingForm :options="options" />
    </main>
</template>
