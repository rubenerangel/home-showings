<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';

import FormField from '../../components/FormField.vue';
import { focusFirstError } from '../../focus';

interface Range {
    id: number;
    date: string;
    start: string;
    end: string;
}

defineProps<{ ranges: Range[] }>();

const form = useForm({ date: '', start: '', end: '' });

const add = () => form.post('/buyer/availability', { preserveScroll: true, onSuccess: () => form.reset(), onError: focusFirstError });
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
    <main class="page-narrow">
        <h1 class="title">Availability</h1>
        <p class="lead">When you can visit homes. Times are local to each home.</p>

        <form class="panel mt-8 p-5 sm:p-6" novalidate @input="form.clearErrors()" @submit.prevent="add">
            <div class="grid gap-4 sm:grid-cols-3">
                <FormField v-slot="{ control }" label="Date" name="date" :error="form.errors.date">
                    <input v-bind="control" v-model="form.date" type="date" />
                </FormField>
                <FormField v-slot="{ control }" label="From" name="start" :error="form.errors.start">
                    <input v-bind="control" v-model="form.start" type="time" />
                </FormField>
                <FormField v-slot="{ control }" label="To" name="end" :error="form.errors.end">
                    <input v-bind="control" v-model="form.end" type="time" />
                </FormField>
            </div>
            <button type="submit" class="btn btn-primary mt-5" :disabled="form.processing">Add availability</button>
        </form>

        <ul v-if="ranges.length" class="panel mt-6 divide-y divide-curb">
            <li v-for="range in ranges" :key="range.id" class="flex items-center justify-between gap-4 px-5 py-3 tabular-nums sm:px-6">
                <span>
                    <span class="inline-block font-extrabold sm:w-32">{{ format(range).date }}</span> <span class="inline-block">{{ format(range).time }}</span>
                </span>
                <button type="button" class="btn btn-small" @click="remove(range)">
                    Remove<span class="sr-only"> {{ format(range).date }} {{ format(range).time }}</span>
                </button>
            </li>
        </ul>
        <p v-else class="panel mt-6 px-6 py-10 text-center text-gravel">You have not added any availability yet.</p>
    </main>
</template>
