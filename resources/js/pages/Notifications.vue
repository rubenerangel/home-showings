<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import HouseTime from '../components/HouseTime.vue';
import PushToggle from '../components/PushToggle.vue';
import type { ShowingNotification } from '../types';

defineProps<{ notifications: ShowingNotification[]; pushPublicKey: string | null }>();

const page = usePage();
const hasUnread = computed(() => (page.props.unreadNotifications ?? 0) > 0);
const readAll = () => router.post(`/${page.props.user!.role}/notifications/read`, {}, { preserveScroll: true });

const actions: Record<ShowingNotification['kind'], string> = {
    booked: 'booked a showing',
    cancelled_by_buyer: 'cancelled a showing',
    cancelled_by_seller: 'cancelled your showing',
};

const received = new Intl.DateTimeFormat('en-US', { dateStyle: 'medium', timeStyle: 'short' });
</script>

<template>
    <Head title="Notifications" />
    <main class="mx-auto max-w-3xl px-4 py-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold tracking-tight">Notifications</h1>
            <button v-if="hasUnread" type="button" class="btn" @click="readAll">Mark all as read</button>
        </div>
        <PushToggle v-if="pushPublicKey" :public-key="pushPublicKey" class="mt-6" />
        <ul v-if="notifications.length" class="card mt-6 divide-y divide-stone-200">
            <li v-for="notification in notifications" :key="notification.id" class="px-5 py-4 text-sm" :class="{ 'bg-teal-50': !notification.read }">
                <p>
                    <span class="font-medium">{{ notification.name }}</span> {{ actions[notification.kind] }} at {{ notification.address }}
                    <span v-if="!notification.read" class="ml-1 rounded-full bg-teal-800 px-2 py-0.5 text-xs font-semibold text-white">New</span>
                </p>
                <HouseTime :starts-at="notification.starts_at" :ends-at="notification.ends_at" :timezone="notification.timezone" class="mt-1" />
                <p class="mt-1 text-xs text-stone-600">Received {{ received.format(new Date(notification.created_at)) }}</p>
            </li>
        </ul>
        <p v-else class="card mt-6 p-8 text-center text-stone-600">Nothing yet. Bookings and cancellations appear here.</p>
    </main>
</template>
