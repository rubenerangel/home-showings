/** The rule of the server, so both say the same. */
export interface EmailFormat {
    pattern: string;
    message: string;
}

// What the server removes, also the invisible characters that come with a pasted text.
const AROUND = /^[\s\p{Default_Ignorable_Code_Point}\u2800\u{1D159}]+|[\s\p{Default_Ignorable_Code_Point}\u2800\u{1D159}]+$/gu;

/** Capitals are lowered by the server, which also says that an empty one is required. */
export const emailError = (email: string, { pattern, message }: EmailFormat): string | undefined => {
    const value = email.replace(AROUND, '').toLowerCase();

    return value === '' || new RegExp(pattern).test(value) ? undefined : message;
};
