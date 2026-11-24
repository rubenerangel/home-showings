<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';

import FormField from '../../components/FormField.vue';

interface Range {
    id: number;
    date: string;
    start: string;
    end: string;
}

defineProps<{ ranges: Range[] }>();

const form = useForm({ date: '', start: '', end: '' });

const add = () => form.post('/buyer/availability', { preserveScroll: true, onSuccess: () => form.reset() });
const remove = (range: Range) => router.delete(`/buyer/availability/${range.id}`, { preserveScroll: true });

// Local to whichever home it is compared with: no zone is involved, so UTC only serves to format.
const format = (range: Range) => {
    const at = (time: string) => new Date(`${range.date}T${time}:00Z`);
    const date = new Intl.DateTimeFormat('en-US', { timeZone: 'UTC', weekday: 'short', month: 'short', day: 'numeric' });
    const time = new Intl.DateTimeFormat('en-US', { timeZone: 'UTC', hour: 'numeric', minute: '2-digit' });

    return { date: date.format(at(range.start)), time: time.formatRange(at(range.start), at(range.end)) };
};
</script>

<template>
    <Head title="Availability" />
    <main class="mx-auto max-w-3xl px-4 py-8">
        <h1 class="text-2xl font-semibold tracking-tight">Availability</h1>
        <p class="mt-1 text-sm text-stone-600">When you can visit homes.</p>

        <form class="card mt-6 p-5" novalidate @submit.prevent="add">
            <p class="mb-4 text-sm text-stone-600">Times are local to each home.</p>
            <div class="grid gap-4 sm:grid-cols-3">
                <FormField v-slot="{ control }" label="Date" :error="form.errors.date">
                    <input v-bind="control" v-model="form.date" type="date" />
                </FormField>
                <FormField v-slot="{ control }" label="From" :error="form.errors.start">
                    <input v-bind="control" v-model="form.start" type="time" />
                </FormField>
                <FormField v-slot="{ control }" label="To" :error="form.errors.end">
                    <input v-bind="control" v-model="form.end" type="time" />
                </FormField>
            </div>
            <button type="submit" class="btn btn-primary mt-4" :disabled="form.processing">Add availability</button>
        </form>

        <ul v-if="ranges.length" class="card mt-6 divide-y divide-stone-200">
            <li v-for="range in ranges" :key="range.id" class="flex items-center justify-between gap-4 px-5 py-3 text-sm">
                <span>
                    <span class="font-medium">{{ format(range).date }}</span> · {{ format(range).time }}
                </span>
                <button type="button" class="btn py-1" @click="remove(range)">
                    Remove<span class="sr-only"> {{ format(range).date }} {{ format(range).time }}</span>
                </button>
            </li>
        </ul>
        <p v-else class="card mt-6 p-8 text-center text-stone-600">You have not added any availability yet.</p>
    </main>
</template>
