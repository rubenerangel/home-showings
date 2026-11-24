<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import { formatPrice, formatRooms, formatTimezone } from '../../format';
import type { Listing } from '../../types';

defineProps<{ listings: Listing[] }>();
</script>

<template>
    <Head title="My homes" />
    <main class="mx-auto max-w-5xl px-4 py-8">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold tracking-tight">My homes</h1>
            <Link href="/seller/listings/create" class="btn btn-primary">Add a home</Link>
        </div>
        <ul v-if="listings.length" class="mt-6 grid gap-4 sm:grid-cols-2">
            <li v-for="listing in listings" :key="listing.id" class="card flex flex-col p-5">
                <h2 class="font-medium">{{ listing.address }}</h2>
                <p class="text-sm text-stone-600">{{ listing.city }}, {{ listing.state }}</p>
                <p class="mt-2 text-sm">
                    <span class="font-semibold">{{ formatPrice(listing.price) }}</span> · {{ formatRooms(listing.bedrooms, listing.bathrooms) }}
                </p>
                <p class="mt-1 text-sm text-stone-600">{{ listing.showing_duration_minutes }} min showings · {{ formatTimezone(listing.timezone) }}</p>
                <div class="mt-4">
                    <Link :href="`/seller/listings/${listing.id}/edit`" class="btn">Edit<span class="sr-only"> {{ listing.address }}</span></Link>
                </div>
            </li>
        </ul>
        <p v-else class="card mt-6 p-8 text-center text-stone-600">You have no homes yet. Add one to publish showing times.</p>
    </main>
</template>
