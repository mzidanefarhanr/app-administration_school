<script setup>
import { onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { authStore } from '@/stores/authStore';
import { useRouter } from 'vue-router';

const { t } = useI18n();
const useAuthStore = authStore();
const router = useRouter();

onMounted(async () => {
    await useAuthStore.logOut();
    router.push({ path: '/auth/login' });
});
</script>

<template>
    <div class="flex items-center justify-center min-h-screen">
        <div class="text-center">
            <i class="pi pi-spin pi-spinner text-4xl text-primary mb-3"></i>
            <p class="text-lg font-medium text-surface-600 dark:text-surface-400">{{ t('common.errorPage.signingOut') }}</p>
        </div>
    </div>
</template>
