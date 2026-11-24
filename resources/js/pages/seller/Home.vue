<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import { formatPrice, formatRooms, formatTimezone } from '../../format';
import type { Listing, Photo } from '../../types';

defineProps<{ listings: (Listing & { cover: Photo | null })[] }>();
</script>

<template>
    <Head title="My homes" />
    <main class="mx-auto max-w-5xl px-4 py-8">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold tracking-tight">My homes</h1>
            <Link href="/seller/listings/create" class="btn btn-primary">Add a home</Link>
        </div>
        <ul v-if="listings.length" class="mt-6 grid gap-4 sm:grid-cols-2">
            <li v-for="listing in listings" :key="listing.id" class="card overflow-hidden">
                <img v-if="listing.cover" :src="listing.cover.url" alt="" class="aspect-3/2 w-full object-cover" />
                <div v-else class="flex aspect-3/2 items-center justify-center bg-stone-100 text-sm text-stone-600">No photos yet</div>
                <div class="p-5">
                    <h2 class="font-medium">{{ listing.address }}</h2>
                    <p class="text-sm text-stone-600">{{ listing.city }}, {{ listing.state }}</p>
                    <p class="mt-2 text-sm">
                        <span class="font-semibold">{{ formatPrice(listing.price) }}</span> · {{ formatRooms(listing.bedrooms, listing.bathrooms) }}
                    </p>
                    <p class="mt-1 text-sm text-stone-600">{{ listing.showing_duration_minutes }} min showings · {{ formatTimezone(listing.timezone) }}</p>
                    <div class="mt-4 flex gap-3">
                        <Link :href="`/seller/listings/${listing.id}/schedule`" class="btn">Schedule<span class="sr-only"> of {{ listing.address }}</span></Link>
                        <Link :href="`/seller/listings/${listing.id}/edit`" class="btn">Edit<span class="sr-only"> {{ listing.address }}</span></Link>
                    </div>
                </div>
            </li>
        </ul>
        <p v-else class="card mt-6 p-8 text-center text-stone-600">You have no homes yet. Add one to publish showing times.</p>
    </main>
</template>
