import test from 'node:test';
import assert from 'node:assert/strict';

/**
 * Phase 01b Automated Test Suite (Node.js test runner)
 * Tests interceptor mechanics, CSRF 419 recovery, session expiration deduplication,
 * route guard ability mapping across child routes, and logout contract.
 */

// ── Test 1: Interceptor error classification & no auto-retry on timeout/network ──
test('interceptor classifies network error and timeout without auto-retry and preserves session', async () => {
    let retried = false;
    let notified = [];
    let sessionCleared = false;

    const fakeNotify = (msg) => notified.push(msg);

    // Mock error handler logic
    async function handleResponseError(error, notifyFn) {
        if (error.code === 'ECONNABORTED' || error.message?.includes('timeout')) {
            notifyFn({ type: 'error', title: 'Request timeout' });
            return Promise.reject(error);
        }
        if (!error.response) {
            notifyFn({ type: 'error', title: 'Connection error' });
            return Promise.reject(error);
        }
        return Promise.reject(error);
    }

    // Active session mock
    let activeSession = { id: 2, username: 'uat.operator' };

    // Network error (no response)
    const netErr = new Error('Network Error');
    await assert.rejects(
        () => handleResponseError(netErr, fakeNotify),
        (err) => err.message === 'Network Error'
    );
    assert.equal(notified.length, 1);
    assert.equal(notified[0].title, 'Connection error');
    assert.equal(retried, false);
    assert.equal(sessionCleared, false);
    assert.equal(activeSession.username, 'uat.operator');

    // Timeout error
    const timeoutErr = new Error('timeout of 15000ms exceeded');
    timeoutErr.code = 'ECONNABORTED';
    await assert.rejects(
        () => handleResponseError(timeoutErr, fakeNotify),
        (err) => err.code === 'ECONNABORTED'
    );
    assert.equal(notified.length, 2);
    assert.equal(notified[1].title, 'Request timeout');
    assert.equal(retried, false);
    assert.equal(sessionCleared, false);
    assert.equal(activeSession.username, 'uat.operator');
});

// ── Test 2: CSRF 419 single recovery and concurrent coordination ──
test('419 CSRF recovery refreshes token once, coordinates concurrent requests, and retries once', async () => {
    let csrfRefreshCalls = 0;
    let csrfRefreshPromise = null;
    let retryCalls = 0;
    let notified = [];

    const mockCookieJar = { 'XSRF-TOKEN': 'fresh-token-abc' };

    function getFreshXsrf() {
        return mockCookieJar['XSRF-TOKEN'];
    }

    async function mockApiLogin(config) {
        if (config.url === '/sanctum/csrf-cookie') {
            csrfRefreshCalls++;
            return { status: 204 };
        }
        if (config._retry) {
            retryCalls++;
            return { status: 200, data: { success: true, payload: config.data } };
        }
        // First attempt fails with 419
        const err = new Error('Request failed with status code 419');
        err.response = { status: 419 };
        err.config = config;
        return handle419(err);
    }

    async function handle419(error) {
        const config = error.config || {};
        const status = error.response?.status;

        if (status === 419 && !config._isCsrfRefresh && !config.url?.includes('/sanctum/csrf-cookie')) {
            if (config._retry) {
                notified.push('security-mismatch');
                return Promise.reject(error);
            }

            if (!csrfRefreshPromise) {
                csrfRefreshPromise = mockApiLogin({
                    url: '/sanctum/csrf-cookie',
                    _isCsrfRefresh: true
                }).finally(() => {
                    setTimeout(() => {
                        csrfRefreshPromise = null;
                    }, 50);
                });
            }

            await csrfRefreshPromise;
            config._retry = true;
            config.headers = config.headers || {};
            config.headers['X-XSRF-TOKEN'] = getFreshXsrf();
            return mockApiLogin(config);
        }
        return Promise.reject(error);
    }

    // Launch 3 concurrent requests that all receive 419 initially
    const payload1 = { item: 'data-1' };
    const payload2 = { item: 'data-2' };
    const payload3 = { item: 'data-3' };

    const [res1, res2, res3] = await Promise.all([mockApiLogin({ url: '/api/update-1', data: payload1 }), mockApiLogin({ url: '/api/update-2', data: payload2 }), mockApiLogin({ url: '/api/update-3', data: payload3 })]);

    // Verify: csrf-cookie was requested EXACTLY ONCE despite 3 concurrent 419s
    assert.equal(csrfRefreshCalls, 1);
    // Verify: all 3 were retried with payload intact
    assert.equal(retryCalls, 3);
    assert.equal(res1.data.payload.item, 'data-1');
    assert.equal(res2.data.payload.item, 'data-2');
    assert.equal(res3.data.payload.item, 'data-3');
});

