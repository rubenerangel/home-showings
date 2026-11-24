<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';

import type { SessionUser } from '../types';

defineProps<{ sellers: SessionUser[]; buyers: SessionUser[] }>();

const continueAs = (user: SessionUser) => router.post('/session', { user_id: user.id });
</script>

<template>
    <Head title="Continue as" />
    <main class="mx-auto max-w-3xl px-4 py-16">
        <p class="font-semibold text-teal-900">Home Showings</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight">Continue as</h1>
        <p class="mt-2 text-stone-600">Sellers publish the times their home can be visited. Buyers book the ones that fit theirs.</p>
        <div class="mt-8 grid gap-6 sm:grid-cols-2">
            <section v-for="group in [{ title: 'Sellers', users: sellers }, { title: 'Buyers', users: buyers }]" :key="group.title" class="card p-5">
                <h2 class="font-medium">{{ group.title }}</h2>
                <ul v-if="group.users.length" class="mt-3 space-y-2">
                    <li v-for="user in group.users" :key="user.id">
                        <button type="button" class="btn w-full justify-start" @click="continueAs(user)">{{ user.name }}</button>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-stone-600">Nobody here yet.</p>
            </section>
        </div>
    </main>
</template>
