import { computed } from 'vue';

import { SUPPORTED_LOCALES, i18n, setLocale } from '@/i18n';

/**
 * Komposabel untuk komponen: bahasa aktif yang dapat di-bind (`v-model`) dan daftar bahasa.
 */
export function useLocale() {
    const locale = computed({
        get: () => i18n.locale.value,
        set: (value) => {
            setLocale(value);
        }
    });

    return {
        locale,
        locales: SUPPORTED_LOCALES,
        setLocale
    };
}
