/** The list with one item taken from its place and put in another. */
export const moved = <T>(list: readonly T[], from: number, to: number): T[] => {
    const result = [...list];
    result.splice(to, 0, ...result.splice(from, 1));

    return result;
};

/** The edge of an item where the dragged one would go, or nothing over itself. */
export const dropEdge = (from: number | null, over: number | null): 'before' | 'after' | undefined => {
    if (from === null || over === null || from === over) return undefined;

    return over < from ? 'before' : 'after';
};
