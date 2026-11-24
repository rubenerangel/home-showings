<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { onMounted } from 'vue';

import StreetSign from '../components/StreetSign.vue';
import { dropPush } from '../push';
import type { SessionUser } from '../types';

defineProps<{ sellers: SessionUser[]; buyers: SessionUser[] }>();

const page = usePage();

// Nobody is signed in here, so this browser must not keep receiving what was for someone else.
onMounted(() => {
    if (!page.props.user) void dropPush();
});

const continueAs = async (user: SessionUser) => {
    await dropPush();
    router.post('/session', { user_id: user.id });
};
</script>

<template>
    <Head title="Continue as" />
    <main class="page-narrow sm:pt-20">
        <p class="text-lg font-extrabold tracking-tight text-sign">Home Showings</p>
        <h1 class="title mt-3">Continue as</h1>
        <p class="lead">Sellers publish the times their home can be visited. Buyers book the ones that fit theirs.</p>
        <div class="mt-12 grid gap-x-6 gap-y-12 sm:grid-cols-2">
            <section v-for="group in [{ title: 'Sellers', users: sellers }, { title: 'Buyers', users: buyers }]" :key="group.title" class="panel px-3 pb-3">
                <h2 class="-mt-5 mb-3 px-2"><StreetSign>{{ group.title }}</StreetSign></h2>
                <ul v-if="group.users.length" class="divide-y divide-curb">
                    <li v-for="user in group.users" :key="user.id">
                        <button
                            type="button"
                            translate="no"
                            class="min-h-13 w-full cursor-pointer rounded-md px-3 text-left text-lg font-bold transition-colors duration-100 hover:bg-sign-wash hover:text-sign-deep active:bg-sign-line"
                            @click="continueAs(user)"
                        >
                            {{ user.name }}
                        </button>
                    </li>
                </ul>
                <p v-else class="px-3 pb-3 text-gravel">Nobody here yet.</p>
            </section>
        </div>
    </main>
</template>
