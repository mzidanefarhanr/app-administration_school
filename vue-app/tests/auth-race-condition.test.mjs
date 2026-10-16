import test from 'node:test';
import assert from 'node:assert/strict';

/**
 * Deterministic test for auth identity initialization & race condition handling.
 * Validates that an in-flight guest check resolving late CANNOT overwrite
 * an authenticated session established by a newer login action.
 */
let requestSequence = 0;

class MockAuthStore {
    constructor() {
        this._requestId = 0;
        this.identity = null;
        this.hasLogin = null;
        this.abilities = [];
    }

    get user() {
        return this.identity;
    }

    get authUser() {
        return this.identity;
    }

    get AllUserAssign() {
        return this.identity;
    }

    can(ability) {
        return Array.isArray(this.abilities) && this.abilities.includes(ability);
    }

    clearIdentity() {
        this.identity = null;
        this.abilities = [];
    }

    $reset() {
        this._requestId = 0;
        this.clearIdentity();
        this.hasLogin = null;
    }

    async fetchIdentity(apiCall) {
        const currentRequestId = ++requestSequence;
        this._requestId = currentRequestId;
        try {
            const response = await apiCall();

            // Discard stale in-flight response
            if (currentRequestId !== requestSequence) {
                return this.identity;
            }

            if (response?.data?.authenticated && response?.data?.user) {
                this.identity = response.data.user;
                this.abilities = response.data.user.abilities || [];
                this.hasLogin = true;
                return this.identity;
            } else {
                this.clearIdentity();
                this.hasLogin = false;
                return null;
            }
        } catch (error) {
            if (currentRequestId !== requestSequence) {
                return this.identity;
            }
            if (error.response?.status === 401) {
                this.clearIdentity();
                this.hasLogin = false;
            } else if (!this.identity) {
                this.hasLogin = false;
            }
            return null;
        }
    }
}

test('getters user, authUser, and AllUserAssign consistently resolve to identity', () => {
    const store = new MockAuthStore();
    const testUser = {
        id: 2,
        name: 'UAT Operator Sekolah',
        username: 'uat.operator',
        status_user_id: 1,
        status_active: true,
        abilities: ['students.view', 'students.export'],
        roles: ['operator-sekolah']
    };

    store.identity = testUser;
    store.abilities = testUser.abilities;

    assert.deepEqual(store.user, testUser);
    assert.deepEqual(store.authUser, testUser);
    assert.deepEqual(store.AllUserAssign, testUser);
    assert.equal(store.can('students.export'), true);
    assert.equal(store.can('users.manage'), false);
});

test('out-of-order guest response does not overwrite post-login authenticated session', async () => {
    const store = new MockAuthStore();

    let resolveGuestCheck;
    const guestCheckPromise = new Promise((resolve) => {
        resolveGuestCheck = resolve;
    });

    // 1. Router / guest check starts (Request #1)
    const req1 = store.fetchIdentity(() => guestCheckPromise);
    assert.equal(store._requestId, 1);

    // 2. User submits login -> fast response from server returns authenticated session (Request #2)
    const authData = {
        data: {
            authenticated: true,
            user: {
                id: 2,
                name: 'UAT Operator Sekolah',
                username: 'uat.operator',
                status_active: true,
                abilities: ['students.view']
            }
        }
    };
    const req2 = store.fetchIdentity(async () => authData);
    assert.equal(store._requestId, 2);

    await req2;
    assert.equal(store.hasLogin, true);
    assert.equal(store.identity?.username, 'uat.operator');

    // 3. Delayed guest check finally arrives late with authenticated: false
    resolveGuestCheck({
        data: {
            authenticated: false,
            user: null
        }
    });

    await req1;

    // Verify: stale guest response was discarded and did NOT wipe out login
    assert.equal(store.hasLogin, true);
    assert.equal(store.identity?.username, 'uat.operator');
    assert.equal(store.authUser?.username, 'uat.operator');
});

test('stale error response does not overwrite active session', async () => {
    const store = new MockAuthStore();

    let rejectStale;
    const stalePromise = new Promise((_, reject) => {
        rejectStale = reject;
    });

    // Slow request #1
    const req1 = store.fetchIdentity(() => stalePromise);

    // Fast request #2
    const req2 = store.fetchIdentity(async () => ({
        data: {
            authenticated: true,
            user: { id: 1, username: 'uat.admin' }
        }
    }));

    await req2;
    assert.equal(store.identity?.username, 'uat.admin');

    // Reject slow request #1 with 401
    const err = new Error('Unauthorized');
    err.response = { status: 401 };
    rejectStale(err);

    await req1;

    // Identity is preserved
    assert.equal(store.hasLogin, true);
    assert.equal(store.identity?.username, 'uat.admin');
});

test('stale response from prior account does not overwrite newly logged in account on account switch', async () => {
    const store = new MockAuthStore();

    let resolvePriorAccount;
    const priorAccountPromise = new Promise((resolve) => {
        resolvePriorAccount = resolve;
    });

    // 1. Account A (uat.operator) starts an identity fetch that is slow / hung
    const reqAccountA = store.fetchIdentity(() => priorAccountPromise);

    // 2. User logs out and logs in as Account B (uat.admin)
    store.$reset();
    const accountBData = {
        data: {
            authenticated: true,
            user: { id: 1, username: 'uat.admin', abilities: ['roles.view'] }
        }
    };
    const reqAccountB = store.fetchIdentity(async () => accountBData);

    await reqAccountB;
    assert.equal(store.hasLogin, true);
    assert.equal(store.identity?.username, 'uat.admin');

    // 3. The delayed response from Account A finally arrives
    resolvePriorAccount({
        data: {
            authenticated: true,
            user: { id: 2, username: 'uat.operator', abilities: ['students.export'] }
        }
    });

    await reqAccountA;

    // Verify: Stale Account A data was safely discarded; Account B identity is preserved
    assert.equal(store.hasLogin, true);
    assert.equal(store.identity?.username, 'uat.admin');
    assert.equal(store.identity?.id, 1);
});
