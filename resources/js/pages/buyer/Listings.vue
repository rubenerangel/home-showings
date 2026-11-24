<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import type { ListingMatch } from '../../types';

defineProps<{ hasAvailability: boolean; listings: ListingMatch[] }>();
</script>

<template>
    <Head title="Homes" />
    <main class="mx-auto max-w-3xl p-8">
        <h1 class="text-2xl font-semibold">Homes</h1>
        <p v-if="!hasAvailability" class="mt-4">Add your availability to see homes you can visit.</p>
        <ul v-else class="mt-4 space-y-2">
            <li v-for="listing in listings" :key="listing.id">{{ listing.address }}</li>
        </ul>
    </main>
</template>
