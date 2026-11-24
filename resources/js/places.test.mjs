import assert from 'node:assert/strict';
import { test } from 'node:test';

import { byPlace, groupByCity } from './places.ts';

const home = (id, state, city) => ({ id, state, city });
const ids = (groups) => groups.map((group) => group.homes.map(({ id }) => id));

test('homes go by city, in the order they come', () => {
    const groups = groupByCity([home(1, 'CO', 'Denver'), home(2, 'CO', 'Boulder'), home(3, 'CO', 'Denver')]);
    assert.deepEqual(groups.map(({ city }) => city), ['Denver', 'Boulder']);
    assert.deepEqual(ids(groups), [[1, 3], [2]]);
});

test('the case and the spaces of a city do not make another one', () => {
    assert.deepEqual(ids(groupByCity([home(1, 'CO', 'Denver'), home(2, 'CO', 'denver'), home(3, 'CO', ' DENVER ')])), [[1, 2, 3]]);
});

test('the same city in two states is two cities', () => {
    assert.deepEqual(ids(groupByCity([home(1, 'IL', 'Springfield'), home(2, 'MA', 'Springfield')])), [[1], [2]]);
});

test('by place keeps the order inside a city, and the list that was given', () => {
    const homes = [home(5, 'NY', 'Albany'), home(2, 'CO', 'Denver'), home(9, 'NY', 'albany'), home(1, 'CO', 'Boulder')];
    assert.deepEqual(byPlace(homes).map(({ id }) => id), [1, 2, 5, 9]);
    assert.equal(homes[0].id, 5);
});

test('nothing gives nothing', () => {
    assert.deepEqual(groupByCity([]), []);
    assert.deepEqual(byPlace([]), []);
});
