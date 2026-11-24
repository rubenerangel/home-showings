<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import ListingForm from '../../../components/ListingForm.vue';
import PhotoManager from '../../../components/PhotoManager.vue';
import StreetSign from '../../../components/StreetSign.vue';
import type { Listing, ListingLocks, ListingOptions, ListingPhoto } from '../../../types';

defineProps<{ listing: Listing; photos: ListingPhoto[]; locks: ListingLocks; options: ListingOptions }>();
</script>

<template>
    <Head :title="listing.address" />
    <main class="page-narrow">
        <p class="mb-3"><StreetSign small>{{ listing.address }}</StreetSign></p>
        <h1 class="title mb-6">Edit home</h1>
        <PhotoManager :listing-id="listing.id" :photos="photos" :rooms="options.rooms" class="mb-6" />
        <ListingForm :key="listing.id" :listing="listing" :locks="locks" :options="options" />
    </main>
</template>
