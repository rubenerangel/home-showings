<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onUnmounted, ref, useId } from 'vue';

import { formatRoom } from '../format';
import type { ListingPhoto } from '../types';
import ConfirmButton from './ConfirmButton.vue';

const props = defineProps<{ listingId: number; photos: ListingPhoto[]; rooms: string[] }>();

const maxPhotos = 5;
const maxBytes = 5 * 1024 * 1024;
const types = ['image/jpeg', 'image/png', 'image/webp'];

const headingId = useId();
const inputId = useId();
const base = computed(() => `/seller/listings/${props.listingId}/photos`);

const form = useForm<{ photos: { file: File; room: string }[] }>({ photos: [] });
const previews = new Map<File, string>();
const problems = ref<string[]>([]);
const free = computed(() => maxPhotos - props.photos.length - form.photos.length);

const previewOf = (file: File) => {
    if (!previews.has(file)) previews.set(file, URL.createObjectURL(file));
    return previews.get(file);
};
const releasePreviews = () => {
    previews.forEach((url) => URL.revokeObjectURL(url));
    previews.clear();
};
onUnmounted(releasePreviews);

// The server checks all of this again: here it only avoids a useless upload.
const choose = (event: Event) => {
    const input = event.target as HTMLInputElement;
    problems.value = [];
    form.clearErrors();
    for (const file of input.files ?? []) {
        if (!types.includes(file.type)) problems.value.push(`${file.name}: photos must be JPG, PNG or WebP.`);
        else if (file.size > maxBytes) problems.value.push(`${file.name}: each photo can be up to 5 MB.`);
        else if (free.value === 0) problems.value.push(`${file.name}: a home can have up to 5 photos.`);
        else form.photos.push({ file, room: props.rooms[0] });
    }
    input.value = '';
};

const discard = (index: number) => {
    form.clearErrors();
    form.photos.splice(index, 1);
};

const upload = () =>
    form.post(base.value, {
        preserveScroll: true,
        onSuccess: () => {
            form.photos = [];
            releasePreviews();
        },
    });

const fileError = (index: number) => form.errors[`photos.${index}.file`] ?? form.errors[`photos.${index}.room`];
const relabel = (photo: ListingPhoto, event: Event) =>
    router.patch(`${base.value}/${photo.id}`, { room: (event.target as HTMLSelectElement).value }, { preserveScroll: true });
const remove = (photo: ListingPhoto) => router.delete(`${base.value}/${photo.id}`, { preserveScroll: true });

// The order of the photos. Dragging is for a mouse: with touch or a keyboard there are buttons.
const canDrag = window.matchMedia('(pointer: fine)').matches;
const dragged = ref<number | null>(null);
const over = ref<number | null>(null);
const saving = ref(false);
const orderError = ref<string>();

const focusMoved = async (id: number, selector: string) => {
    await nextTick();
    const item = document.querySelector(`[data-photo="${id}"]`);
    (item?.querySelector<HTMLElement>(selector) ?? item?.querySelector<HTMLElement>('select'))?.focus();
};

const move = (from: number, to: number, focus?: string) => {
    if (saving.value || from === to) return;
    const ids = props.photos.map((photo) => photo.id);
    const [id] = ids.splice(from, 1);
    ids.splice(to, 0, id);

    let moved = false;
    saving.value = true;
    orderError.value = undefined;
    router.put(
        `${base.value}/order`,
        { photos: ids },
        {
            preserveScroll: true,
            onError: (errors) => (orderError.value = errors.photos ?? Object.values(errors)[0]),
            onSuccess: () => {
                moved = true;
            },
            // The buttons are disabled until then, and cannot take the focus.
            onFinish: () => {
                saving.value = false;
                if (moved && focus) void focusMoved(id, focus);
            },
        },
    );
};

const dragStart = (index: number, event: DragEvent) => {
    if (saving.value) return event.preventDefault();
    dragged.value = index;
    event.dataTransfer!.effectAllowed = 'move';
    // Firefox starts a drag only if there is data.
    event.dataTransfer!.setData('text/plain', '');
};
const dragOver = (index: number, event: DragEvent) => {
    // Anything that did not start here is not accepted.
    if (dragged.value === null) return;
    event.preventDefault();
    over.value = index;
};
const drop = (index: number, event: DragEvent) => {
    if (dragged.value === null) return;
    event.preventDefault();
    move(dragged.value, index);
    dragEnd();
};
const dragEnd = () => {
    dragged.value = null;
    over.value = null;
};
/** Where the dragged photo would be, to mark the edge it goes to. */
const marker = (index: number) => {
    if (dragged.value === null || over.value !== index || dragged.value === index) return undefined;
    return index < dragged.value ? 'before' : 'after';
};
</script>

