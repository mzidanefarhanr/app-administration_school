<script setup>
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <div class="flex items-center justify-center min-h-screen overflow-hidden">
        <div class="flex flex-col items-center justify-center">
            <div style="border-radius: 56px; padding: 0.3rem; background: linear-gradient(180deg, color-mix(in srgb, var(--primary-color), transparent 60%) 10%, var(--surface-ground) 30%)">
                <div class="w-full bg-surface-0 dark:bg-surface-900 py-10 px-8 sm:px-20 flex flex-col items-center" style="border-radius: 53px">
                    <span class="text-primary font-bold text-9xl">404</span>
                    <h1 class="text-surface-900 dark:text-surface-0 font-bold text-3xl lg:text-5xl mb-2">{{ t('common.errorPage.notFoundTitle') }}</h1>
                    <span class="text-surface-900 dark:text-surface-0 lg:text-xl font-medium mb-0 block text-center max-w-lg">
                        {{ t('common.errorPage.notFoundDesc') }}
                    </span>
                    <div class="text-surface-600 dark:text-surface-200 my-4 text-sm">{{ t('common.errorPage.notFoundHint') }}</div>

                    <Button as="router-link" :label="t('common.errorPage.backToDashboard')" to="/" severity="success" rounded size="large" />
                </div>
            </div>
        </div>
    </div>
</template>
