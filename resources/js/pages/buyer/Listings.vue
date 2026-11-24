<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import FactList from '../../components/FactList.vue';
import PhotoCarousel from '../../components/PhotoCarousel.vue';
import SlotPicker from '../../components/SlotPicker.vue';
import StreetSign from '../../components/StreetSign.vue';
import { formatFacts, formatPrice } from '../../format';
import type { ListingMatch } from '../../types';

defineProps<{ hasAvailability: boolean; listings: ListingMatch[] }>();
</script>

<template>
    <Head title="Homes that fit" />
    <main class="page">
        <h1 class="title">Homes that fit</h1>
        <p class="lead">Only homes with an opening that fits your availability. Times are local to each home.</p>

        <div v-if="!hasAvailability" class="panel mt-8 px-6 py-12 text-center">
            <p class="text-lg font-bold">When can you visit?</p>
            <p class="mx-auto mt-1 max-w-[46ch] text-gravel">Add your availability to see the homes you can visit.</p>
            <Link href="/buyer/availability" class="btn btn-primary mt-5">Add availability</Link>
        </div>
        <div v-else-if="!listings.length" class="panel mt-8 px-6 py-12 text-center">
            <p class="text-lg font-bold">No home has an opening within your availability.</p>
            <p class="mx-auto mt-1 max-w-[46ch] text-gravel">Another day or a longer range may fit more homes.</p>
            <Link href="/buyer/availability" class="btn mt-5">Change availability</Link>
        </div>
        <ul v-else class="mt-8 space-y-7">
            <li v-for="listing in listings" :key="listing.id" class="panel grid overflow-clip md:grid-cols-[5fr_6fr]">
                <div class="relative">
                    <h2 class="pointer-events-none absolute top-4 left-4 z-10 max-w-[calc(100%-2rem)] sm:top-5 sm:left-5">
                        <Link :href="`/homes/${listing.id}`" class="pointer-events-auto rounded-[7px]"><StreetSign>{{ listing.address }}</StreetSign></Link>
                    </h2>
                    <PhotoCarousel :photos="listing.photos" :label="listing.address" class="md:aspect-auto md:h-full md:min-h-104" />
                </div>
                <div class="min-w-0 p-5 sm:p-7">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4">
                        <p class="text-lg font-bold wrap-anywhere">{{ listing.city }}, {{ listing.state }}</p>
                        <p class="text-2xl font-extrabold tracking-tight tabular-nums">{{ formatPrice(listing.price) }}</p>
                    </div>
                    <FactList :facts="formatFacts(listing)" class="mt-1" />
                    <p v-if="listing.description" class="mt-3 max-w-[52ch] break-words">{{ listing.description }}</p>
                    <SlotPicker :slots="listing.slots" :timezone="listing.timezone" class="mt-5 border-t border-curb pt-5" />
                </div>
            </li>
        </ul>
    </main>
</template>
