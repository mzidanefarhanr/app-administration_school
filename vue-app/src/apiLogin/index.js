import { notify } from '@kyvg/vue3-notification';
import axios from 'axios';

// Base URL API WAJIB berasal dari konfigurasi (VITE_API_BASE_URL).
// Tidak ada fallback diam-diam ke alamat lama: bila tidak tersedia/valid,
// tampilkan kesalahan konfigurasi yang jelas dan jangan mengirim request.
const configuredBaseUrl = import.meta.env.VITE_API_BASE_URL;

const isValidBaseUrl = (value) => {
    if (typeof value !== 'string' || value.trim() === '') return false;
    try {
        const url = new URL(value);
        return url.protocol === 'http:' || url.protocol === 'https:';
    } catch {
        return false;
    }
};

if (!isValidBaseUrl(configuredBaseUrl)) {
    notify({
        type: 'error',
        title: 'Configuration error',
        text: 'VITE_API_BASE_URL is missing or invalid. Set it to your API base URL (e.g. http://127.0.0.1:8001) before running the app.',
        duration: -1
    });
    throw new Error('Configuration error: VITE_API_BASE_URL is missing or invalid.');
}

const apiLogin = axios.create({
    baseURL: configuredBaseUrl,
    withCredentials: true,
    withXSRFToken: true,
    timeout: 15000 // 15s timeout
});

apiLogin.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
apiLogin.defaults.headers.common['Accept'] = 'application/json';

// Single shared promise to coordinate concurrent CSRF 419 refreshes
let csrfRefreshPromise = null;

// Deduplicate concurrent 401 session-expired notifications/redirects
let isSessionExpiredHandled = false;

function getXsrfCookie() {
    if (typeof document === 'undefined') return null;
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : null;
}

// Callbacks registered from main.js to handle session expiration and inactive account cleanly without circular imports
let onSessionExpiredHandler = null;
let onAccountInactiveHandler = null;

export function registerAuthHandlers(handlers = {}) {
    if (handlers.onSessionExpired) onSessionExpiredHandler = handlers.onSessionExpired;
    if (handlers.onAccountInactive) onAccountInactiveHandler = handlers.onAccountInactive;
}

apiLogin.interceptors.response.use(
    (response) => response,
    async (error) => {
        const config = error.config || {};

        // 1. Timeout / Network error (no response)
        if (error.code === 'ECONNABORTED' || error.message?.includes('timeout')) {
            notify({
                type: 'error',
                title: 'Request timeout',
                text: 'The request took too long to respond. Please check your connection and try again.'
            });
            return Promise.reject(error);
        }

        if (!error.response) {
            notify({
                type: 'error',
                title: 'Connection error',
                text: 'Could not reach the API server. Check that it is running and reachable.'
            });
            return Promise.reject(error);
        }

        const status = error.response.status;

        // 2. CSRF Token Mismatch (419) — single coordinated retry
        if (status === 419 && !config._isCsrfRefresh && !config.url?.includes('/sanctum/csrf-cookie')) {
            if (config._retry) {
                notify({
                    type: 'error',
                    title: 'Security token mismatch',
                    text: 'Security validation failed. Please refresh the page and try again.'
                });
                return Promise.reject(error);
            }

            if (!csrfRefreshPromise) {
                csrfRefreshPromise = apiLogin
                    .get('/sanctum/csrf-cookie', {
                        skipAuthRedirect: true,
                        _isCsrfRefresh: true
                    })
                    .finally(() => {
                        setTimeout(() => {
                            csrfRefreshPromise = null;
                        }, 100);
                    });
            }

            try {
                await csrfRefreshPromise;
                config._retry = true;
                const freshXsrf = getXsrfCookie();
                if (freshXsrf && config.headers) {
                    config.headers['X-XSRF-TOKEN'] = freshXsrf;
                }
                return apiLogin(config);
            } catch (refreshErr) {
                notify({
                    type: 'error',
                    title: 'Security validation expired',
                    text: 'Failed to refresh security token. Please refresh the page and try again.'
                });
                return Promise.reject(error);
            }
        }

        // 3. 401 Unauthorized / Session Expired
        if (status === 401) {
            const isAuthEndpoint =
                config.skipAuthRedirect ||
                config.url?.includes('/check-auth') ||
                config.url?.includes('/login') ||
                config.url?.includes('/sanctum/csrf-cookie');

            if (!isAuthEndpoint) {
                if (!isSessionExpiredHandled) {
                    isSessionExpiredHandled = true;
                    try {
                        sessionStorage.setItem('auth_flash_message', 'Your session has expired. Please sign in again.');
                    } catch (_) {
                        // ignore storage errors
                    }

                    notify({
                        type: 'error',
                        title: 'Session expired',
                        text: 'Your session has expired. Please sign in again.',
                        duration: 6000
                    });

                    if (onSessionExpiredHandler) {
                        onSessionExpiredHandler();
                    } else if (typeof window !== 'undefined' && !window.location.pathname.startsWith('/auth/login')) {
                        window.location.href = '/auth/login';
                    }

                    setTimeout(() => {
                        isSessionExpiredHandled = false;
                    }, 3000);
                }
            }
            return Promise.reject(error);
        }

        // 4. 403 Forbidden: differentiate inactive account from permission denial
        if (status === 403) {
            const msg = (error.response.data?.message || '').toLowerCase();
            const isInactive = msg.includes('inactive') || msg.includes('tidak aktif');

            if (isInactive) {
                notify({
                    type: 'error',
                    title: 'Account inactive',
                    text: error.response.data?.message || 'Your account is inactive. Please contact the administrator.',
                    duration: 6000
                });
                if (onAccountInactiveHandler) {
                    onAccountInactiveHandler(error.response.data?.message);
                } else if (typeof window !== 'undefined' && !window.location.pathname.startsWith('/auth/login')) {
                    window.location.href = '/auth/login';
                }
            }
            // For feature permission denial: do NOT log out or redirect. Let caller handle.
            return Promise.reject(error);
        }

        // 5. 429 Too Many Requests
        if (status === 429) {
            notify({
                type: 'warn',
                title: 'Too many requests',
                text: 'Too many requests. Please wait a moment and try again.'
            });
            return Promise.reject(error);
        }

        // 6. 5xx Server Error
        if (status >= 500) {
            notify({
                type: 'error',
                title: 'Server error',
                text: 'An unexpected server error occurred. Please try again later.'
            });
            return Promise.reject(error);
        }

        // 7. 422 and others: domain-specific, passed to caller
        return Promise.reject(error);
    }
);

export default apiLogin;
