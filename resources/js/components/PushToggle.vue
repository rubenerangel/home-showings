<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';

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
            let on = false;
            try {
                on = await resumePush(props.publicKey);
            } finally {
                state.value = on ? 'on' : 'off';
            }
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

// Not `disabled` while it works: that would take the focus away from the keyboard.
const toggle = () => {
    if (busy.value) return;
    void (state.value === 'on' ? disable() : enable());
};

const status = computed(
    () =>
        ({
            checking: { label: 'Checking…', badge: '', text: 'Looking at the settings of this browser.' },
            on: { label: 'On', badge: 'bg-sign text-white', text: 'They arrive even with this tab closed, until you log out.' },
            off: { label: 'Off', badge: 'bg-gravel text-white', text: 'Turn them on to hear about changes with this tab closed.' },
            blocked: { label: 'Blocked', badge: 'bg-stop text-white', text: 'Allow notifications for this site in the settings of the browser, and reload.' },
            unsupported: { label: 'Not available', badge: '', text: 'This browser does not support them.' },
        })[state.value],
);
</script>

<template>
    <section aria-label="Push notifications" class="panel push-panel flex items-center gap-4 px-5 py-4 sm:px-6" :class="{ 'push-panel-on': state === 'on' }" :aria-busy="busy">
        <span
            class="flex size-11 shrink-0 items-center justify-center rounded-full transition-colors duration-150 max-sm:hidden"
            :class="state === 'on' ? 'bg-sign text-white' : state === 'blocked' ? 'bg-stop-wash text-stop' : 'bg-mist text-gravel'"
        >
            <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M6.5 16.5h11l-1.4-2.2V10a4.1 4.1 0 0 0-8.2 0v4.3zM10.2 19.5a2 2 0 0 0 3.6 0M12 5.9V4" />
                <path v-if="state !== 'on'" d="M4.5 4.5l15 15" />
            </svg>
        </span>
        <div class="min-w-0 grow">
            <p class="flex flex-wrap items-center gap-x-2.5 gap-y-1 font-bold">
                Push notifications on this device
                <span role="status" class="badge" :class="status.badge">{{ status.label }}</span>
            </p>
            <p class="mt-0.5 text-sm text-gravel">{{ status.text }}</p>
            <p v-if="error" role="alert" class="mt-1 text-sm font-bold text-stop">{{ error }}</p>
        </div>
        <button
            v-if="state === 'on' || state === 'off'"
            type="button"
            role="switch"
            class="switch"
            aria-label="Push notifications on this device"
            :aria-checked="state === 'on'"
            :aria-disabled="busy"
            @click="toggle"
        >
            <span class="switch-thumb">
                <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path :d="state === 'on' ? 'M5 12.5l4.5 4.5L19 7.5' : 'M7 7l10 10M17 7L7 17'" />
                </svg>
            </span>
        </button>
    </section>
</template>
