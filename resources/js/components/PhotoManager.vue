<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, useId } from 'vue';

import { formatRoom } from '../format';
import type { Photo } from '../types';

const props = defineProps<{ listingId: number; photos: Photo[]; rooms: string[] }>();

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
const relabel = (photo: Photo, event: Event) =>
    router.patch(`${base.value}/${photo.id}`, { room: (event.target as HTMLSelectElement).value }, { preserveScroll: true });
const remove = (photo: Photo) => router.delete(`${base.value}/${photo.id}`, { preserveScroll: true });
</script>

<template>
    <section :aria-labelledby="headingId" class="card p-5">
        <h2 :id="headingId" class="font-medium">Photos</h2>
        <p class="mt-1 text-sm text-stone-600">Up to 5, JPG, PNG or WebP, 5 MB each. The first one is the cover.</p>

        <ol v-if="photos.length" class="mt-4 grid gap-4 sm:grid-cols-3">
            <li v-for="(photo, index) in photos" :key="photo.id" class="space-y-2">
                <div class="relative">
                    <img :src="photo.url" :alt="formatRoom(photo.room)" class="aspect-3/2 w-full rounded-md object-cover" />
                    <span v-if="index === 0" class="absolute top-2 left-2 rounded bg-teal-900 px-2 py-0.5 text-xs font-medium text-white">Cover</span>
                </div>
                <select :value="photo.room" :aria-label="`Room of photo ${index + 1}`" class="input py-1" @change="relabel(photo, $event)">
                    <option v-for="room in rooms" :key="room" :value="room">{{ formatRoom(room) }}</option>
                </select>
                <button type="button" class="btn w-full py-1" @click="remove(photo)">Remove<span class="sr-only"> photo {{ index + 1 }}</span></button>
            </li>
        </ol>

        <form class="mt-4" novalidate @submit.prevent="upload">
            <template v-if="free > 0">
                <label :for="inputId" class="mb-1 block text-sm font-medium">Add photos</label>
                <input
                    :id="inputId"
                    type="file"
                    multiple
                    :accept="types.join(',')"
                    :disabled="form.processing"
                    class="input file:mr-3 file:cursor-pointer file:rounded file:border-0 file:bg-stone-200 file:px-3 file:py-1 file:text-sm"
                    @change="choose"
                />
            </template>
            <p v-else class="text-sm text-stone-600">That is the limit. Remove a photo to add another one.</p>

            <div role="alert" class="text-sm text-red-700">
                <p v-for="problem in problems" :key="problem" class="mt-1">{{ problem }}</p>
                <p v-if="form.errors.photos" class="mt-1">{{ form.errors.photos }}</p>
            </div>

            <ul v-if="form.photos.length" class="mt-4 space-y-3">
                <li v-for="(photo, index) in form.photos" :key="previewOf(photo.file)" class="flex flex-wrap items-center gap-3">
                    <img :src="previewOf(photo.file)" alt="" class="h-16 w-24 rounded-md object-cover" />
                    <span class="min-w-0 flex-1 truncate text-sm">{{ photo.file.name }}</span>
                    <select v-model="photo.room" :aria-label="`Room of ${photo.file.name}`" class="input w-auto py-1">
                        <option v-for="room in rooms" :key="room" :value="room">{{ formatRoom(room) }}</option>
                    </select>
                    <button type="button" class="btn py-1" @click="discard(index)">Discard<span class="sr-only"> {{ photo.file.name }}</span></button>
                    <p v-if="fileError(index)" class="w-full text-sm text-red-700">{{ fileError(index) }}</p>
                </li>
            </ul>
            <div v-if="form.photos.length" class="mt-4 flex items-center gap-3">
                <button type="submit" class="btn btn-primary" :disabled="form.processing">Upload {{ form.photos.length === 1 ? '1 photo' : `${form.photos.length} photos` }}</button>
                <progress v-if="form.progress" :value="form.progress.percentage" max="100" aria-label="Upload progress" class="flex-1">{{ form.progress.percentage }}%</progress>
            </div>
        </form>
    </section>
</template>
