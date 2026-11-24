<script setup lang="ts">
import { nextTick, ref } from 'vue';

defineProps<{ label: string; subject: string; question: string; confirmLabel: string; keepLabel: string; disabled?: boolean }>();
const emit = defineEmits<{ confirm: [] }>();

const asking = ref(false);
const keep = ref<HTMLButtonElement | null>(null);
const trigger = ref<HTMLButtonElement | null>(null);

const ask = async () => {
    asking.value = true;
    await nextTick();
    keep.value?.focus();
};
const dismiss = async () => {
    asking.value = false;
    await nextTick();
    trigger.value?.focus();
};
const confirm = () => {
    asking.value = false;
    emit('confirm');
};
</script>

<template>
    <div v-if="asking" role="group" :aria-label="question" class="flex flex-wrap items-center gap-2" @keydown.esc="dismiss">
        <p class="w-full text-sm sm:w-auto">{{ question }}</p>
        <button type="button" class="btn btn-small btn-stop" @click="confirm">{{ confirmLabel }}</button>
        <button ref="keep" type="button" class="btn btn-small" @click="dismiss">{{ keepLabel }}</button>
    </div>
    <button v-else ref="trigger" type="button" class="btn btn-small" :disabled="disabled" @click="ask">
        {{ label }}<span class="sr-only">&nbsp;{{ subject }}</span>
    </button>
</template>
