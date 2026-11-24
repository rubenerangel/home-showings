import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';

import { leaves } from './leaving';

/** Asks before leaving the page while there is something to lose. */
export function useUnsavedChanges(dirty: () => boolean) {
    const beforeUnload = (event: BeforeUnloadEvent) => {
        if (dirty()) event.preventDefault();
    };
    let stopListening: (() => void) | undefined;

    onMounted(() => {
        window.addEventListener('beforeunload', beforeUnload);
        stopListening = router.on('before', ({ detail: { visit } }) => {
            if (dirty() && leaves(visit)) return window.confirm('Leave without saving your changes?');
        });
    });
    onBeforeUnmount(() => {
        window.removeEventListener('beforeunload', beforeUnload);
        stopListening?.();
    });
}
