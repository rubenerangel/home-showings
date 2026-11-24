<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { formatCount, formatDate, formatPrice, formatRoom } from '../format';
import type { HomeCard } from '../types';
import FactList from './FactList.vue';
import StreetSign from './StreetSign.vue';

defineProps<{ home: HomeCard; eager?: boolean }>();
</script>

<template>
    <li class="home-tile">
        <!-- In one cell, and not positioned: the link is stretched over the tile. -->
        <div class="grid overflow-clip rounded-t-[9px]">
            <img
                v-if="home.cover"
                :src="home.cover.url"
                :alt="`${formatRoom(home.cover.room)} of ${home.address}`"
                :loading="eager ? 'eager' : 'lazy'"
                width="1200"
                height="800"
                class="col-start-1 row-start-1 aspect-3/2 w-full object-cover"
            />
            <div v-else class="col-start-1 row-start-1 flex aspect-3/2 w-full min-w-0 self-start items-center justify-center bg-curb/50 text-gravel">No photos yet</div>
            <h3 class="col-start-1 row-start-1 min-w-0 self-start p-3">
                <!-- The whole tile is the link. -->
                <Link :href="`/homes/${home.id}`" class="home-tile-link"><StreetSign small>{{ home.address }}</StreetSign></Link>
            </h3>
        </div>
        <div class="flex grow flex-col p-5">
            <p class="text-2xl font-extrabold tracking-tight tabular-nums">{{ formatPrice(home.price) }}</p>
            <FactList :facts="[home.bedrooms === 0 ? 'Studio' : `${home.bedrooms} bd`, `${home.bathrooms} ba`]" class="mt-0.5" />
            <div class="mt-auto flex flex-wrap items-center gap-x-2.5 gap-y-1.5 pt-4 text-sm">
                <span class="font-semibold">{{ formatCount(home.openings, 'opening', 'openings') }}, next {{ formatDate(home.next_opening) }}</span>
                <span v-if="home.fits" class="badge badge-sign">Fits your availability</span>
            </div>
        </div>
    </li>
</template>
