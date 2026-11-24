<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import ListingForm from '../../../components/ListingForm.vue';
import PhotoManager from '../../../components/PhotoManager.vue';
import type { Listing, ListingLocks, ListingOptions, Photo } from '../../../types';

defineProps<{ listing: Listing; photos: Photo[]; locks: ListingLocks; options: ListingOptions }>();
</script>

<template>
    <Head :title="listing.address" />
    <main class="mx-auto max-w-3xl px-4 py-8">
        <h1 class="mb-6 text-2xl font-semibold tracking-tight">Edit home</h1>
        <PhotoManager :listing-id="listing.id" :photos="photos" :rooms="options.rooms" class="mb-6" />
        <ListingForm :key="listing.id" :listing="listing" :locks="locks" :options="options" />
    </main>
</template>
