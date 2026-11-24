<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';

import FactList from '../components/FactList.vue';
import HouseTime from '../components/HouseTime.vue';
import PhotoCarousel from '../components/PhotoCarousel.vue';
import SlotPicker from '../components/SlotPicker.vue';
import StreetSign from '../components/StreetSign.vue';
import { formatCount, formatDate, formatFacts, formatPrice } from '../format';
import type { BuyerShowing, Photo, PublicHome, Slot } from '../types';

defineProps<{
    home: PublicHome & { photos: Photo[] };
    openings: { date: string; count: number }[];
    /** Only for a buyer. */
    booking: { slots: Slot[]; showing: BuyerShowing | null } | null;
}>();

const page = usePage();
</script>

<template>
    <Head :title="home.address" />
    <main class="page">
        <Link href="/" class="link inline-flex min-h-11 items-center">All homes</Link>
        <div class="mt-2 grid items-start gap-6 lg:grid-cols-[6fr_5fr] lg:gap-8">
            <div class="panel overflow-clip">
                <PhotoCarousel :photos="home.photos" :label="home.address" />
            </div>
            <div class="min-w-0">
                <h1><StreetSign>{{ home.address }}</StreetSign></h1>
                <div class="mt-4 flex flex-wrap items-baseline justify-between gap-x-4">
                    <p class="text-lg font-bold wrap-anywhere">{{ home.city }}, {{ home.state_name }}</p>
                    <p class="text-2xl font-extrabold tracking-tight tabular-nums">{{ formatPrice(home.price) }}</p>
                </div>
                <FactList :facts="formatFacts(home)" class="mt-1" />
                <p v-if="home.description" class="mt-3 max-w-[60ch] break-words">{{ home.description }}</p>

                <section class="panel mt-6 p-5 sm:p-7" aria-labelledby="visit">
                    <h2 id="visit" class="text-xl font-extrabold">{{ booking?.showing ? 'Your showing' : 'Visit this home' }}</h2>

                    <template v-if="booking?.showing">
                        <HouseTime :starts-at="booking.showing.starts_at" :ends-at="booking.showing.ends_at" :timezone="home.timezone" class="mt-3" />
                        <p class="mt-2 text-gravel">You can have one showing at a time in each home.</p>
                        <Link href="/buyer/showings" class="btn mt-4">See my showings</Link>
                    </template>
                    <p v-else-if="!openings.length" class="mt-2 text-gravel">No openings right now.</p>
                    <template v-else-if="booking">
                        <p v-if="!booking.slots.length" class="mt-2 text-gravel">Its open times overlap your other showings.</p>
                        <template v-else>
                            <p class="mt-1 text-gravel">
                                Times are local to the home.
                                {{ booking.slots.some((slot) => slot.fits) ? 'A check marks the ones inside your availability.' : 'You can pick any of them.' }}
                            </p>
                            <SlotPicker :slots="booking.slots" :timezone="home.timezone" :book-url="`/homes/${home.id}/showings`" class="mt-4" />
                        </template>
                    </template>
                    <template v-else>
                        <ul class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                            <li v-for="day in openings.slice(0, 6)" :key="day.date" class="rounded-md bg-sign-wash px-3 py-2">
                                <p class="font-bold">{{ formatDate(day.date) }}</p>
                                <p class="text-sm text-sign-deep tabular-nums">{{ formatCount(day.count, 'opening', 'openings') }}</p>
                            </li>
                        </ul>
                        <p v-if="openings.length > 6" class="mt-2 text-sm text-gravel">And {{ formatCount(openings.length - 6, 'more day', 'more days') }}.</p>
                        <template v-if="!page.props.user">
                            <Link :href="`/register?home=${home.id}`" class="btn btn-primary mt-5 w-full sm:w-auto">Book a showing</Link>
                            <p class="mt-3 text-sm text-gravel">
                                You need an account to see the times. Already have one? <Link :href="`/login?home=${home.id}`" class="link">Log in</Link>
                            </p>
                        </template>
                        <p v-else class="mt-4 text-sm text-gravel">Buyers see the times and book them.</p>
                    </template>
                </section>
            </div>
        </div>
    </main>
</template>
