import { notify } from '@kyvg/vue3-notification';
import axios from 'axios';
import { enrichReferenceResponse } from '@/utils/referenceLabels.mjs';
import { i18n } from '@/i18n';

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
    (response) => {
        response.data = enrichReferenceResponse(response.data, response.config?.url);
        response.correlationId = response.headers?.['x-correlation-id'] || response.data?.correlation_id;
        return response;
    },
    async (error) => {
        const config = error.config || {};
        const correlationId = error.response?.headers?.['x-correlation-id'] || error.response?.data?.correlation_id;
        if (correlationId) {
            error.correlationId = correlationId;
        }

        // 1. Timeout / Network error (no response)
        if (error.code === 'ECONNABORTED' || error.message?.includes('timeout')) {
            notify({
                type: 'error',
                title: i18n.t('common.api.timeoutTitle'),
                text: i18n.t('common.api.timeoutText')
            });
            return Promise.reject(error);
        }

        if (!error.response) {
            notify({
                type: 'error',
                title: i18n.t('common.api.connectionErrorTitle'),
                text: i18n.t('common.api.connectionErrorText')
            });
            return Promise.reject(error);
        }

        const status = error.response.status;

        // 2. CSRF Token Mismatch (419) — single coordinated retry
        if (status === 419 && !config._isCsrfRefresh && !config.url?.includes('/sanctum/csrf-cookie')) {
            if (config._retry) {
                notify({
                    type: 'error',
                    title: i18n.t('common.api.csrfMismatchTitle'),
                    text: i18n.t('common.api.csrfMismatchText')
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
                    title: i18n.t('common.api.csrfExpiredTitle'),
                    text: i18n.t('common.api.csrfExpiredText')
                });
                return Promise.reject(error);
            }
        }

        // 3. 401 Unauthorized / Session Expired
        if (status === 401) {
            const isAuthEndpoint = config.skipAuthRedirect || config.url?.includes('/check-auth') || config.url?.includes('/login') || config.url?.includes('/sanctum/csrf-cookie');

            if (!isAuthEndpoint) {
                if (!isSessionExpiredHandled) {
                    isSessionExpiredHandled = true;
                    try {
                        sessionStorage.setItem('auth_flash_message', i18n.t('common.api.sessionExpiredText'));
                    } catch (_) {
                        // ignore storage errors
                    }

                    notify({
                        type: 'error',
                        title: i18n.t('common.api.sessionExpiredTitle'),
                        text: i18n.t('common.api.sessionExpiredText'),
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
                    title: i18n.t('common.api.accountInactiveTitle'),
                    text: error.response.data?.message || i18n.t('common.api.accountInactiveText'),
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
                title: i18n.t('common.api.tooManyRequestsTitle'),
                text: i18n.t('common.api.tooManyRequestsText')
            });
            return Promise.reject(error);
        }

        // 6. 5xx Server Error
        if (status >= 500) {
            const refText = correlationId ? i18n.t('common.api.referenceId', { id: correlationId }) : '';
            const errorMsg = error.response?.data?.message || i18n.t('common.api.serverErrorText');
            notify({
                type: 'error',
                title: i18n.t('common.api.serverErrorTitle'),
                text: `${errorMsg}${refText}`,
                duration: 8000
            });
            return Promise.reject(error);
        }

        // 7. 422 and others: domain-specific, passed to caller
        return Promise.reject(error);
    }
);

export default apiLogin;
