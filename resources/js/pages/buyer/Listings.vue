<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import PhotoCarousel from '../../components/PhotoCarousel.vue';
import SlotPicker from '../../components/SlotPicker.vue';
import { formatPrice, formatRooms, formatTimezone } from '../../format';
import type { ListingMatch } from '../../types';

defineProps<{ hasAvailability: boolean; listings: ListingMatch[] }>();
</script>

<template>
    <Head title="Homes" />
    <main class="mx-auto max-w-5xl px-4 py-8">
        <h1 class="text-2xl font-semibold tracking-tight">Homes you can visit</h1>
        <p class="mt-1 text-sm text-stone-600">Only homes with an opening that fits your availability. Times are local to each home.</p>

        <div v-if="!hasAvailability" class="card mt-6 p-8 text-center">
            <p class="text-stone-600">Add your availability to see the homes you can visit.</p>
            <Link href="/buyer/availability" class="btn btn-primary mt-4">Add availability</Link>
        </div>
        <div v-else-if="!listings.length" class="card mt-6 p-8 text-center">
            <p class="text-stone-600">No home has an opening within your availability.</p>
            <Link href="/buyer/availability" class="btn mt-4">Change availability</Link>
        </div>
        <ul v-else class="mt-6 grid items-start gap-6 md:grid-cols-2">
            <li v-for="listing in listings" :key="listing.id" class="card overflow-hidden">
                <PhotoCarousel :photos="listing.photos" :label="listing.address" />
                <div class="p-5">
                    <h2 class="font-medium">{{ listing.address }}</h2>
                    <p class="text-sm text-stone-600">{{ listing.city }}, {{ listing.state }} · {{ formatTimezone(listing.timezone) }}</p>
                    <p class="mt-2 text-sm">
                        <span class="font-semibold">{{ formatPrice(listing.price) }}</span> · {{ formatRooms(listing.bedrooms, listing.bathrooms) }}
                    </p>
                    <p v-if="listing.description" class="mt-2 text-sm text-stone-700">{{ listing.description }}</p>
                    <SlotPicker :listing="listing" class="mt-4 border-t border-stone-200 pt-4" />
                </div>
            </li>
        </ul>
    </main>
</template>
