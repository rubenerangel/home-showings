<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import type { ListingMatch, Slot } from '../types';
import HouseTime from './HouseTime.vue';

const props = defineProps<{ listing: ListingMatch }>();

const keyOf = (slot: Slot) => `${slot.window_id} ${slot.starts_at}`;
const selectedKey = ref<string | null>(null);
// A slot that is no longer offered is not selected any more.
const selected = computed(() => props.listing.slots.find((slot) => keyOf(slot) === selectedKey.value));
const booking = ref(false);

const days = computed(() => {
    const day = new Intl.DateTimeFormat('en-US', { timeZone: props.listing.timezone, weekday: 'long', month: 'long', day: 'numeric' });
    const time = new Intl.DateTimeFormat('en-US', { timeZone: props.listing.timezone, hour: 'numeric', minute: '2-digit' });

    return props.listing.slots.reduce<{ label: string; slots: { slot: Slot; time: string }[] }[]>((groups, slot) => {
        const label = day.format(new Date(slot.starts_at));
        if (groups.at(-1)?.label !== label) groups.push({ label, slots: [] });
        groups.at(-1)!.slots.push({ slot, time: time.format(new Date(slot.starts_at)) });

        return groups;
    }, []);
});

const visibleDays = 3;
const showAll = ref(false);
const shownDays = computed(() => (showAll.value ? days.value : days.value.slice(0, visibleDays)));

const book = () => {
    if (!selected.value) return;
    router.post(
        '/buyer/showings',
        { window_id: selected.value.window_id, starts_at: selected.value.starts_at },
        {
            onStart: () => (booking.value = true),
            onFinish: () => (booking.value = false),
        },
    );
};
</script>

<template>
    <div>
        <fieldset v-for="group in shownDays" :key="group.label" class="mt-3 first:mt-0">
            <legend class="text-sm font-medium">{{ group.label }}</legend>
            <div class="mt-1.5 flex flex-wrap gap-2">
                <button
                    v-for="{ slot, time } in group.slots"
                    :key="keyOf(slot)"
                    type="button"
                    class="btn px-3 py-1.5 aria-pressed:border-teal-800 aria-pressed:bg-teal-50 aria-pressed:text-teal-900"
                    :aria-pressed="keyOf(slot) === selectedKey"
                    @click="selectedKey = keyOf(slot)"
                >
                    {{ time }}
                </button>
            </div>
            <div
                v-if="selected && group.slots.some(({ slot }) => slot === selected)"
                class="mt-2 flex flex-wrap items-center justify-between gap-3 rounded-md bg-stone-100 p-3"
            >
                <HouseTime :starts-at="selected.starts_at" :ends-at="selected.ends_at" :timezone="listing.timezone" class="text-sm" />
                <button type="button" class="btn btn-primary" :disabled="booking" @click="book">Book this showing</button>
            </div>
        </fieldset>
        <button
            v-if="days.length > visibleDays"
            type="button"
            class="mt-3 cursor-pointer text-sm text-teal-800 underline"
            :aria-expanded="showAll"
            @click="showAll = !showAll"
        >
            {{ showAll ? 'Show fewer days' : `Show ${days.length - visibleDays} more days` }}
        </button>
    </div>
</template>
