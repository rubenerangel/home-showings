<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import type { ShowingNotification } from '../types';

defineProps<{ notifications: ShowingNotification[] }>();
</script>

<template>
    <Head title="Notifications" />
    <main class="mx-auto max-w-3xl p-8">
        <h1 class="text-2xl font-semibold">Notifications</h1>
        <ul class="mt-4 space-y-2">
            <li v-for="notification in notifications" :key="notification.id">{{ notification.name }}, {{ notification.address }}</li>
        </ul>
    </main>
</template>
