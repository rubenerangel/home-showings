<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import HouseTime from '../../components/HouseTime.vue';
import type { BuyerShowing } from '../../types';

const props = defineProps<{ showings: BuyerShowing[] }>();

const groups = computed(() => [
    { title: 'Upcoming', showings: props.showings.filter((showing) => showing.upcoming) },
    { title: 'Past and cancelled', showings: props.showings.filter((showing) => !showing.upcoming).reverse() },
]);

const statuses: Record<BuyerShowing['status'], string> = {
    confirmed: 'Confirmed',
    cancelled_by_buyer: 'Cancelled by you',
    cancelled_by_seller: 'Cancelled by the seller',
};

const confirming = ref<number | null>(null);
const cancel = (showing: BuyerShowing) =>
    router.post(`/buyer/showings/${showing.id}/cancel`, {}, { preserveScroll: true, onFinish: () => (confirming.value = null) });
</script>

<template>
    <Head title="My showings" />
    <main class="mx-auto max-w-3xl px-4 py-8">
        <h1 class="text-2xl font-semibold tracking-tight">My showings</h1>
        <div v-if="!showings.length" class="card mt-6 p-8 text-center">
            <p class="text-stone-600">You have no showings yet.</p>
            <Link href="/buyer" class="btn btn-primary mt-4">Find homes</Link>
        </div>
        <template v-for="group in groups" :key="group.title">
            <section v-if="group.showings.length" class="mt-6">
                <h2 class="font-medium">{{ group.title }}</h2>
                <ul class="card mt-2 divide-y divide-stone-200">
                    <li v-for="showing in group.showings" :key="showing.id" class="flex flex-wrap items-center justify-between gap-4 px-5 py-4 text-sm">
                        <div>
                            <p class="font-medium">{{ showing.listing.address }}, {{ showing.listing.city }}, {{ showing.listing.state }}</p>
                            <HouseTime :starts-at="showing.starts_at" :ends-at="showing.ends_at" :timezone="showing.listing.timezone" />
                            <p class="mt-1 text-stone-600">{{ statuses[showing.status] }}</p>
                        </div>
                        <div v-if="showing.cancellable" class="flex gap-2">
                            <template v-if="confirming === showing.id">
                                <button type="button" class="btn border-red-700 py-1 text-red-800" @click="cancel(showing)">Yes, cancel it</button>
                                <button type="button" class="btn py-1" @click="confirming = null">Keep it</button>
                            </template>
                            <button v-else type="button" class="btn py-1" @click="confirming = showing.id">
                                Cancel<span class="sr-only"> the showing at {{ showing.listing.address }}</span>
                            </button>
                        </div>
                    </li>
                </ul>
            </section>
        </template>
    </main>
</template>
