import assert from 'node:assert/strict';
import { test } from 'node:test';

import { formatWindow, windowPreview } from './showingWindow.ts';

const plain = (text) => text.replace(/[   ]/g, ' ');

test('slots and leftover as in R1', () => {
    assert.deepEqual(windowPreview('10:00', '11:10', 30), { fits: true, text: '2 showings, 10 min unused' });
    assert.deepEqual(windowPreview('10:00', '10:45', 60), { fits: false, text: 'Too short: a showing takes 60 min' });
});

test('no leftover, a single showing and the exact length', () => {
    assert.deepEqual(windowPreview('10:00', '12:00', 30), { fits: true, text: '4 showings' });
    assert.deepEqual(windowPreview('10:00', '10:30', 30), { fits: true, text: '1 showing' });
    assert.deepEqual(windowPreview('10:00', '10:45', 30), { fits: true, text: '1 showing, 15 min unused' });
    assert.deepEqual(windowPreview('10:00', '10:29', 30), { fits: false, text: 'Too short: a showing takes 30 min' });
});

test('an end that is not after the start', () => {
    for (const end of ['10:00', '09:00', '00:00']) {
        assert.deepEqual(windowPreview('10:00', end, 30), { fits: false, text: 'The end must be after the start, on the same day' });
    }
});

test('nothing to say while a time is missing', () => {
    assert.equal(windowPreview('', '11:00', 30), null);
    assert.equal(windowPreview('10:00', '', 30), null);
});

test('a window reads as the clock of the house, whatever the zone of the browser', () => {
    assert.equal(plain(formatWindow('2027-03-10', '10:00', '11:10')), 'Wed, Mar 10, 2027, 10:00 – 11:10 AM');
    assert.equal(plain(formatWindow('2027-12-31', '22:30', '23:59')), 'Fri, Dec 31, 2027, 10:30 – 11:59 PM');
    assert.equal(plain(formatWindow('2027-03-14', '02:30', '13:00')), 'Sun, Mar 14, 2027, 2:30 AM – 1:00 PM');
});
