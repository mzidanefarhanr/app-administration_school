<script setup>
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import FloatLabel from 'primevue/floatlabel';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import { ref } from 'vue';
import apiLogin from '@/apiLogin';
import AppLogoDefault from '@/layout/AppLogoDefault.vue';

const email = ref('');
const isSubmitting = ref(false);

const visibleDanger = ref(false);
const titleDanger = ref('');
const fieldDanger = ref('');

const visibleSuccess = ref(false);
const titleSuccess = ref('');
const fieldSuccess = ref('');

const submitForgotPassword = async () => {
    if (isSubmitting.value) return;

    if (!email.value || !email.value.trim()) {
        titleDanger.value = 'Validation Error';
        fieldDanger.value = 'Please enter your email address.';
        visibleDanger.value = true;
        return;
    }

    isSubmitting.value = true;

    try {
        await apiLogin.post('/forgot-password', {
            email: email.value.trim()
        });

        // Anti-enumeration: always display uniform confirmation
        titleSuccess.value = 'Check Your Email';
        fieldSuccess.value = 'If your email address is registered, you will receive a password reset link shortly.';
        visibleSuccess.value = true;
    } catch (error) {
        if (!error.response) {
            titleDanger.value = 'Connection Error';
            fieldDanger.value = 'Could not reach the server. Please check your connection and try again.';
            visibleDanger.value = true;
        } else if (error.response.status === 422) {
            const errors = error.response.data?.errors;
            if (errors?.email && errors.email[0]?.toLowerCase().includes('valid email')) {
                titleDanger.value = 'Invalid Email';
                fieldDanger.value = 'Please provide a valid email address.';
                visibleDanger.value = true;
            } else {
                // Anti-enumeration: if email is not found, show the same generic message
                titleSuccess.value = 'Check Your Email';
                fieldSuccess.value = 'If your email address is registered, you will receive a password reset link shortly.';
                visibleSuccess.value = true;
            }
        } else if (error.response.status === 429) {
            titleDanger.value = 'Too Many Requests';
            fieldDanger.value = 'Too many requests. Please wait a moment before trying again.';
            visibleDanger.value = true;
        } else {
            titleDanger.value = 'Request Failed';
            fieldDanger.value = error.response.data?.message || 'An unexpected error occurred. Please try again.';
            visibleDanger.value = true;
        }
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <div class="px-8 py-20 md:px-12 lg:px-20 flex items-center justify-center backdrop-blur-3xl !bg-cover !bg-center forgot-background">
        <form @submit.prevent="submitForgotPassword">
            <div class="px-8 md:px-12 lg:px-20 py-12 flex flex-col items-center gap-10 w-full max-w-xl backdrop-blur-2xl rounded-2xl bg-white/10 border border-white/10">
                <div class="flex flex-col items-center gap-4 w-full">
                    <AppLogoDefault width="93" height="92" />
                    <div class="flex flex-col gap-2 w-full text-center">
                        <div class="text-3xl font-medium text-white leading-tight">Forgot Password</div>
                        <p class="text-white/80 text-sm">Enter your registered email address to receive a password reset link.</p>
                    </div>
                </div>

                <div class="flex flex-col items-center gap-6 w-full">
                    <div class="flex flex-col gap-4 w-full">
                        <IconField>
                            <InputIcon class="pi pi-envelope !text-white/70" />
                            <FloatLabel variant="on">
                                <InputText id="email" type="email" class="!ps-9 !appearance-none !border !border-white/0 !w-full !outline-0 !bg-white/10 !text-white placeholder:!text-white/50 !rounded-3xl !shadow-sm" v-model="email" required />
                                <label for="email" class="ps-7 !appearance-none !outline-0 !bg-white/0 !text-white placeholder:!text-white/50 !rounded-3xl !shadow-sm">Email Address</label>
                            </FloatLabel>
                        </IconField>
                    </div>

                    <Button type="submit" label="Send Reset Link" :loading="isSubmitting" :disabled="isSubmitting" class="!w-full !rounded-3xl !bg-surface-950 !border !border-surface-950 !text-white hover:!bg-surface-950/80" />
                </div>

                <router-link to="/auth/login" class="text-white/80 cursor-pointer hover:text-white/90 text-sm"> Back to Sign In </router-link>
            </div>
        </form>

        <Dialog v-model:visible="visibleDanger" modal :header="titleDanger" :style="{ width: '25rem' }">
            <div>{{ fieldDanger }}</div>
        </Dialog>

        <Dialog v-model:visible="visibleSuccess" modal :header="titleSuccess" :style="{ width: '25rem' }">
            <div>{{ fieldSuccess }}</div>
        </Dialog>
    </div>
</template>

<style scoped>
.forgot-background {
    background-image: url('@/assets/pictures/signin-glass.jpg');
    min-height: 100vh;
}
</style>
