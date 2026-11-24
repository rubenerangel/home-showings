<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import ConfirmButton from '../../../components/ConfirmButton.vue';
import FormField from '../../../components/FormField.vue';
import HouseTime from '../../../components/HouseTime.vue';
import StreetSign from '../../../components/StreetSign.vue';
import { focusFirstError } from '../../../focus';
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
        onError: focusFirstError,
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
const cancel = (showing: Showing) => router.post(`/seller/showings/${showing.id}/cancel`, {}, { preserveScroll: true });
</script>

<template>
    <Head :title="`Schedule of ${listing.address}`" />
    <main class="page-narrow space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="min-w-0">
                <p class="mb-3 flex flex-wrap items-center gap-x-3 gap-y-1">
                    <StreetSign small>{{ listing.address }}</StreetSign>
                    <span class="wrap-anywhere">{{ listing.city }}, {{ listing.state }}</span>
                </p>
                <h1 class="title">Schedule</h1>
            </div>
            <div class="flex gap-3">
                <Link :href="`/seller/listings/${listing.id}/edit`" class="btn btn-small">Edit home</Link>
                <Link href="/seller" class="btn btn-small">Back to my homes</Link>
            </div>
        </div>

        <section v-for="group in groups.slice(0, 1)" :key="group.title" :aria-label="group.title" class="panel p-5 sm:p-6">
            <h2 class="text-xl font-extrabold">{{ group.title }}</h2>
            <ul v-if="group.showings.length" class="mt-2 divide-y divide-curb">
                <li v-for="showing in group.showings" :key="showing.id" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 py-4 last:pb-0">
                    <div>
                        <HouseTime :starts-at="showing.starts_at" :ends-at="showing.ends_at" :timezone="showing.listing.timezone" />
                        <p class="mt-1 flex flex-wrap items-center gap-2 wrap-anywhere">{{ showing.buyer_name }} <span class="badge badge-sign">{{ statusOf(showing) }}</span></p>
                    </div>
                    <ConfirmButton
                        v-if="showing.cancellable"
                        label="Cancel"
                        :subject="`the showing with ${showing.buyer_name}`"
                        question="Cancel it? This time will stay closed in its window."
                        confirm-label="Yes, cancel it"
                        keep-label="Keep it"
                        @confirm="cancel(showing)"
                    />
                </li>
            </ul>
            <p v-else class="mt-2 text-gravel">{{ group.empty }}</p>
        </section>

        <section aria-labelledby="windows-heading" class="panel p-5 sm:p-6">
            <h2 id="windows-heading" class="text-xl font-extrabold">Windows</h2>
            <p class="mt-1 text-gravel">
                When the home can be visited, in its own time: {{ formatTimezone(listing.timezone) }}. Each showing takes
                {{ listing.showing_duration_minutes }} minutes.
            </p>

            <form class="mt-5 border-b border-curb pb-6" novalidate @input="form.clearErrors()" @submit.prevent="open">
                <div class="grid gap-4 sm:grid-cols-3">
                    <FormField v-slot="{ control }" label="Date" name="date" :error="form.errors.date">
                        <input v-bind="control" v-model="form.date" type="date" :min="today" />
                    </FormField>
                    <FormField v-slot="{ control }" label="From" name="start" :error="form.errors.start">
                        <input v-bind="control" v-model="form.start" type="time" />
                    </FormField>
                    <FormField v-slot="{ control }" label="To" name="end" :error="form.errors.end">
                        <input v-bind="control" v-model="form.end" type="time" />
                    </FormField>
                </div>
                <div class="mt-5 flex flex-wrap items-center gap-x-4 gap-y-2">
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">Add window</button>
                    <p role="status" class="font-semibold" :class="preview?.fits === false ? 'text-stop' : 'text-sign-deep'">{{ preview?.text }}</p>
                </div>
            </form>

            <ul v-if="windows.length" class="relative -mr-3 max-h-112 divide-y divide-curb overflow-y-auto overscroll-contain pr-3">
                <li v-for="window in windows" :key="window.id" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 py-3.5 last:pb-0">
                    <div class="tabular-nums">
                        <p :class="window.past ? 'text-gravel' : 'font-bold'">
                            {{ formatWindow(window.date, window.start, window.end) }}<template v-if="window.past"> (past)</template>
                        </p>
                        <p class="mt-0.5 flex flex-wrap items-center gap-2 text-sm text-gravel">
                            {{ formatSlots(window.showings, window.unused_minutes) }}
                            <span v-if="window.active_showings" class="badge badge-caution">{{ window.active_showings }} booked, cancel to close</span>
                        </p>
                    </div>
                    <ConfirmButton
                        label="Close"
                        :subject="`the window on ${formatWindow(window.date, window.start, window.end)}`"
                        question="Close this window?"
                        confirm-label="Yes, close it"
                        keep-label="Keep it"
                        :disabled="window.active_showings > 0"
                        @confirm="close(window)"
                    />
                </li>
            </ul>
            <p v-else class="pt-5 text-gravel">No open windows, so buyers do not see this home.</p>
        </section>

        <section v-for="group in groups.slice(1)" :key="group.title" :aria-label="group.title" class="panel p-5 sm:p-6">
            <h2 class="text-xl font-extrabold">{{ group.title }}</h2>
            <ul v-if="group.showings.length" class="mt-2 divide-y divide-curb">
                <li v-for="showing in group.showings" :key="showing.id" class="py-4 last:pb-0">
                    <HouseTime :starts-at="showing.starts_at" :ends-at="showing.ends_at" :timezone="showing.listing.timezone" />
                    <p class="mt-1 flex flex-wrap items-center gap-2 wrap-anywhere">{{ showing.buyer_name }} <span class="badge">{{ statusOf(showing) }}</span></p>
                </li>
            </ul>
            <p v-else class="mt-2 text-gravel">{{ group.empty }}</p>
        </section>
    </main>
</template>
