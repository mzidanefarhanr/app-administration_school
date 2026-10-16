<script setup>
import { onMounted } from 'vue';
import { authStore } from '@/stores/authStore';
import { useRouter } from 'vue-router';

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
            <p class="text-lg font-medium text-surface-600 dark:text-surface-400">Signing out...</p>
        </div>
    </div>
</template>
