<script setup lang="ts">
import { useId } from 'vue';

import FieldError from './FieldError.vue';

defineProps<{
    label: string;
    name: string;
    /** `text` is what fits in the button, and `label` says it in full. */
    options: { value: number; text: string; label: string }[];
    error?: string;
    hint?: string;
}>();
const model = defineModel<number>({ required: true });

const id = useId();
</script>

<template>
    <fieldset :aria-describedby="[hint && `${id}-hint`, error && `${id}-error`].filter(Boolean).join(' ') || undefined">
        <legend class="mb-1.5 text-[0.9375rem] font-bold">{{ label }}</legend>
        <div class="choices">
            <label v-for="option in options" :key="option.value" class="choice" :title="option.label">
                <input
                    v-model="model"
                    type="radio"
                    class="sr-only"
                    :name="name"
                    :value="option.value"
                    :aria-label="option.label"
                    :aria-invalid="error ? true : undefined"
                />
                <span aria-hidden="true">{{ option.text }}</span>
            </label>
        </div>
        <p v-if="hint" :id="`${id}-hint`" class="mt-1.5 text-sm text-gravel">{{ hint }}</p>
        <FieldError :id="`${id}-error`" :text="error" />
    </fieldset>
</template>
