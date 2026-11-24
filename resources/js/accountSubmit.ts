import { ref } from 'vue';

import { focusFirstError } from './focus';
import { dropPush } from './push';

/** Sends a form that changes the user of this browser, so its push subscription ends first. */
export function useAccountSubmit(send: (options: { onError: () => Promise<void>; onFinish: () => void }) => void, clearPasswords: () => void) {
    const busy = ref(false);

    const submit = async () => {
        if (busy.value) return;
        busy.value = true;
        await dropPush();
        send({
            onError: focusFirstError,
            onFinish: () => {
                clearPasswords();
                busy.value = false;
            },
        });
    };

    return { busy, submit };
}
