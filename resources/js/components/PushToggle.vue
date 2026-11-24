<script setup lang="ts">
import { onMounted, ref } from 'vue';

import { disablePush, enablePush, pushSupported, resumePush } from '../push';

const props = defineProps<{ publicKey: string }>();

const state = ref<'checking' | 'unsupported' | 'blocked' | 'off' | 'on'>('checking');
const busy = ref(false);
const error = ref<string | null>(null);

const run = async (action: () => Promise<void>) => {
    busy.value = true;
    error.value = null;
    try {
        await action();
    } catch {
        error.value = 'Push notifications could not be changed on this device. Try again.';
    } finally {
        busy.value = false;
    }
};

onMounted(() =>
    run(async () => {
        if (!pushSupported()) state.value = 'unsupported';
        else if (Notification.permission === 'denied') state.value = 'blocked';
        else {
            state.value = 'off';
            state.value = (await resumePush(props.publicKey)) ? 'on' : 'off';
        }
    }),
);

const enable = () =>
    run(async () => {
        const permission = await Notification.requestPermission();
        if (permission === 'denied') state.value = 'blocked';
        if (permission !== 'granted') return;

        await enablePush(props.publicKey);
        state.value = 'on';
    });

const disable = () =>
    run(async () => {
        await disablePush();
        state.value = 'off';
    });
</script>

<template>
    <section v-if="state !== 'checking'" aria-label="Push notifications" class="panel flex flex-wrap items-center justify-between gap-4 px-5 py-4">
        <div>
            <p class="font-bold">Push notifications on this device</p>
            <p class="text-sm text-gravel">
                <template v-if="state === 'on'">On. They arrive even with this tab closed, until you switch user.</template>
                <template v-else-if="state === 'off'">Off. Turn them on to hear about changes with this tab closed.</template>
                <template v-else-if="state === 'blocked'">Blocked in the settings of this browser.</template>
                <template v-else>This browser does not support them.</template>
            </p>
            <p v-if="error" role="alert" class="mt-1 text-sm font-bold text-stop">{{ error }}</p>
        </div>
        <button v-if="state === 'off'" type="button" class="btn btn-small" :disabled="busy" @click="enable">Turn on</button>
        <button v-if="state === 'on'" type="button" class="btn btn-small" :disabled="busy" @click="disable">Turn off</button>
    </section>
</template>
