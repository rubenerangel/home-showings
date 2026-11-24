// Times on the clock of the house: they have no zone, so UTC is only a neutral one to compute with.
const instant = (date: string, time: string) => new Date(`${date}T${time}:00Z`);
const minutes = (time: string) => instant('2000-01-01', time).getTime() / 60_000;

const formatter = new Intl.DateTimeFormat('en-US', {
    timeZone: 'UTC',
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
});

export const formatWindow = (date: string, start: string, end: string) =>
    formatter.formatRange(instant(date, start), instant(date, end));

export const formatSlots = (showings: number, unusedMinutes: number) =>
    `${showings} ${showings === 1 ? 'showing' : 'showings'}${unusedMinutes ? `, ${unusedMinutes} min unused` : ''}`;

/** What a window would hold (R1). The server decides: this only anticipates it. */
export function windowPreview(start: string, end: string, duration: number): { fits: boolean; text: string } | null {
    if (!start || !end) return null;

    const length = minutes(end) - minutes(start);
    if (length <= 0) return { fits: false, text: 'The end must be after the start, on the same day' };
    if (length < duration) return { fits: false, text: `Too short: a showing takes ${duration} min` };

    return { fits: true, text: formatSlots(Math.floor(length / duration), length % duration) };
}
