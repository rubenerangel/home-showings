<script setup lang="ts">
import { computed, useId } from 'vue';

const props = defineProps<{ label: string; name: string; error?: string; hint?: string }>();

const id = useId();
const describedBy = computed(() => [props.hint && `${id}-hint`, props.error && `${id}-error`].filter(Boolean).join(' ') || undefined);
const control = computed(() => ({
    id,
    name: props.name,
    autocomplete: 'off',
    class: 'input',
    'aria-describedby': describedBy.value,
    'aria-invalid': props.error ? true : undefined,
}));
</script>

<template>
    <div>
        <label :for="id" class="mb-1.5 block text-[0.9375rem] font-bold">{{ label }}</label>
        <slot :control="control" />
        <p v-if="hint" :id="`${id}-hint`" class="mt-1.5 text-sm text-gravel">{{ hint }}</p>
        <p v-if="error" :id="`${id}-error`" class="mt-1.5 text-sm font-bold text-stop">{{ error }}</p>
    </div>
</template>
