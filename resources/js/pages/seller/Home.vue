<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import FactList from '../../components/FactList.vue';
import StreetSign from '../../components/StreetSign.vue';
import { formatFacts, formatPrice } from '../../format';
import type { Listing, Photo } from '../../types';

defineProps<{ listings: (Listing & { cover: Photo | null })[] }>();
</script>

<template>
    <Head title="My homes" />
    <main class="page">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="title">My homes</h1>
            <Link href="/seller/listings/create" class="btn btn-primary">Add a home</Link>
        </div>
        <ul v-if="listings.length" class="mt-8 grid gap-7 md:grid-cols-2">
            <li v-for="listing in listings" :key="listing.id" class="panel flex flex-col overflow-clip">
                <div class="relative">
                    <h2 class="absolute top-4 left-4 max-w-[calc(100%-2rem)]"><StreetSign>{{ listing.address }}</StreetSign></h2>
                    <img v-if="listing.cover" :src="listing.cover.url" alt="" width="1200" height="800" class="aspect-3/2 w-full object-cover" />
                    <div v-else class="flex aspect-3/2 items-center justify-center bg-curb/50 text-gravel">No photos yet</div>
                </div>
                <div class="flex grow flex-col p-5 sm:p-6">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4">
                        <p class="text-lg font-bold wrap-anywhere">{{ listing.city }}, {{ listing.state }}</p>
                        <p class="text-2xl font-extrabold tracking-tight tabular-nums">{{ formatPrice(listing.price) }}</p>
                    </div>
                    <FactList :facts="[...formatFacts(listing), `${listing.showing_duration_minutes} min showings`]" class="mt-1 mb-5" />
                    <div class="mt-auto flex gap-3">
                        <Link :href="`/seller/listings/${listing.id}/schedule`" class="btn btn-primary">Schedule<span class="sr-only"> of {{ listing.address }}</span></Link>
                        <Link :href="`/seller/listings/${listing.id}/edit`" class="btn">Edit<span class="sr-only"> {{ listing.address }}</span></Link>
                    </div>
                </div>
            </li>
        </ul>
        <div v-else class="panel mt-8 px-6 py-12 text-center">
            <p class="text-lg font-bold">You have no homes yet.</p>
            <p class="mt-1 text-gravel">Add one to publish showing times.</p>
            <Link href="/seller/listings/create" class="btn btn-primary mt-5">Add a home</Link>
        </div>
    </main>
</template>
