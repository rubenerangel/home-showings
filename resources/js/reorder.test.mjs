import assert from 'node:assert/strict';
import { test } from 'node:test';

import { dropEdge, moved } from './reorder.ts';

test('an item goes to its new place and the others keep their order', () => {
    assert.deepEqual(moved(['a', 'b', 'c', 'd'], 2, 0), ['c', 'a', 'b', 'd']);
    assert.deepEqual(moved(['a', 'b', 'c', 'd'], 0, 3), ['b', 'c', 'd', 'a']);
    assert.deepEqual(moved(['a', 'b', 'c', 'd'], 1, 2), ['a', 'c', 'b', 'd']);
    assert.deepEqual(moved(['a', 'b', 'c', 'd'], 3, 1), ['a', 'd', 'b', 'c']);
});

test('moving to the same place changes nothing', () => {
    assert.deepEqual(moved(['a', 'b'], 1, 1), ['a', 'b']);
    assert.deepEqual(moved(['a'], 0, 0), ['a']);
});

test('the list that was given stays as it was', () => {
    const list = ['a', 'b', 'c'];
    moved(list, 0, 2);
    assert.deepEqual(list, ['a', 'b', 'c']);
});

test('the edge is the side the item comes from', () => {
    assert.equal(dropEdge(3, 1), 'before');
    assert.equal(dropEdge(1, 3), 'after');
});

test('there is no edge over itself or without a drag', () => {
    assert.equal(dropEdge(2, 2), undefined);
    assert.equal(dropEdge(null, 1), undefined);
    assert.equal(dropEdge(1, null), undefined);
});
