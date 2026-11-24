<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import HomeTile from '../components/HomeTile.vue';
import { formatTimezone } from '../format';
import { groupByCity } from '../places';
import type { HomeCard } from '../types';

const props = defineProps<{ homes: HomeCard[]; state: string | null; states: { code: string; name: string; count: number }[] }>();

const places = computed(() => groupByCity(props.homes));
const nameOf = (place: { city: string; homes: HomeCard[] }) => `${place.city}, ${place.homes[0].state_name}`;
// The first row, which is in sight from the start.
const eager = computed(() => new Set(props.homes.slice(0, 3).map(({ id }) => id)));

const columns = (homes: number) => Math.min(homes, 3) as 1 | 2 | 3;
const spans = { 1: '', 2: 'sm:col-span-2', 3: 'sm:col-span-2 lg:col-span-3' };
const inner = { 1: '', 2: 'sm:grid-cols-2', 3: 'sm:grid-cols-2 lg:grid-cols-3' };

const total = computed(() => props.states.reduce((sum, { count }) => sum + count, 0));
const filters = computed(() => [{ code: null, name: 'All', count: total.value, href: '/' }, ...props.states.map((state) => ({ ...state, href: `/?state=${state.code}` }))]);
</script>

<template>
    <Head title="Homes" />
    <main class="page">
        <h1 class="title">Homes you can visit</h1>
        <p class="lead">Every home with open showing times. Pick one, and book the time that suits you.</p>

        <nav v-if="states.length > 1" aria-label="Filter by state" class="-mx-4 mt-6 flex gap-2 overflow-x-auto px-4 py-1 sm:mx-0 sm:flex-wrap sm:px-0">
            <Link
                v-for="filter in filters"
                :key="filter.href"
                :href="filter.href"
                class="filter"
                :aria-current="state === filter.code ? 'true' : undefined"
                preserve-scroll
            >
                {{ filter.name }} <span class="tabular-nums opacity-75">{{ filter.count }}</span>
            </Link>
        </nav>

        <div v-if="!homes.length" class="panel mt-8 px-6 py-12 text-center">
            <p class="text-lg font-bold">{{ state ? 'No home has open times there right now.' : 'No home has open times right now.' }}</p>
            <Link v-if="state && total" href="/" class="btn mt-5">See all homes</Link>
        </div>
        <!-- A city takes the columns its homes need, so the ones with one home go side by side. -->
        <div v-if="homes.length" class="mt-8 grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
            <section v-for="place in places" :key="place.key" :aria-label="nameOf(place)" :class="spans[columns(place.homes.length)]">
                <h2 class="flex flex-wrap items-baseline gap-x-3 border-b border-curb pb-2">
                    <span class="text-lg font-extrabold wrap-anywhere">{{ nameOf(place) }}</span>
                    <span class="text-sm text-gravel">{{ formatTimezone(place.homes[0].timezone) }} time</span>
                </h2>
                <ul class="mt-4 grid gap-6" :class="inner[columns(place.homes.length)]">
                    <HomeTile v-for="home in place.homes" :key="home.id" :home="home" :eager="eager.has(home.id)" />
                </ul>
            </section>
        </div>
    </main>
</template>
