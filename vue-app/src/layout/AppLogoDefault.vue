<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLayout } from '@/layout/composables/layout';

const { isDarkTheme } = useLayout();
const { locale } = useI18n();

const logoSrc = computed(() => {
    const isEn = locale.value === 'en';
    if (isDarkTheme.value) {
        return isEn ? '/logo/alternatif-tagline/sekolario-horizontal-tagline-singkat-on-dark.svg' : '/logo/sekolario-horizontal-on-dark.svg';
    }
    return isEn ? '/logo/alternatif-tagline/sekolario-horizontal-tagline-singkat.svg' : '/logo/sekolario-horizontal.svg';
});
</script>

<template>
    <img :src="logoSrc" alt="Sekolario" class="h-8 w-auto" />
</template>
