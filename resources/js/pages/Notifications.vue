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
    <main class="page-narrow">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="title">Notifications</h1>
            <button v-if="hasUnread" type="button" class="btn btn-small" @click="readAll">Mark all as read</button>
        </div>
        <PushToggle v-if="pushPublicKey" :public-key="pushPublicKey" class="mt-6" />
        <ul v-if="notifications.length" class="panel mt-6 divide-y divide-curb overflow-clip">
            <li v-for="notification in notifications" :key="notification.id" class="px-5 py-4 sm:px-6" :class="{ 'shadow-[inset_5px_0_0_var(--color-caution)]': !notification.read }">
                <p>
                    <span class="font-extrabold">{{ notification.name }}</span> {{ actions[notification.kind] }} at {{ notification.address }}
                    <span v-if="!notification.read" class="badge badge-caution ml-1">New</span>
                </p>
                <HouseTime :starts-at="notification.starts_at" :ends-at="notification.ends_at" :timezone="notification.timezone" class="mt-1" />
                <p class="mt-1 text-sm text-gravel">Received {{ received.format(new Date(notification.created_at)) }}</p>
            </li>
        </ul>
        <p v-else class="panel mt-6 px-6 py-10 text-center text-gravel">Nothing yet. Bookings and cancellations appear here.</p>
    </main>
</template>
