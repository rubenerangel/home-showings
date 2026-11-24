import assert from 'node:assert/strict';
import { test } from 'node:test';

import { houseTime } from './houseTime.ts';

// Intl separates with thin and no-break spaces.
const plain = (text) => text?.replace(/[   ]/g, ' ') ?? null;
const lines = (start, end, home, viewer) => {
    const { house, viewer: own } = houseTime(start, end, home, viewer);
    return [plain(house), plain(own)];
};

const denver = 'America/Denver';
const phoenix = 'America/Phoenix';
const newYork = 'America/New_York';
const losAngeles = 'America/Los_Angeles';

test('the time of the house carries its zone, and a viewer in that zone sees no second line', () => {
    assert.deepEqual(lines('2027-03-10T17:00:00Z', '2027-03-10T17:30:00Z', denver, denver), [
        'Wed, Mar 10, 10:00 – 10:30 AM MST',
        null,
    ]);
});

test('the same instant follows daylight saving of the house', () => {
    assert.equal(lines('2027-03-13T17:00:00Z', '2027-03-13T17:30:00Z', denver, denver)[0], 'Sat, Mar 13, 10:00 – 10:30 AM MST');
    assert.equal(lines('2027-03-15T16:00:00Z', '2027-03-15T16:30:00Z', denver, denver)[0], 'Mon, Mar 15, 10:00 – 10:30 AM MDT');
});

test('a viewer in another zone sees their time, without the date while it is the same', () => {
    assert.equal(lines('2027-03-10T17:00:00Z', '2027-03-10T17:30:00Z', denver, newYork)[1], '12:00 – 12:30 PM EST');
    assert.equal(lines('2027-03-10T18:30:00Z', '2027-03-10T19:30:00Z', denver, losAngeles)[1], '10:30 – 11:30 AM PST');
});

test('two zones count as different only on the dates they differ', () => {
    assert.equal(lines('2027-03-13T17:00:00Z', '2027-03-13T17:30:00Z', denver, phoenix)[1], null);
    assert.equal(lines('2027-03-15T16:00:00Z', '2027-03-15T16:30:00Z', denver, phoenix)[1], '9:00 – 9:30 AM MST');
});

test('the date appears when it changes forwards or backwards', () => {
    assert.equal(
        lines('2027-03-11T05:00:00Z', '2027-03-11T05:30:00Z', losAngeles, newYork)[1],
        'Thu, Mar 11, 12:00 – 12:30 AM EST',
    );
    assert.equal(
        lines('2027-03-11T05:30:00Z', '2027-03-11T06:00:00Z', newYork, losAngeles)[1],
        'Wed, Mar 10, 9:30 – 10:00 PM PST',
    );
});

test('the date appears on both ends when only the end changes date', () => {
    assert.equal(
        lines('2027-03-11T04:30:00Z', '2027-03-11T05:30:00Z', losAngeles, newYork)[1],
        'Wed, Mar 10, 11:30 PM EST – Thu, Mar 11, 12:30 AM EST',
    );
});

test('a half hour zone and the last day of the year', () => {
    assert.equal(
        lines('2027-12-31T19:00:00Z', '2027-12-31T19:15:00Z', phoenix, 'Asia/Kolkata')[1],
        'Sat, Jan 1, 12:30 – 12:45 AM GMT+5:30',
    );
});
