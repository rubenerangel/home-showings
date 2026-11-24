import { router } from '@inertiajs/vue3';
import { nextTick, ref, watch } from 'vue';

import { dropEdge, moved } from './reorder';
import type { ListingPhoto } from './types';

/** The order of the photos of a home: shown at once, saved, and put back if it was not accepted. */
export function usePhotoOrder(photos: () => ListingPhoto[], base: () => string) {
    const shown = ref([...photos()]);
    const reset = () => (shown.value = [...photos()]);
    watch(photos, reset);

    const saving = ref(false);
    const error = ref<string>();
    // Only what a mouse or a finger starts slides: with a keyboard it would only be slow.
    const sliding = ref(false);

    const focusPhoto = async (id: number, selector: string) => {
        await nextTick();
        const card = document.querySelector(`[data-photo="${id}"]`);
        (card?.querySelector<HTMLElement>(selector) ?? card?.querySelector<HTMLElement>('select'))?.focus();
    };

    /** @param focus what takes the focus in the photo that moved, if it is there */
    const move = (from: number, to: number, event: MouseEvent, focus?: string) => {
        if (saving.value || from === to) return;

        const { id } = shown.value[from];
        sliding.value = event.type === 'drop' || event.detail > 0;
        shown.value = moved(shown.value, from, to);
        saving.value = true;
        error.value = undefined;

        let accepted = false;
        router.put(
            `${base()}/order`,
            { photos: shown.value.map((photo) => photo.id) },
            {
                preserveScroll: true,
                onSuccess: () => {
                    accepted = true;
                },
                onError: (errors) => {
                    error.value = errors.photos ?? Object.values(errors)[0];
                },
                onFinish: () => {
                    saving.value = false;
                    // Also when there was no answer at all.
                    if (!accepted) reset();
                    // The buttons were disabled until now, and could not take the focus.
                    else if (focus) void focusPhoto(id, focus);
                },
            },
        );
    };

    const dragged = ref<number | null>(null);
    const over = ref<number | null>(null);
    const endDrag = () => {
        dragged.value = null;
        over.value = null;
    };

    const startDrag = (index: number, event: DragEvent) => {
        if (saving.value || event.dataTransfer === null) return event.preventDefault();
        dragged.value = index;
        event.dataTransfer.effectAllowed = 'move';
        // Firefox starts a drag only if there is data.
        event.dataTransfer.setData('text/plain', '');
    };
    const dragOver = (index: number, event: DragEvent) => {
        // Nothing that started somewhere else is accepted, like a file or a photo of another page.
        if (dragged.value === null) return;
        event.preventDefault();
        over.value = index;
    };
    const drop = (index: number, event: DragEvent) => {
        if (dragged.value === null) return;
        event.preventDefault();
        move(dragged.value, index, event);
        endDrag();
    };
    const edge = (index: number) => (over.value === index ? dropEdge(dragged.value, index) : undefined);

    return { shown, saving, error, sliding, dragged, move, startDrag, dragOver, drop, endDrag, edge };
}