<template>
    <section :aria-labelledby="headingId" class="panel p-5 sm:p-6">
        <h2 :id="headingId" class="text-xl font-extrabold">Photos</h2>
        <p class="mt-1 text-gravel">Up to 5, JPG, PNG or WebP, 5&nbsp;MB each. The first one is the cover.</p>

        <p v-if="photos.length > 1" class="mt-4 text-sm text-gravel">
            {{ canDrag ? 'Drag a photo to change the order, or use its buttons.' : 'Use the buttons of a photo to change the order.' }}
        </p>
        <p v-if="orderError" role="alert" class="mt-2 text-sm font-bold text-stop">{{ orderError }}</p>
        <ol v-if="photos.length" class="mt-4 grid gap-4 sm:grid-cols-3">
            <li
                v-for="(photo, index) in photos"
                :key="photo.id"
                :data-photo="photo.id"
                class="relative space-y-2"
                @dragover="dragOver(index, $event)"
                @drop="drop(index, $event)"
            >
                <span
                    v-if="marker(index)"
                    class="pointer-events-none absolute inset-y-0 z-10 w-1 rounded bg-sign"
                    :class="marker(index) === 'before' ? '-left-2.5' : '-right-2.5'"
                />
                <div
                    class="relative"
                    :class="{ 'cursor-grab': canDrag && photos.length > 1, 'opacity-40': dragged === index }"
                    :draggable="canDrag && photos.length > 1"
                    @dragstart="dragStart(index, $event)"
                    @dragend="dragEnd"
                >
                    <img :src="photo.url" :alt="formatRoom(photo.room)" draggable="false" width="1200" height="800" class="aspect-3/2 w-full rounded-md object-cover" />
                    <span v-if="index === 0" class="badge badge-caution absolute top-2 left-2">Cover</span>
                </div>
                <select :value="photo.room" :aria-label="`Room of photo ${index + 1}`" class="input" @change="relabel(photo, $event)">
                    <option v-for="room in rooms" :key="room" :value="room">{{ formatRoom(room) }}</option>
                </select>
                <div v-if="photos.length > 1" class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="btn btn-small px-3"
                        data-move="earlier"
                        :aria-label="`Move photo ${index + 1} earlier`"
                        :disabled="saving || index === 0"
                        @click="move(index, index - 1, '[data-move=earlier]:enabled')"
                    >
                        <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M15 5l-7 7 7 7" /></svg>
                    </button>
                    <button
                        type="button"
                        class="btn btn-small px-3"
                        data-move="later"
                        :aria-label="`Move photo ${index + 1} later`"
                        :disabled="saving || index === photos.length - 1"
                        @click="move(index, index + 1, '[data-move=later]:enabled')"
                    >
                        <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M9 5l7 7-7 7" /></svg>
                    </button>
                    <button v-if="index > 0" type="button" class="btn btn-small" :disabled="saving" @click="move(index, 0, 'select')">
                        Make cover<span class="sr-only">, photo {{ index + 1 }}</span>
                    </button>
                </div>
                <ConfirmButton
                    label="Remove"
                    :subject="`photo ${index + 1}`"
                    question="Remove this photo?"
                    confirm-label="Yes, remove it"
                    keep-label="Keep it"
                    @confirm="remove(photo)"
                />
            </li>
        </ol>

        <form class="mt-4" novalidate @submit.prevent="upload">
            <template v-if="free > 0">
                <label :for="inputId" class="mb-1.5 block text-[0.9375rem] font-bold">Add photos</label>
                <input
                    :id="inputId"
                    type="file"
                    multiple
                    :accept="types.join(',')"
                    :disabled="form.processing"
                    class="input py-2 file:mr-3 file:cursor-pointer file:rounded file:border-0 file:bg-mist file:px-3 file:py-1 file:text-sm file:font-bold"
                    @change="choose"
                />
            </template>
            <p v-else class="text-gravel">That is the limit. Remove a photo to add another one.</p>

            <div role="alert" class="text-sm font-bold text-stop">
                <p v-for="problem in problems" :key="problem" class="mt-1">{{ problem }}</p>
                <p v-if="form.errors.photos" class="mt-1">{{ form.errors.photos }}</p>
            </div>

            <ul v-if="form.photos.length" class="mt-4 space-y-3">
                <li v-for="(photo, index) in form.photos" :key="previewOf(photo.file)" class="flex flex-wrap items-center gap-3">
                    <img :src="previewOf(photo.file)" alt="" width="96" height="64" class="h-16 w-24 rounded-md object-cover" />
                    <span class="min-w-0 flex-1 truncate text-sm">{{ photo.file.name }}</span>
                    <select v-model="photo.room" :aria-label="`Room of ${photo.file.name}`" class="input w-auto">
                        <option v-for="room in rooms" :key="room" :value="room">{{ formatRoom(room) }}</option>
                    </select>
                    <button type="button" class="btn btn-small" @click="discard(index)">Discard<span class="sr-only"> {{ photo.file.name }}</span></button>
                    <p v-if="fileError(index)" class="w-full text-sm font-bold text-stop">{{ fileError(index) }}</p>
                </li>
            </ul>
            <div v-if="form.photos.length" class="mt-4 flex items-center gap-3">
                <button type="submit" class="btn btn-primary" :disabled="form.processing">Upload {{ form.photos.length === 1 ? '1 photo' : `${form.photos.length} photos` }}</button>
                <progress v-if="form.progress" :value="form.progress.percentage" max="100" aria-label="Upload progress" class="flex-1">{{ form.progress.percentage }}%</progress>
            </div>
        </form>
    </section>
</template>
