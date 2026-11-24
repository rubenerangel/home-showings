<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{ appName: string }>();
</script>

<template>
    <Head title="Home" />
    <main class="mx-auto max-w-3xl p-8">
        <h1 class="text-2xl font-semibold">{{ appName }}</h1>
    </main>
</template>
