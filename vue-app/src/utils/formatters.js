/**
 * Standarisasi formatter tampilan visual (Subfase 12c / DEC-47)
 * Bahasa Indonesia (bawaan) atau English mengikuti locale aktif (i18n-1), Timezone WIB, & format konsisten.
 */

import { i18n } from '@/i18n';

const MONTH_NAMES_ID = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const MONTH_NAMES_EN = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

// Locale Intl untuk angka: id-ID (titik ribuan) atau en-GB (koma ribuan). Dibaca saat dipanggil agar reaktif.
const NUMBER_LOCALES = { id: 'id-ID', en: 'en-GB' };

function currentLocale() {
    return i18n.locale.value;
}

/**
 * Format tanggal ke standar DD/MM/YYYY atau D MMMM YYYY
 * @param {string|Date} dateVal
 * @param {'short'|'long'} mode
 * @returns {string}
 */
export function formatDate(dateVal, mode = 'short') {
    if (!dateVal) return '-';

    const d = new Date(dateVal);
    if (isNaN(d.getTime())) return typeof dateVal === 'string' ? dateVal : '-';

    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();

    if (mode === 'long') {
        const monthName = (currentLocale() === 'en' ? MONTH_NAMES_EN : MONTH_NAMES_ID)[d.getMonth()];
        return `${d.getDate()} ${monthName} ${year}`;
    }

    return `${day}/${month}/${year}`;
}

/**
 * Format waktu ke standar HH:mm WIB
 * @param {string|Date} timeVal
 * @returns {string}
 */
export function formatTime(timeVal) {
    if (!timeVal) return '-';

    if (typeof timeVal === 'string' && timeVal.includes(':')) {
        const parts = timeVal.trim().split(':');
        if (parts.length >= 2) {
            return `${parts[0].padStart(2, '0')}:${parts[1].padStart(2, '0')} WIB`;
        }
    }

    const d = new Date(timeVal);
    if (isNaN(d.getTime())) return '-';

    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');
    return `${hours}:${minutes} WIB`;
}

/**
 * Format tanggal dan waktu ke standar DD/MM/YYYY HH:mm WIB
 * @param {string|Date} dateTimeVal
 * @returns {string}
 */
export function formatDateTime(dateTimeVal) {
    if (!dateTimeVal) return '-';

    const d = new Date(dateTimeVal);
    if (isNaN(d.getTime())) return '-';

    const dateStr = formatDate(d, 'short');
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');
    return `${dateStr} ${hours}:${minutes} WIB`;
}

/**
 * Format angka numerik ke standar ribuan sesuai locale aktif (Indonesia: titik, English: koma)
 * @param {number|string} numVal
 * @returns {string}
 */
export function formatNumber(numVal) {
    if (numVal === null || numVal === undefined || numVal === '') return '-';
    const n = Number(numVal);
    if (isNaN(n)) return String(numVal);
    return new Intl.NumberFormat(NUMBER_LOCALES[currentLocale()] || NUMBER_LOCALES.id).format(n);
}

/**
 * Format persentase dengan 1 angka desimal
 * @param {number|string} val
 * @returns {string}
 */
export function formatPercentage(val) {
    if (val === null || val === undefined || val === '') return '0.0%';
    const n = Number(val);
    if (isNaN(n)) return '0.0%';
    return `${n.toFixed(1)}%`;
}

/**
 * Placeholder nilai kosong (strip '-')
 * @param {*} val
 * @returns {string}
 */
export function emptyPlaceholder(val) {
    if (val === null || val === undefined || val === '' || val === 'null' || val === 'undefined') {
        return '-';
    }
    return String(val);
}

export default {
    formatDate,
    formatTime,
    formatDateTime,
    formatNumber,
    formatPercentage,
    emptyPlaceholder
};
