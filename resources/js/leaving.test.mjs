import assert from 'node:assert/strict';
import { test } from 'node:test';

import { leaves } from './leaving.ts';

const visit = (method, pathname, prefetch) => ({ method, url: { pathname }, prefetch });

test('opening another page and logging out leave', () => {
    assert.equal(leaves(visit('get', '/seller')), true);
    assert.equal(leaves(visit('delete', '/session')), true);
});

test('saving the form or a photo does not', () => {
    assert.equal(leaves(visit('put', '/seller/listings/1')), false);
    assert.equal(leaves(visit('post', '/seller/listings')), false);
    assert.equal(leaves(visit('delete', '/seller/listings/1/photos/2')), false);
    assert.equal(leaves(visit('put', '/seller/listings/1/photos/order')), false);
});

test('a prefetch does not', () => {
    assert.equal(leaves(visit('get', '/seller', true)), false);
});
