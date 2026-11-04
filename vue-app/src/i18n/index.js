import { createI18n } from 'vue-i18n';

import { primevueLocales } from './primevue';

/**
 * Fondasi i18n (Worktree A, i18n-1).
 * - Bahasa bawaan `id`, fallback `id`.
 * - Preferensi disimpan di sisi klien (`localStorage`), tanpa kolom database.
 * - Satu berkas JSON per namespace di `locales/<locale>/<namespace>.json`.
 */

export const DEFAULT_LOCALE = 'id';
export const LOCALE_STORAGE_KEY = 'app.locale';

// Nama bahasa memakai nama aslinya (endonim) agar mudah dikenali di kedua bahasa.
export const SUPPORTED_LOCALES = [
    { value: 'id', label: 'Indonesia' },
    { value: 'en', label: 'English' }
];

const supportedValues = SUPPORTED_LOCALES.map((item) => item.value);

function isSupported(locale) {
    return typeof locale === 'string' && supportedValues.includes(locale);
}

function readStoredLocale() {
    try {
        const stored = window.localStorage.getItem(LOCALE_STORAGE_KEY);
        return isSupported(stored) ? stored : null;
    } catch {
        return null;
    }
}

function writeStoredLocale(locale) {
    try {
        window.localStorage.setItem(LOCALE_STORAGE_KEY, locale);
    } catch {
        // Penyimpanan tidak tersedia (mode privat/diblokir): pilihan berlaku untuk sesi ini saja.
    }
}

// Menyusun pesan dari `locales/<locale>/<namespace>.json` menjadi { id: { namespace: {...} }, en: {...} }.
function buildMessages() {
    const modules = import.meta.glob('./locales/*/*.json', { eager: true, import: 'default' });
    const messages = {};
    for (const [path, content] of Object.entries(modules)) {
        const match = path.match(/\.\/locales\/([^/]+)\/([^/]+)\.json$/);
        if (!match) continue;
        const [, locale, namespace] = match;
        messages[locale] = messages[locale] || {};
        messages[locale][namespace] = content;
    }
    return messages;
}

const vueI18n = createI18n({
    legacy: false,
    locale: readStoredLocale() || DEFAULT_LOCALE,
    fallbackLocale: DEFAULT_LOCALE,
    messages: buildMessages(),
    missingWarn: import.meta.env.DEV,
    fallbackWarn: import.meta.env.DEV
});

/** Instans komposer global (dipakai `formatters.js` dan kode non-komponen). */
export const i18n = vueI18n.global;

let appInstance = null;

// Teks bawaan komponen PrimeVue (kalender, paginator, filter, dsb.) mengikuti bahasa aktif.
// Dipasang lewat config PrimeVue yang reaktif, tanpa mengubah blok konfigurasi `app.use(PrimeVue, ...)`.
function applyPrimeVueLocale(locale) {
    const config = appInstance?.config.globalProperties.$primevue?.config;
    const messages = primevueLocales[locale];
    if (!config || !messages) return;
    config.locale = { ...config.locale, ...messages, aria: { ...config.locale?.aria, ...messages.aria } };
}

function applyDocumentLanguage(locale) {
    if (typeof document !== 'undefined') {
        document.documentElement.setAttribute('lang', locale);
    }
}

export function getLocale() {
    return i18n.locale.value;
}

/**
 * Ganti bahasa tanpa reload. Nilai tak dikenal diabaikan.
 * @param {string} locale 'id' | 'en'
 * @returns {boolean} true bila bahasa berubah/diterapkan
 */
export function setLocale(locale) {
    if (!isSupported(locale)) return false;
    i18n.locale.value = locale;
    writeStoredLocale(locale);
    applyDocumentLanguage(locale);
    applyPrimeVueLocale(locale);
    return true;
}

/**
 * Plugin untuk `app.use(i18n)`: memasang vue-i18n, lalu menerapkan bahasa aktif
 * ke atribut `lang` dokumen dan locale PrimeVue.
 */
const plugin = {
    install(app) {
        vueI18n.install(app);
        appInstance = app;
        applyDocumentLanguage(getLocale());
        // Semua `app.use(...)` di main.js sinkron; PrimeVue sudah terpasang saat microtask ini berjalan.
        queueMicrotask(() => applyPrimeVueLocale(getLocale()));
    },
    global: i18n
};

export default plugin;
