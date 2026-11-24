import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

import { emailError } from './emailFormat.ts';

// The same list that PHP and PostgreSQL run. A PHP test checks that the pattern is the real one.
const { pattern, complete, incomplete } = JSON.parse(readFileSync(new URL('./emailFormat.cases.json', import.meta.url), 'utf8'));
const format = { pattern, message: 'Enter a complete email.' };

// What Laravel removes around the text of a request.
const invisible = [0x9, 0xa, 0xd, 0x20, 0xa0, 0xad, 0x34f, 0x61c, 0x115f, 0x1160, 0x17b4, 0x17b5, 0x180e, 0x202f, 0x205f, 0x2800, 0x3000, 0x3164, 0xfeff, 0xffa0, 0x1d159, 0xe0020];
for (const [from, to] of [[0x2000, 0x200f], [0x2060, 0x206f], [0x1d173, 0x1d17a]]) {
    for (let code = from; code <= to; code++) invisible.push(code);
}

for (const email of complete) {
    test(`the pattern accepts ${JSON.stringify(email)}`, () => assert.equal(new RegExp(pattern).test(email), true));
}

for (const email of incomplete) {
    test(`the pattern refuses ${JSON.stringify(email)}`, () => assert.equal(new RegExp(pattern).test(email), false));
}

test('an incomplete email has the message of the server', () => {
    assert.equal(emailError('test@testco', format), 'Enter a complete email.');
});

test('a complete one has none', () => {
    assert.equal(emailError('ada@example.com', format), undefined);
});

test('capitals are left to the server, which lowers them', () => {
    assert.equal(emailError('Ada@Example.COM', format), undefined);
});

test('what the server removes around it is not an error', () => {
    for (const code of invisible) {
        const character = String.fromCodePoint(code);
        assert.equal(emailError(`${character}ada@example.com${character}`, format), undefined, code.toString(16));
        assert.equal(emailError(`ada${character}@example.com`, format), format.message, code.toString(16));
    }
});

test('an empty one is left to the server, which says that it is required', () => {
    assert.equal(emailError('', format), undefined);
    assert.equal(emailError('   ', format), undefined);
});
