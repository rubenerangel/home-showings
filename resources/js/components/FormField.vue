<script setup lang="ts">
import { computed, useId } from 'vue';

import FieldError from './FieldError.vue';

const props = defineProps<{ label: string; name: string; error?: string; hint?: string; prefix?: string }>();

const id = useId();
const describedBy = computed(() => [props.hint && `${id}-hint`, props.error && `${id}-error`].filter(Boolean).join(' ') || undefined);
const control = computed(() => ({
    id,
    name: props.name,
    autocomplete: 'off',
    class: props.prefix ? 'input pl-8' : 'input',
    'aria-describedby': describedBy.value,
    'aria-invalid': props.error ? true : undefined,
}));
</script>

<template>
    <div>
        <label :for="id" class="mb-1.5 block text-[0.9375rem] font-bold">{{ label }}</label>
        <div class="relative">
            <span v-if="prefix" aria-hidden="true" class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center font-semibold text-gravel">{{ prefix }}</span>
            <slot :control="control" />
        </div>
        <p v-if="hint" :id="`${id}-hint`" class="mt-1.5 text-sm text-gravel">{{ hint }}</p>
        <FieldError :id="`${id}-error`" :text="error" />
    </div>
</template>
