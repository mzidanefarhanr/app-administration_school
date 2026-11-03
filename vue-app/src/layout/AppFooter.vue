<script setup>
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <div class="layout-footer">{{ t('layout.footer.createdBy') }} <b class="cursor-pointer">Muhamad Zidane Farhan Ramadhan, S.Kom.</b> {{ t('layout.footer.notAcquired') }}.</div>
</template>
