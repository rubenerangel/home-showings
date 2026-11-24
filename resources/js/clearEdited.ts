/** For the input event of a form: the message of a field goes away as soon as a person edits it. */
export const clearEdited = (form: { clearErrors: (...fields: never[]) => unknown }) => (event: Event) => {
    const { name } = event.target as HTMLInputElement;
    if (name) form.clearErrors(name as never);
};
