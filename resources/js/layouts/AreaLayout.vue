<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import type { SessionUser } from '../types';

const navigation: Record<SessionUser['role'], { label: string; href: string }[]> = {
    seller: [{ label: 'My homes', href: '/seller' }],
    buyer: [{ label: 'Homes', href: '/buyer' }],
};

const page = usePage();
// Pages of an area are only served to a user with its role.
const user = computed(() => page.props.user!);
const links = computed(() => navigation[user.value.role]);
const unread = computed(() => page.props.unreadNotifications ?? 0);
const path = computed(() => page.url.split('?')[0]);
</script>

<template>
    <header class="border-b border-stone-200 bg-white">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3">
            <span class="font-semibold text-teal-900">Home Showings</span>
            <nav aria-label="Main" class="flex gap-4 text-sm">
                <Link
                    v-for="link in links"
                    :key="link.href"
                    :href="link.href"
                    :aria-current="path === link.href ? 'page' : undefined"
                    class="rounded py-1 hover:underline aria-[current=page]:font-semibold aria-[current=page]:text-teal-800"
                >
                    {{ link.label }}
                </Link>
                <Link :href="`/${user.role}/notifications`" class="rounded py-1 hover:underline">
                    Notifications
                    <span v-if="unread > 0" class="ml-1 rounded-full bg-teal-800 px-2 py-0.5 text-xs font-semibold text-white">
                        {{ unread }}<span class="sr-only"> unread</span>
                    </span>
                </Link>
            </nav>
            <div class="ml-auto flex items-center gap-3 text-sm">
                <span>
                    {{ user.name }} <span class="text-stone-600">({{ user.role === 'seller' ? 'Seller' : 'Buyer' }})</span>
                </span>
                <Link href="/session" method="delete" as="button" class="btn py-1">Switch user</Link>
            </div>
        </div>
    </header>
    <div role="status" class="mx-auto max-w-5xl px-4">
        <p v-if="page.flash.message" class="mt-4 rounded-md border border-teal-200 bg-teal-50 px-4 py-2 text-sm text-teal-900">
            {{ page.flash.message }}
        </p>
    </div>
    <slot />
</template>
