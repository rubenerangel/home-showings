<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import FormField from '../../../components/FormField.vue';
import HouseTime from '../../../components/HouseTime.vue';
import { formatTimezone } from '../../../format';
import { formatSlots, formatWindow, windowPreview } from '../../../showingWindow';

interface Window {
    id: number;
    date: string;
    start: string;
    end: string;
    showings: number;
    unused_minutes: number;
    active_showings: number;
    past: boolean;
}

interface Showing {
    id: number;
    buyer_name: string;
    listing: { address: string; city: string; state: string; timezone: string };
    starts_at: string;
    ends_at: string;
    status: 'confirmed' | 'cancelled_by_buyer' | 'cancelled_by_seller';
    upcoming: boolean;
    cancellable: boolean;
}

const props = defineProps<{
    listing: { id: number; address: string; city: string; state: string; timezone: string; showing_duration_minutes: number };
    today: string;
    windows: Window[];
    showings: Showing[];
}>();

const form = useForm({ date: '', start: '', end: '' });
const preview = computed(() => windowPreview(form.start, form.end, props.listing.showing_duration_minutes));
const open = () =>
    form.post(`/seller/listings/${props.listing.id}/windows`, {
        preserveScroll: true,
        // The date stays, to add another window on the same day.
        onSuccess: () => form.reset('start', 'end'),
    });

const groups = computed(() => [
    { title: 'Upcoming showings', empty: 'No upcoming showings.', showings: props.showings.filter((showing) => showing.upcoming) },
    { title: 'Past and cancelled showings', empty: 'Nothing here yet.', showings: props.showings.filter((showing) => !showing.upcoming) },
]);

const statusOf = (showing: Showing) => {
    if (showing.status === 'cancelled_by_buyer') return 'Cancelled by the buyer';
    if (showing.status === 'cancelled_by_seller') return 'Cancelled by you';
    if (!showing.upcoming) return 'Done';
    return showing.cancellable ? 'Confirmed' : 'In progress';
};

const close = (window: Window) => router.post(`/seller/windows/${window.id}/close`, {}, { preserveScroll: true });
const cancel = (showing: Showing) => {
    if (confirm(`Cancel the showing with ${showing.buyer_name}? This time will stay closed in its window.`)) {
        router.post(`/seller/showings/${showing.id}/cancel`, {}, { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="`Schedule of ${listing.address}`" />
    <main class="mx-auto max-w-3xl space-y-6 px-4 py-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Schedule</h1>
                <p class="text-stone-600">{{ listing.address }}, {{ listing.city }}, {{ listing.state }}</p>
            </div>
            <div class="flex gap-3">
                <Link :href="`/seller/listings/${listing.id}/edit`" class="btn">Edit home</Link>
                <Link href="/seller" class="btn">Back to my homes</Link>
            </div>
        </div>

        <section aria-labelledby="windows-heading" class="card p-5">
            <h2 id="windows-heading" class="font-medium">Windows</h2>
            <p class="mt-1 text-sm text-stone-600">
                When the home can be visited, in its own time: {{ formatTimezone(listing.timezone) }}. Each showing takes
                {{ listing.showing_duration_minutes }} minutes.
            </p>

            <form class="mt-4 border-b border-stone-200 pb-5" novalidate @submit.prevent="open">
                <div class="grid gap-4 sm:grid-cols-3">
                    <FormField v-slot="{ control }" label="Date" :error="form.errors.date">
                        <input v-bind="control" v-model="form.date" type="date" :min="today" />
                    </FormField>
                    <FormField v-slot="{ control }" label="From" :error="form.errors.start">
                        <input v-bind="control" v-model="form.start" type="time" />
                    </FormField>
                    <FormField v-slot="{ control }" label="To" :error="form.errors.end">
                        <input v-bind="control" v-model="form.end" type="time" />
                    </FormField>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2">
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">Add window</button>
                    <p role="status" class="text-sm" :class="preview?.fits === false ? 'text-red-700' : 'text-stone-600'">{{ preview?.text }}</p>
                </div>
            </form>

            <ul v-if="windows.length" class="relative max-h-96 divide-y divide-stone-200 overflow-y-auto">
                <li v-for="window in windows" :key="window.id" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 py-3 last:pb-0">
                    <div class="text-sm">
                        <p :class="window.past ? 'text-stone-600' : 'font-medium'">
                            {{ formatWindow(window.date, window.start, window.end) }}<template v-if="window.past"> (past)</template>
                        </p>
                        <p class="text-stone-600">
                            {{ formatSlots(window.showings, window.unused_minutes) }}<template v-if="window.active_showings"> · {{ window.active_showings }} booked: cancel to close</template>
                        </p>
                    </div>
                    <button type="button" class="btn py-1" :disabled="window.active_showings > 0" @click="close(window)">
                        Close<span class="sr-only"> the window on {{ formatWindow(window.date, window.start, window.end) }}</span>
                    </button>
                </li>
            </ul>
            <p v-else class="pt-4 text-sm text-stone-600">No open windows, so buyers do not see this home.</p>
        </section>

        <section v-for="group in groups" :key="group.title" :aria-label="group.title" class="card p-5">
            <h2 class="font-medium">{{ group.title }}</h2>
            <ul v-if="group.showings.length" class="mt-2 divide-y divide-stone-200">
                <li v-for="showing in group.showings" :key="showing.id" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 py-3 last:pb-0">
                    <div>
                        <HouseTime :starts-at="showing.starts_at" :ends-at="showing.ends_at" :timezone="showing.listing.timezone" />
                        <p class="text-sm text-stone-600">{{ showing.buyer_name }} · {{ statusOf(showing) }}</p>
                    </div>
                    <button v-if="showing.cancellable" type="button" class="btn py-1" @click="cancel(showing)">
                        Cancel<span class="sr-only"> the showing with {{ showing.buyer_name }}</span>
                    </button>
                </li>
            </ul>
            <p v-else class="mt-2 text-sm text-stone-600">{{ group.empty }}</p>
        </section>
    </main>
</template>
