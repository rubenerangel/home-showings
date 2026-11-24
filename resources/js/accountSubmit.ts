import { ref } from 'vue';

import { focusFirstError } from './focus';
import { dropPush } from './push';

/** Sends a form that changes the user of this browser. Its push subscription ends only if the user did change. */
export function useAccountSubmit(send: (options: { onSuccess: () => Promise<void>; onError: () => Promise<void>; onFinish: () => void }) => void, clearPasswords: () => void) {
    const busy = ref(false);

    const submit = () => {
        if (busy.value) return;
        busy.value = true;
        send({
            onSuccess: dropPush,
            onError: focusFirstError,
            onFinish: () => {
                clearPasswords();
                busy.value = false;
            },
        });
    };

    return { busy, submit };
}