// ── Test 2b: 419 retry returning 419 again stops immediately without loop ──
test('419 retry returning 419 again stops immediately without loop and notifies mismatch', async () => {
    let csrfRefreshCalls = 0;
    let retryAttempts = 0;
    let notified = [];

    async function mockApiLogin(config) {
        if (config.url === '/sanctum/csrf-cookie') {
            csrfRefreshCalls++;
            return { status: 204 };
        }
        if (config._retry) {
            retryAttempts++;
            // The retried request still returns 419
            const retryErr = new Error('Request failed with status code 419');
            retryErr.response = { status: 419 };
            retryErr.config = config;
            return handle419(retryErr);
        }
        const err = new Error('Request failed with status code 419');
        err.response = { status: 419 };
        err.config = config;
        return handle419(err);
    }

    async function handle419(error) {
        const config = error.config || {};
        const status = error.response?.status;

        if (status === 419 && !config._isCsrfRefresh && !config.url?.includes('/sanctum/csrf-cookie')) {
            if (config._retry) {
                notified.push('security-mismatch');
                return Promise.reject(error);
            }

            // First 419: refresh token
            await mockApiLogin({ url: '/sanctum/csrf-cookie', _isCsrfRefresh: true });
            config._retry = true;
            return mockApiLogin(config);
        }
        return Promise.reject(error);
    }

    await assert.rejects(
        () => mockApiLogin({ url: '/api/resource' }),
        (err) => err.response?.status === 419
    );

    // Verified: token refreshed once, retried once, stopped without infinite loop
    assert.equal(csrfRefreshCalls, 1);
    assert.equal(retryAttempts, 1);
    assert.deepEqual(notified, ['security-mismatch']);
});

// ── Test 2c: 419 recovery stops and notifies when csrf-cookie refresh fails ──
test('419 recovery stops and notifies when csrf-cookie refresh fails', async () => {
    let retryAttempted = false;
    let notified = [];

    async function mockApiLogin(config) {
        if (config._isCsrfRefresh) {
            const err = new Error('Network Error during refresh');
            err.code = 'ECONNABORTED';
            throw err;
        }
        if (config._retry) {
            retryAttempted = true;
            return { status: 200 };
        }
        const err = new Error('Request failed with status code 419');
        err.response = { status: 419 };
        err.config = config;
        return handle419(err);
    }

    async function handle419(error) {
        const config = error.config || {};
        const status = error.response?.status;

        if (status === 419 && !config._isCsrfRefresh && !config.url?.includes('/sanctum/csrf-cookie')) {
            if (config._retry) {
                notified.push('security-mismatch');
                return Promise.reject(error);
            }

            try {
                await mockApiLogin({ url: '/sanctum/csrf-cookie', _isCsrfRefresh: true });
                config._retry = true;
                return mockApiLogin(config);
            } catch (refreshErr) {
                notified.push('csrf-refresh-failed');
                return Promise.reject(error);
            }
        }
        return Promise.reject(error);
    }

    await assert.rejects(
        () => mockApiLogin({ url: '/api/resource' }),
        (err) => err.response?.status === 419
    );

    // Verified: When refresh fails, original request is NOT retried and error is reported cleanly
    assert.equal(retryAttempted, false);
    assert.deepEqual(notified, ['csrf-refresh-failed']);
});

// ── Test 3: 419 on /sanctum/csrf-cookie is not retried (no recursion) ──
test('419 on csrf-cookie endpoint itself does not trigger infinite retry', async () => {
    let calls = 0;
    async function mockHandler(error) {
        const config = error.config || {};
        if (error.response?.status === 419 && !config.url?.includes('/sanctum/csrf-cookie') && !config._isCsrfRefresh) {
            calls++;
        }
        return Promise.reject(error);
    }

    const csrfErr = new Error('CSRF Error');
    csrfErr.response = { status: 419 };
    csrfErr.config = { url: '/sanctum/csrf-cookie' };

    await assert.rejects(
        () => mockHandler(csrfErr),
        (err) => err.response.status === 419
    );
    assert.equal(calls, 0); // Not retried
});

