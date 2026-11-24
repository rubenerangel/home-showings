<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import ListingForm from '../../../components/ListingForm.vue';
import type { ListingOptions } from '../../../types';

defineProps<{ options: ListingOptions }>();
</script>

<template>
    <Head title="Add a home" />
    <main class="page-narrow">
        <h1 class="title mb-6">Add a home</h1>
        <ListingForm :options="options" />
    </main>
</template>
