<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';

import type { SessionUser } from '../types';

defineProps<{ sellers: SessionUser[]; buyers: SessionUser[] }>();

const continueAs = (user: SessionUser) => router.post('/session', { user_id: user.id });
</script>

<template>
    <Head title="Continue as" />
    <main class="mx-auto max-w-3xl p-8">
        <h1 class="text-2xl font-semibold">Continue as</h1>
        <div class="mt-6 grid gap-6 sm:grid-cols-2">
            <section v-for="group in [{ title: 'Sellers', users: sellers }, { title: 'Buyers', users: buyers }]" :key="group.title">
                <h2 class="font-medium">{{ group.title }}</h2>
                <ul class="mt-2 space-y-2">
                    <li v-for="user in group.users" :key="user.id">
                        <button type="button" class="w-full rounded border border-stone-300 bg-white px-4 py-2 text-left hover:bg-stone-100" @click="continueAs(user)">
                            {{ user.name }}
                        </button>
                    </li>
                </ul>
            </section>
        </div>
    </main>
</template>
