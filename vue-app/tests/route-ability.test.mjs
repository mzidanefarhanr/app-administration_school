import test from 'node:test';
import assert from 'node:assert/strict';
import { isRouteAbilitySatisfied } from '../src/router/routeAbility.mjs';

const granted = (...abilities) => ({
    can: (ability) => abilities.includes(ability),
    canAny: (wanted) => wanted.some((ability) => abilities.includes(ability))
});

test('routes without an ability requirement stay accessible', () => {
    assert.equal(isRouteAbilitySatisfied(undefined, granted()), true);
    assert.equal(isRouteAbilitySatisfied(null, granted()), true);
    assert.equal(isRouteAbilitySatisfied('', granted()), true);
});

test('single abilities keep their legacy behaviour', () => {
    assert.equal(isRouteAbilitySatisfied('students.view', granted('students.view')), true);
    assert.equal(isRouteAbilitySatisfied('students.view', granted('employees.view')), false);
});

test('ability arrays allow either permission', () => {
    const required = ['students.view', 'employees.view'];

    assert.equal(isRouteAbilitySatisfied(required, granted('students.view')), true);
    assert.equal(isRouteAbilitySatisfied(required, granted('employees.view')), true);
    assert.equal(isRouteAbilitySatisfied(required, granted('students.manage')), false);
});

test('an empty ability array never grants access', () => {
    assert.equal(isRouteAbilitySatisfied([], granted()), false);
});
