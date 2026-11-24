<script setup lang="ts">
defineProps<{ facts: string[] }>();
</script>

<template>
    <ul class="flex flex-wrap gap-y-1 text-gravel">
        <li v-for="fact in facts" :key="fact" class="not-first:ml-3 not-first:border-l not-first:border-curb not-first:pl-3">{{ fact }}</li>
    </ul>
</template>
