<script setup lang="ts">
import { nextTick, ref } from 'vue';

import { formatRoom } from '../format';
import type { ListingPhoto } from '../types';

defineProps<{
    photo: ListingPhoto;
    index: number;
    count: number;
    rooms: string[];
    saving: boolean;
    draggable: boolean;
    /** This photo is the one being dragged. */
    lifted: boolean;
    /** Some photo is being dragged, so the buttons step aside. */
    dragging: boolean;
    edge?: 'before' | 'after';
}>();

const emit = defineEmits<{
    move: [to: number, event: MouseEvent, focus: string];
    relabel: [room: string];
    remove: [];
    dragStart: [event: DragEvent];
    dragEnd: [];
}>();

// Removing asks first, over the photo, so nothing else moves.
const asking = ref(false);
const keepButton = ref<HTMLButtonElement>();
const removeButton = ref<HTMLButtonElement>();
const ask = async () => {
    asking.value = true;
    await nextTick();
    keepButton.value?.focus();
};
const keep = async () => {
    asking.value = false;
    await nextTick();
    removeButton.value?.focus();
};
</script>

<template>
    <li class="photo-card" :data-photo="photo.id">
        <span
            v-if="edge"
            class="pointer-events-none absolute inset-y-0 z-10 w-1 rounded bg-sign"
            :class="edge === 'before' ? '-left-2.5' : '-right-2.5'"
        />
        <div class="relative overflow-clip rounded-t-[9px]">
            <div
                :class="{ 'cursor-grab active:cursor-grabbing': draggable, 'opacity-40': lifted }"
                :draggable="draggable"
                @dragstart="emit('dragStart', $event)"
                @dragend="emit('dragEnd')"
            >
                <img :src="photo.url" :alt="formatRoom(photo.room)" draggable="false" width="1200" height="800" class="aspect-3/2 w-full object-cover" />
            </div>
            <span v-if="index === 0" class="badge badge-caution pointer-events-none absolute top-2 left-2">Cover</span>
            <div v-show="!dragging && !asking" class="photo-controls">
                <button
                    v-if="index > 0"
                    type="button"
                    class="photo-control top-2 left-2"
                    title="Make cover"
                    :aria-label="`Make photo ${index + 1} the cover`"
                    :disabled="saving"
                    @click="emit('move', 0, $event, 'select')"
                >
                    <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 3.5l2.6 5.4 5.9.8-4.3 4.1 1 5.9L12 16.9l-5.2 2.8 1-5.9-4.3-4.1 5.9-.8z" />
                    </svg>
                </button>
                <button ref="removeButton" type="button" class="photo-control top-2 right-2" title="Remove" :aria-label="`Remove photo ${index + 1}`" @click="ask">
                    <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M4 7h16M9 7V4.5h6V7M6.5 7l.8 12.5h9.4L17.5 7M10 11v5M14 11v5" />
                    </svg>
                </button>
                <button
                    v-if="index > 0"
                    type="button"
                    class="photo-control bottom-2 left-2"
                    data-move="earlier"
                    title="Move earlier"
                    :aria-label="`Move photo ${index + 1} earlier`"
                    :disabled="saving"
                    @click="emit('move', index - 1, $event, '[data-move=earlier]:enabled')"
                >
                    <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M15 5l-7 7 7 7" />
                    </svg>
                </button>
                <button
                    v-if="index < count - 1"
                    type="button"
                    class="photo-control right-2 bottom-2"
                    data-move="later"
                    title="Move later"
                    :aria-label="`Move photo ${index + 1} later`"
                    :disabled="saving"
                    @click="emit('move', index + 1, $event, '[data-move=later]:enabled')"
                >
                    <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>
            <Transition name="fade">
                <div
                    v-if="asking"
                    role="group"
                    :aria-label="`Remove photo ${index + 1}?`"
                    class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-asphalt/80 p-2"
                    @keydown.esc="keep"
                >
                    <p class="font-bold text-white">Remove this photo?</p>
                    <div class="flex flex-wrap justify-center gap-2">
                        <button type="button" class="btn btn-small btn-stop" @click="emit('remove')">Yes, remove it</button>
                        <button ref="keepButton" type="button" class="btn btn-small" @click="keep">Keep it</button>
                    </div>
                </div>
            </Transition>
        </div>
        <select
            :value="photo.room"
            :aria-label="`Room of photo ${index + 1}`"
            class="photo-room"
            @change="emit('relabel', ($event.target as HTMLSelectElement).value)"
        >
            <option v-for="room in rooms" :key="room" :value="room">{{ formatRoom(room) }}</option>
        </select>
    </li>
</template>
