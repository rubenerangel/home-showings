<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{ ranges: { id: number; date: string; start: string; end: string }[] }>();
</script>

<template>
    <Head title="Availability" />
    <main class="mx-auto max-w-3xl p-8">
        <h1 class="text-2xl font-semibold">Availability</h1>
        <ul class="mt-4 space-y-2">
            <li v-for="range in ranges" :key="range.id">{{ range.date }} {{ range.start }} {{ range.end }}</li>
        </ul>
    </main>
</template>
