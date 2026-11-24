<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

import ConfirmButton from '../../components/ConfirmButton.vue';
import HouseTime from '../../components/HouseTime.vue';
import StreetSign from '../../components/StreetSign.vue';
import type { BuyerShowing } from '../../types';

const props = defineProps<{ showings: BuyerShowing[] }>();

const groups = computed(() => [
    { title: 'Upcoming', showings: props.showings.filter((showing) => showing.upcoming) },
    { title: 'Past and cancelled', showings: props.showings.filter((showing) => !showing.upcoming).reverse() },
]);

const statusOf = (showing: BuyerShowing) => {
    if (showing.status === 'cancelled_by_buyer') return 'Cancelled by you';
    if (showing.status === 'cancelled_by_seller') return 'Cancelled by the seller';
    if (!showing.upcoming) return 'Done';
    return showing.cancellable ? 'Confirmed' : 'In progress';
};

const cancel = (showing: BuyerShowing) => router.post(`/buyer/showings/${showing.id}/cancel`, {}, { preserveScroll: true });
</script>

<template>
    <Head title="My showings" />
    <main class="page-narrow">
        <h1 class="title">My showings</h1>
        <div v-if="!showings.length" class="panel mt-8 px-6 py-12 text-center">
            <p class="text-lg font-bold">You have no showings yet.</p>
            <Link href="/buyer" class="btn btn-primary mt-5">Find homes</Link>
        </div>
        <template v-for="group in groups" :key="group.title">
            <section v-if="group.showings.length" class="mt-8">
                <h2 class="text-xl font-extrabold">{{ group.title }}</h2>
                <ul class="panel mt-3 divide-y divide-curb">
                    <li v-for="showing in group.showings" :key="showing.id" class="flex flex-wrap items-center justify-between gap-4 px-5 py-5 sm:px-6">
                        <div class="min-w-0" :class="{ 'opacity-75': !showing.upcoming }">
                            <p class="mb-2.5 flex flex-wrap items-center gap-x-3 gap-y-1">
                                <StreetSign small>{{ showing.listing.address }}</StreetSign>
                                <span class="wrap-anywhere">{{ showing.listing.city }}, {{ showing.listing.state }}</span>
                            </p>
                            <HouseTime :starts-at="showing.starts_at" :ends-at="showing.ends_at" :timezone="showing.listing.timezone" />
                            <span class="badge mt-2" :class="{ 'badge-sign': showing.upcoming }">{{ statusOf(showing) }}</span>
                        </div>
                        <ConfirmButton
                            v-if="showing.cancellable"
                            label="Cancel"
                            :subject="`the showing at ${showing.listing.address}`"
                            question="Cancel this showing?"
                            confirm-label="Yes, cancel it"
                            keep-label="Keep it"
                            @confirm="cancel(showing)"
                        />
                    </li>
                </ul>
            </section>
        </template>
    </main>
</template>
