<script setup lang="ts">
import { computed, ref } from 'vue';

import { formatRoom } from '../format';
import type { Photo } from '../types';

// https://www.w3.org/WAI/ARIA/apg/patterns/carousel/ without automatic rotation.
const props = defineProps<{ photos: Photo[]; label: string }>();

const selected = ref(0);
const count = computed(() => props.photos.length);
// Photos can disappear while this is on screen.
const current = computed(() => Math.min(selected.value, Math.max(count.value - 1, 0)));

const move = (step: 1 | -1) => {
    if (count.value > 1) selected.value = (current.value + step + count.value) % count.value;
};

const swipeDistance = 40;
let origin: { x: number; y: number } | null = null;

const startSwipe = (event: PointerEvent) => {
    origin = event.isPrimary ? { x: event.clientX, y: event.clientY } : null;
};
const endSwipe = (event: PointerEvent) => {
    if (!origin) return;
    const x = event.clientX - origin.x;
    const y = event.clientY - origin.y;
    origin = null;
    if (Math.abs(x) >= swipeDistance && Math.abs(x) > Math.abs(y)) move(x < 0 ? 1 : -1);
};
const cancelSwipe = () => {
    origin = null;
};

const arrow =
    'absolute top-1/2 z-10 flex size-11 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full bg-white/95 text-asphalt transition-transform duration-150 ease-out hover:bg-white active:scale-95';
</script>

<template>
    <div v-if="count === 0" class="flex aspect-3/2 items-center justify-center bg-curb/50 text-gravel">No photos yet</div>
    <section
        v-else
        aria-roledescription="carousel"
        :aria-label="`Photos of ${label}`"
        class="relative aspect-3/2 overflow-hidden bg-curb"
        @keydown.left.prevent="move(-1)"
        @keydown.right.prevent="move(1)"
    >
        <template v-if="count > 1">
            <button type="button" aria-label="Previous photo" :class="arrow" class="left-3" @click="move(-1)">
                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M15 5l-7 7 7 7" /></svg>
            </button>
            <button type="button" aria-label="Next photo" :class="arrow" class="right-3" @click="move(1)">
                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M9 5l7 7-7 7" /></svg>
            </button>
        </template>
        <div
            aria-live="polite"
            aria-atomic="false"
            class="size-full touch-pan-y touch-pinch-zoom select-none"
            @pointerdown="startSwipe"
            @pointerup="endSwipe"
            @pointercancel="cancelSwipe"
            @pointerleave="cancelSwipe"
        >
            <!-- A hidden lazy photo is not requested until it is shown. -->
            <div
                v-for="(photo, index) in photos"
                v-show="index === current"
                :key="photo.url"
                role="group"
                aria-roledescription="slide"
                :aria-label="`${index + 1} of ${count}`"
                class="size-full"
            >
                <img
                    :loading="index === 0 ? 'eager' : 'lazy'"
                    :src="photo.url"
                    :alt="`${formatRoom(photo.room)} of ${label}`"
                    draggable="false"
                    width="1200"
                    height="800"
                    class="size-full object-cover"
                />
                <span aria-hidden="true" class="absolute bottom-3 left-3 rounded bg-asphalt/85 px-2 py-0.5 text-sm font-semibold text-white">
                    {{ formatRoom(photo.room) }}
                </span>
            </div>
        </div>
        <span aria-hidden="true" class="absolute right-3 bottom-3 rounded bg-asphalt/85 px-2 py-0.5 text-sm font-semibold text-white tabular-nums">
            {{ current + 1 }} / {{ count }}
        </span>
    </section>
</template>
