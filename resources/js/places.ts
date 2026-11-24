interface Place {
    state: string;
    city: string;
}

const keyOf = (home: Place) => `${home.state} ${home.city.trim().toLowerCase()}`;

/** Homes by city, in the order they come. The same city in two states is two cities, and "denver" is Denver. */
export const groupByCity = <T extends Place>(homes: readonly T[]): { key: string; state: string; city: string; homes: T[] }[] => {
    const groups = new Map<string, { key: string; state: string; city: string; homes: T[] }>();
    for (const home of homes) {
        const key = keyOf(home);
        const group = groups.get(key) ?? { key, state: home.state, city: home.city.trim(), homes: [] };
        group.homes.push(home);
        groups.set(key, group);
    }

    return [...groups.values()];
};

/** By state and city, and the homes of a city as they were. */
export const byPlace = <T extends Place>(homes: readonly T[]): T[] =>
    [...homes].sort((a, b) => (keyOf(a) < keyOf(b) ? -1 : keyOf(a) > keyOf(b) ? 1 : 0));