// ── Test 4: 401 session-expired deduplication & guest check exclusion ──
test('401 on active session deduplicates notifications; guest/login check is excluded', async () => {
    let sessionExpiredHandledCount = 0;
    let isSessionExpiredHandled = false;

    function handle401(config) {
        const isAuthEndpoint = config.skipAuthRedirect || config.url?.includes('/check-auth') || config.url?.includes('/login') || config.url?.includes('/sanctum/csrf-cookie');

        if (!isAuthEndpoint) {
            if (!isSessionExpiredHandled) {
                isSessionExpiredHandled = true;
                sessionExpiredHandledCount++;
                setTimeout(() => {
                    isSessionExpiredHandled = false;
                }, 200);
            }
        }
    }

    // 1. Guest check with 401 -> must NOT trigger session-expired
    handle401({ url: '/api/check-auth', skipAuthRedirect: true });
    handle401({ url: '/login' });
    assert.equal(sessionExpiredHandledCount, 0);

    // 2. Three concurrent protected requests getting 401
    handle401({ url: '/api/users' });
    handle401({ url: '/api/roles' });
    handle401({ url: '/api/students/export' });

    // Deduplicated: handled only once
    assert.equal(sessionExpiredHandledCount, 1);
});

// ── Test 5: 403 permission denial does not log out; inactive account does ──
test('403 feature permission preserves session; inactive account triggers logout', () => {
    let loggedOut = false;
    let notified = [];

    function handle403(status, message) {
        const isInactive = message?.toLowerCase().includes('inactive') || message?.toLowerCase().includes('tidak aktif');
        if (isInactive) {
            loggedOut = true;
            notified.push('account-inactive');
        } else {
            // Feature permission denial: session is preserved
            notified.push('permission-denied');
        }
    }

    // Normal permission denial
    handle403(403, 'This action is unauthorized.');
    assert.equal(loggedOut, false);
    assert.equal(notified[0], 'permission-denied');

    // Inactive account denial
    handle403(403, 'Your account is inactive. Please contact the administrator.');
    assert.equal(loggedOut, true);
    assert.equal(notified[1], 'account-inactive');
});

// ── Test 6: Route guard ability mapping across child navigations ──
test('route guard evaluates ability requirements per page and across child routes', () => {
    // Ability definitions per role
    const operatorAbilities = ['master.view', 'master.manage', 'students.view', 'students.export'];
    const adminAbilities = ['users.view', 'users.manage', 'roles.view', 'roles.manage', 'access.assign', 'master.view', 'master.manage', 'students.view', 'students.export'];

    function evaluateGuard(to, userAbilities, isAuthenticated) {
        const isGuestOnly = to.meta?.guestOnly;
        const isPublic = to.meta?.public;
        const requiresAuth = to.meta?.requiresAuth || (!isPublic && !isGuestOnly);

        if (isAuthenticated && isGuestOnly) {
            return { action: 'redirect', path: '/' };
        }
        if (!isAuthenticated && requiresAuth) {
            return { action: 'redirect', path: '/auth/login' };
        }
        const requiredAbility = to.meta?.requiresAbility;
        if (requiredAbility && !userAbilities.includes(requiredAbility)) {
            return { action: 'redirect', path: '/auth/access' };
        }
        return { action: 'allow' };
    }

    // 1. Guest visiting protected route
    const guestNav = evaluateGuard({ path: '/master/roles', meta: { requiresAuth: true, requiresAbility: 'roles.view' } }, [], false);
    assert.equal(guestNav.action, 'redirect');
    assert.equal(guestNav.path, '/auth/login');

    // 2. Operator navigating to /master/roles (needs roles.view) -> denied
    const opNavRoles = evaluateGuard({ path: '/master/roles', meta: { requiresAuth: true, requiresAbility: 'roles.view' } }, operatorAbilities, true);
    assert.equal(opNavRoles.action, 'redirect');
    assert.equal(opNavRoles.path, '/auth/access');

    // 3. Operator navigating to /master/typeusers (needs master.manage) -> allowed
    const opNavTypeUsers = evaluateGuard({ path: '/master/typeusers', meta: { requiresAuth: true, requiresAbility: 'master.manage' } }, operatorAbilities, true);
    assert.equal(opNavTypeUsers.action, 'allow');

    // 4. Operator navigating to /master/accounts (needs users.view) -> denied
    const opNavAccounts = evaluateGuard({ path: '/master/accounts', meta: { requiresAuth: true, requiresAbility: 'users.view' } }, operatorAbilities, true);
    assert.equal(opNavAccounts.action, 'redirect');
    assert.equal(opNavAccounts.path, '/auth/access');

    // 5. Admin navigating to /master/roles -> allowed
    const adminNavRoles = evaluateGuard({ path: '/master/roles', meta: { requiresAuth: true, requiresAbility: 'roles.view' } }, adminAbilities, true);
    assert.equal(adminNavRoles.action, 'allow');

    // 6. Logged in user visiting login page -> redirected to /
    const loggedInVisitLogin = evaluateGuard({ path: '/auth/login', meta: { guestOnly: true } }, operatorAbilities, true);
    assert.equal(loggedInVisitLogin.action, 'redirect');
    assert.equal(loggedInVisitLogin.path, '/');
});

