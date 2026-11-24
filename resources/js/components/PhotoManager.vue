<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onUnmounted, ref, useId } from 'vue';

import { formatRoom } from '../format';
import { usePhotoOrder } from '../photoOrder';
import type { ListingPhoto } from '../types';
import PhotoCard from './PhotoCard.vue';

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
const relabel = (photo: ListingPhoto, room: string) => router.patch(`${base.value}/${photo.id}`, { room }, { preserveScroll: true });
const remove = (photo: ListingPhoto) =>
    router.delete(`${base.value}/${photo.id}`, {
        preserveScroll: true,
        // The card that had the focus is gone, and there is always room for another photo now.
        onSuccess: () => nextTick(() => document.getElementById(inputId)?.focus()),
    });

// With touch, dragging is how a page scrolls: there are buttons instead.
const canDrag = window.matchMedia('(pointer: fine)').matches;
const { shown, saving, error: orderError, sliding, dragged, move, startDrag, dragOver, drop, endDrag, edge } = usePhotoOrder(
    () => props.photos,
    () => base.value,
);
</script>

<template>
    <section :aria-labelledby="headingId" class="panel p-5 sm:p-6">
        <h2 :id="headingId" class="text-xl font-extrabold">Photos</h2>
        <p class="mt-1 text-gravel">Up to 5, JPG, PNG or WebP, 5&nbsp;MB each. The first one is the cover.</p>

        <p v-if="photos.length > 1" class="mt-4 text-sm text-gravel">
            {{ canDrag ? 'Drag the photos to change their order, or point at one to see its buttons.' : 'Use the buttons of a photo to change the order.' }}
        </p>
        <p v-if="orderError" role="alert" class="mt-2 text-sm font-bold text-stop">{{ orderError }}</p>
        <TransitionGroup v-if="shown.length" tag="ol" :name="sliding ? 'photo' : 'photo-still'" class="mt-4 grid items-start gap-4 sm:grid-cols-3">
            <PhotoCard
                v-for="(photo, index) in shown"
                :key="photo.id"
                :photo="photo"
                :index="index"
                :count="shown.length"
                :rooms="rooms"
                :saving="saving"
                :draggable="canDrag && shown.length > 1"
                :lifted="dragged === index"
                :dragging="dragged !== null"
                :edge="edge(index)"
                @move="(to, event, focus) => move(index, to, event, focus)"
                @relabel="relabel(photo, $event)"
                @remove="remove(photo)"
                @drag-start="startDrag(index, $event)"
                @drag-end="endDrag"
                @dragover="dragOver(index, $event)"
                @drop="drop(index, $event)"
            />
        </TransitionGroup>

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
