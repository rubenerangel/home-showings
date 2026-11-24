<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

import type { ListingMatch, Slot } from '../types';
import HouseTime from './HouseTime.vue';

const props = defineProps<{ listing: ListingMatch }>();

const keyOf = (slot: Slot) => `${slot.window_id} ${slot.starts_at}`;
const selectedKey = ref<string | null>(null);
// A slot that is no longer offered is not selected any more.
const selected = computed(() => props.listing.slots.find((slot) => keyOf(slot) === selectedKey.value));
const booking = ref(false);

const days = computed(() => {
    const format = (options: Intl.DateTimeFormatOptions) => new Intl.DateTimeFormat('en-US', { timeZone: props.listing.timezone, ...options });
    const weekday = format({ weekday: 'short' });
    const date = format({ month: 'short', day: 'numeric' });
    const time = format({ hour: 'numeric', minute: '2-digit' });

    return props.listing.slots.reduce<{ weekday: string; date: string; slots: { slot: Slot; time: string }[] }[]>((groups, slot) => {
        const start = new Date(slot.starts_at);
        if (groups.at(-1)?.date !== date.format(start)) groups.push({ weekday: weekday.format(start), date: date.format(start), slots: [] });
        groups.at(-1)!.slots.push({ slot, time: time.format(start) });

        return groups;
    }, []);
});

const visibleDays = 3;
const showAll = ref(false);
const shownDays = computed(() => (showAll.value ? days.value : days.value.slice(0, visibleDays)));
const hiddenDays = computed(() => days.value.length - visibleDays);

const choose = async (slot: Slot, event: MouseEvent) => {
    const button = event.currentTarget as HTMLElement;
    selectedKey.value = keyOf(slot) === selectedKey.value ? null : keyOf(slot);
    await nextTick();
    // The booking bar must not cover what was just chosen.
    button.scrollIntoView({ block: 'nearest' });
};

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
        <div class="grid gap-x-4 gap-y-5 sm:grid-cols-3">
            <fieldset v-for="day in shownDays" :key="day.date" class="min-w-0">
                <legend class="mb-2 text-[0.9375rem] text-gravel"><span class="font-extrabold text-asphalt">{{ day.weekday }}</span> {{ day.date }}</legend>
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-1">
                    <button
                        v-for="{ slot, time } in day.slots"
                        :key="keyOf(slot)"
                        type="button"
                        class="slot"
                        :aria-pressed="keyOf(slot) === selectedKey"
                        @click="choose(slot, $event)"
                    >
                        {{ time }}
                    </button>
                </div>
            </fieldset>
        </div>
        <button v-if="hiddenDays > 0" type="button" class="link mt-4 inline-flex min-h-11 items-center" :aria-expanded="showAll" @click="showAll = !showAll">
            {{ showAll ? 'Show fewer days' : `Show ${hiddenDays} more ${hiddenDays === 1 ? 'day' : 'days'}` }}
        </button>
        <div aria-live="polite" class="sticky bottom-0 z-10 -mx-5 -mb-5 sm:-mx-7 sm:-mb-7">
            <Transition name="appear">
                <div v-if="selected" class="mt-5 flex flex-wrap items-center justify-between gap-x-4 gap-y-3 border-t border-sign-line bg-sign-wash px-5 pt-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:px-7">
                    <HouseTime :starts-at="selected.starts_at" :ends-at="selected.ends_at" :timezone="listing.timezone" />
                    <button type="button" class="btn btn-primary max-sm:w-full" :disabled="booking" @click="book">{{ booking ? 'Booking…' : 'Book this showing' }}</button>
                </div>
            </Transition>
        </div>
    </div>
</template>