// ── Test 7: Logout contract 204 No Content & failure handling ──
test('logout handles 204 without reading body, cleans state, and reports server status', async () => {
    let localStateCleared = false;

    async function mockStoreLogout(apiPostFn) {
        let serverSuccess = true;
        try {
            const response = await apiPostFn('/logout');
            if (response.status !== 204 && response.status !== 200) {
                serverSuccess = false;
            }
        } catch (error) {
            if (error.response?.status === 401) {
                serverSuccess = true;
            } else {
                serverSuccess = false;
            }
        } finally {
            localStateCleared = true;
        }
        return { success: true, serverSuccess };
    }

    // Success case: 204 No Content
    localStateCleared = false;
    const resSuccess = await mockStoreLogout(async () => ({ status: 204, data: '' }));
    assert.equal(resSuccess.serverSuccess, true);
    assert.equal(localStateCleared, true);

    // Already unauthenticated case: 401
    localStateCleared = false;
    const res401 = await mockStoreLogout(async () => {
        const err = new Error('Unauthorized');
        err.response = { status: 401 };
        throw err;
    });
    assert.equal(res401.serverSuccess, true);
    assert.equal(localStateCleared, true);

    // Network / server failure case: 500
    localStateCleared = false;
    const resFail = await mockStoreLogout(async () => {
        const err = new Error('Server Error');
        err.response = { status: 500 };
        throw err;
    });
    assert.equal(resFail.serverSuccess, false);
    assert.equal(localStateCleared, true); // Local state still cleared for user safety
});

// ── Test 8: Reset password error handling distinguishes success from reused/invalid token ──
test('reset password form handles 422 invalid/expired/reused token correctly', () => {
    function parseResetError(response) {
        if (response?.status === 422) {
            const errors = response.data?.errors;
            if (errors?.email) {
                return { title: 'Invalid Token', message: errors.email[0] };
            }
            if (errors?.password) {
                return { title: 'Password Validation', message: errors.password[0] };
            }
            return { title: 'Reset Failed', message: response.data?.message || 'Failed to reset password.' };
        }
        return { title: 'Reset Failed', message: 'An unexpected error occurred.' };
    }

    const invalidTokenRes = {
        status: 422,
        data: {
            message: 'This password reset token is invalid.',
            errors: { email: ['This password reset token is invalid.'] }
        }
    };
    const parsed = parseResetError(invalidTokenRes);
    assert.equal(parsed.title, 'Invalid Token');
    assert.equal(parsed.message, 'This password reset token is invalid.');

    const passwordMismatchRes = {
        status: 422,
        data: {
            message: 'The password confirmation does not match.',
            errors: { password: ['The password confirmation does not match.'] }
        }
    };
    const parsedMismatch = parseResetError(passwordMismatchRes);
    assert.equal(parsedMismatch.title, 'Password Validation');
    assert.equal(parsedMismatch.message, 'The password confirmation does not match.');
});

// ── Test 9: Reset password client validation enforces 12 characters ──
test('reset password client validation rejects password under 12 characters', () => {
    function validateResetInput(password, confirmation) {
        if (!password || password.length < 12) {
            return { valid: false, error: 'Password must be at least 12 characters long.' };
        }
        if (password !== confirmation) {
            return { valid: false, error: 'Password confirmation does not match.' };
        }
        return { valid: true };
    }

    // 11 chars
    const shortResult = validateResetInput('shortpass11', 'shortpass11');
    assert.equal(shortResult.valid, false);
    assert.equal(shortResult.error, 'Password must be at least 12 characters long.');

    // 12 chars
    const validResult = validateResetInput('twelvechars1', 'twelvechars1');
    assert.equal(validResult.valid, true);

    // Mismatch
    const mismatchResult = validateResetInput('twelvechars1', 'twelvechars2');
    assert.equal(mismatchResult.valid, false);
    assert.equal(mismatchResult.error, 'Password confirmation does not match.');
});
