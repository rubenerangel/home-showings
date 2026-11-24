import { nextTick } from 'vue';

/** After a rejected form, the first field to fix. */
export const focusFirstError = async () => {
    await nextTick();
    document.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus();
};
