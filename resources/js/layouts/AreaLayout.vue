<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import PageMessage from '../components/PageMessage.vue';
import type { SessionUser } from '../types';

const navigation: Record<SessionUser['role'], { label: string; href: string }[]> = {
    seller: [{ label: 'My homes', href: '/seller' }],
    buyer: [
        { label: 'Homes', href: '/buyer' },
        { label: 'Availability', href: '/buyer/availability' },
        { label: 'My showings', href: '/buyer/showings' },
    ],
};

const page = usePage();
// Pages of an area are only served to a user with its role.
const user = computed(() => page.props.user!);
const links = computed(() => [...navigation[user.value.role], { label: 'Notifications', href: `/${user.value.role}/notifications` }]);
const unread = computed(() => page.props.unreadNotifications ?? 0);
const refusal = computed(() => page.props.errors.refusal);
const path = computed(() => page.url.split('?')[0]);
const skip = () => document.getElementById('content')?.focus();
</script>

<template>
    <a href="#content" class="btn btn-primary absolute top-2 left-2 z-30 not-focus:sr-only" @click.prevent="skip">Skip to content</a>
    <header class="border-b-[3px] border-sign bg-white">
        <div class="mx-auto grid max-w-6xl grid-cols-[1fr_auto] items-center gap-x-4 px-4 sm:px-6 lg:min-h-16 lg:grid-cols-[auto_1fr_auto] lg:gap-x-8">
            <span class="py-2 text-lg font-extrabold tracking-tight text-sign">Home Showings</span>
            <div class="flex min-w-0 items-center gap-3 py-2 lg:order-last">
                <span class="min-w-0 truncate text-sm" translate="no">
                    {{ user.name }}<span class="text-gravel max-sm:sr-only"> ({{ user.role === 'seller' ? 'Seller' : 'Buyer' }})</span>
                </span>
                <Link href="/session" method="delete" as="button" class="btn btn-small shrink-0">Switch user</Link>
            </div>
            <nav aria-label="Main" class="col-span-2 -mx-4 flex gap-x-4 overflow-x-auto px-4 text-[0.9375rem] sm:mx-0 sm:gap-x-6 sm:px-0 lg:col-span-1 lg:self-stretch">
                <Link
                    v-for="link in links"
                    :key="link.href"
                    :href="link.href"
                    :aria-current="path === link.href ? 'page' : undefined"
                    class="flex min-h-11 shrink-0 items-center gap-1.5 border-b-[3px] border-transparent font-semibold text-gravel -outline-offset-2 hover:text-asphalt aria-[current=page]:border-sign aria-[current=page]:font-bold aria-[current=page]:text-asphalt"
                >
                    {{ link.label }}
                    <span v-if="link.label === 'Notifications' && unread > 0" class="badge badge-caution px-2 text-xs tabular-nums">
                        {{ unread }}<span class="sr-only"> unread</span>
                    </span>
                </Link>
            </nav>
        </div>
    </header>
    <!-- Stays in view: the form that caused it may be far down the page. -->
    <div class="pointer-events-none sticky top-0 z-20 mx-auto max-w-6xl px-4 sm:px-6">
        <PageMessage kind="done" :text="page.flash.message" />
        <PageMessage kind="refused" :text="refusal" />
    </div>
    <div id="content" tabindex="-1" class="outline-none"><slot /></div>
</template>
