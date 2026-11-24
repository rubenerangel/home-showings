// Criterion 21. The locale is fixed because the interface is in English.
const formatter = (timeZone: string, options: Intl.DateTimeFormatOptions) => new Intl.DateTimeFormat('en-US', { timeZone, ...options });

const time: Intl.DateTimeFormatOptions = { hour: 'numeric', minute: '2-digit', timeZoneName: 'short' };
const dateAndTime: Intl.DateTimeFormatOptions = { weekday: 'short', month: 'short', day: 'numeric', ...time };

/** The viewer line is null when both clocks read the same during the showing. */
export function houseTime(startsAt: string, endsAt: string, timezone: string, viewerTimezone: string) {
    const start = new Date(startsAt);
    const end = new Date(endsAt);
    const dateOf = (zone: string, instant: Date) => formatter(zone, { dateStyle: 'short' }).format(instant);
    const clockOf = (zone: string, instant: Date) => formatter(zone, { dateStyle: 'short', timeStyle: 'short' }).format(instant);
    const range = (zone: string, options: Intl.DateTimeFormatOptions) => formatter(zone, options).formatRange(start, end);

    const instants = [start, end];
    const sameClock = instants.every((instant) => clockOf(timezone, instant) === clockOf(viewerTimezone, instant));
    const sameDate = instants.every((instant) => dateOf(timezone, instant) === dateOf(viewerTimezone, instant));

    return {
        house: range(timezone, dateAndTime),
        viewer: sameClock ? null : range(viewerTimezone, sameDate ? time : dateAndTime),
    };
}
