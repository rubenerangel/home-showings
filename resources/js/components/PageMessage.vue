<script setup lang="ts">
import { ref, watch } from 'vue';

const props = defineProps<{ text?: string; kind: 'done' | 'refused' }>();

const dismissed = ref(false);
watch(
    () => props.text,
    () => (dismissed.value = false),
);
</script>

<template>
    <div :role="kind === 'done' ? 'status' : 'alert'">
        <Transition name="appear">
            <div
                v-if="text && !dismissed"
                :key="text"
                class="pointer-events-auto mt-3 flex items-start gap-3 rounded-lg border py-2 pr-2 pl-4 font-semibold shadow-[0_6px_20px_-8px_rgb(23_37_42/0.35)]"
                :class="kind === 'done' ? 'border-sign-line bg-sign-wash text-sign-deep' : 'border-stop-line bg-stop-wash text-stop'"
            >
                <svg v-if="kind === 'done'" viewBox="0 0 24 24" class="mt-1.5 size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 13l5 5L20 6" /></svg>
                <svg v-else viewBox="0 0 24 24" class="mt-1.5 size-5 shrink-0" fill="currentColor" aria-hidden="true"><path d="M8 2h8l6 6v8l-6 6H8l-6-6V8z" /><path d="M7 12h10" stroke="#fff" stroke-width="3" /></svg>
                <p class="min-w-0 grow py-1">{{ text }}</p>
                <button type="button" aria-label="Dismiss message" class="flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-md hover:bg-white/70 pointer-coarse:size-11" @click="dismissed = true">
                    <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                </button>
            </div>
        </Transition>
    </div>
</template>
