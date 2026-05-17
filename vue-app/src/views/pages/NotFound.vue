<script setup>
import FloatingConfigurator from '@/components/FloatingConfigurator.vue';
import logoDefault from '@/assets/pictures/logo_default.jpg';
</script>

<template>
    <!-- <FloatingConfigurator /> -->
    <div class="flex items-center justify-center min-h-screen overflow-hidden">
        <div class="flex flex-col items-center justify-center">
            <!-- <img :src="logoDefault" alt="Logo Sekolah" class="mt-4 mb-8 w-32 shrink-0" /> -->

            <div style="border-radius: 56px; padding: 0.3rem; background: linear-gradient(180deg, color-mix(in srgb, var(--primary-color), transparent 60%) 10%, var(--surface-ground) 30%)">
                <div class="w-full bg-surface-0 dark:bg-surface-900 py-10 px-8 sm:px-20 flex flex-col items-center" style="border-radius: 53px">
                    <span class="text-primary font-bold text-9xl">404</span>
                    <h1 class="text-surface-900 dark:text-surface-0 font-bold text-3xl lg:text-5xl mb-2">UH OH! You're lost.</h1>
                    <span class="text-surface-900 dark:text-surface-0 lg:text-xl font-medium mb-0 block"
                        >The page you are looking for does not exist. <br />
                        How you got here is a mystery. <br />
                        But you can click the button below to go back to the homepage.</span
                    >
                    <div class="text-surface-600 dark:text-surface-200 mb-8">Pages Not Found!!</div>

                    <Button as="router-link" label="Dashboard" to="/" severity="success" rounded size="large" />
                </div>
            </div>
        </div>
    </div>
</template>
