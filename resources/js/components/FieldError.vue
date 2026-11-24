<script setup lang="ts">
defineProps<{ id: string; text?: string }>();
</script>

<template>
    <Transition name="fade">
        <p v-if="text" :id="id" class="mt-1.5 flex items-start gap-1.5 text-sm font-bold text-stop">
            <svg viewBox="0 0 24 24" class="mt-0.5 size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                <circle cx="12" cy="12" r="9.5" />
                <path d="M12 7v6M12 16.5v.01" />
            </svg>
            <span class="min-w-0">{{ text }}</span>
        </p>
    </Transition>
</template>
