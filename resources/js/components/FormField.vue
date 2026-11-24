<script setup lang="ts">
import { computed, useId } from 'vue';

const props = defineProps<{ label: string; error?: string; hint?: string }>();

const id = useId();
const describedBy = computed(() => [props.hint && `${id}-hint`, props.error && `${id}-error`].filter(Boolean).join(' ') || undefined);
const control = computed(() => ({
    id,
    class: 'input',
    'aria-describedby': describedBy.value,
    'aria-invalid': props.error ? true : undefined,
}));
</script>

<template>
    <div>
        <label :for="id" class="mb-1 block text-sm font-medium">{{ label }}</label>
        <slot :control="control" />
        <p v-if="hint" :id="`${id}-hint`" class="mt-1 text-xs text-stone-600">{{ hint }}</p>
        <p v-if="error" :id="`${id}-error`" class="mt-1 text-sm text-red-700">{{ error }}</p>
    </div>
</template>
