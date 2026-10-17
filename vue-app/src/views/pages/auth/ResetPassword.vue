<script setup>
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import FloatLabel from 'primevue/floatlabel';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import apiLogin from '@/apiLogin';
import AppLogoDefault from '@/layout/AppLogoDefault.vue';

const route = useRoute();
const router = useRouter();

const token = ref('');
const email = ref('');
const password = ref('');
const password_confirmation = ref('');

const showPassword = ref(false);
const inputTypePassword = ref('password');
const inputIconPassword = ref('pi pi-eye !text-white/70');

const isSubmitting = ref(false);

const visibleDanger = ref(false);
const titleDanger = ref('');
const fieldDanger = ref('');

const visibleSuccess = ref(false);
const titleSuccess = ref('');
const fieldSuccess = ref('');

function togglePasswordVisibility() {
    showPassword.value = !showPassword.value;
    if (showPassword.value) {
        inputTypePassword.value = 'text';
        inputIconPassword.value = 'pi pi-eye-slash !text-white/70';
    } else {
        inputTypePassword.value = 'password';
        inputIconPassword.value = 'pi pi-eye !text-white/70';
    }
}

onMounted(() => {
    token.value = route.params.token || '';
    email.value = route.query.email || '';
});

const submitResetPassword = async () => {
    if (isSubmitting.value) return;

    if (!email.value || !email.value.trim()) {
        titleDanger.value = 'Validation Error';
        fieldDanger.value = 'Please provide your email address.';
        visibleDanger.value = true;
        return;
    }

    if (!password.value || password.value.length < 12) {
        titleDanger.value = 'Validation Error';
        fieldDanger.value = 'Password must be at least 12 characters long.';
        visibleDanger.value = true;
        return;
    }

    if (password.value !== password_confirmation.value) {
        titleDanger.value = 'Validation Error';
        fieldDanger.value = 'Password confirmation does not match.';
        visibleDanger.value = true;
        return;
    }

    isSubmitting.value = true;

    try {
        await apiLogin.post('/reset-password', {
            token: token.value,
            email: email.value.trim(),
            password: password.value,
            password_confirmation: password_confirmation.value
        });

        titleSuccess.value = 'Password Reset Complete';
        fieldSuccess.value = 'Your password has been reset successfully. Redirecting to sign in...';
        visibleSuccess.value = true;

        setTimeout(() => {
            visibleSuccess.value = false;
            router.push({ path: '/auth/login' });
        }, 1500);
    } catch (error) {
        if (!error.response) {
            titleDanger.value = 'Connection Error';
            fieldDanger.value = 'Could not reach the server. Please check your connection and try again.';
        } else if (error.response.status === 422) {
            const data = error.response.data;
            const errors = data?.errors;
            if (errors?.email) {
                titleDanger.value = 'Invalid Token';
                fieldDanger.value = errors.email[0] || 'This password reset token is invalid or has expired.';
            } else if (errors?.password) {
                titleDanger.value = 'Password Validation';
                fieldDanger.value = errors.password[0] || 'Password does not meet requirements.';
            } else {
                titleDanger.value = 'Reset Failed';
                fieldDanger.value = data?.message || 'Failed to reset password. Please request a new link.';
            }
        } else if (error.response.status === 429) {
            titleDanger.value = 'Too Many Requests';
            fieldDanger.value = 'Too many requests. Please wait a moment before trying again.';
        } else {
            titleDanger.value = 'Reset Failed';
            fieldDanger.value = error.response.data?.message || 'An unexpected error occurred. Please try again.';
        }
        visibleDanger.value = true;
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <div class="px-8 py-20 md:px-12 lg:px-20 flex items-center justify-center backdrop-blur-3xl !bg-cover !bg-center reset-background">
        <form @submit.prevent="submitResetPassword">
            <div class="px-8 md:px-12 lg:px-20 py-12 flex flex-col items-center gap-8 w-full max-w-xl backdrop-blur-2xl rounded-2xl bg-white/10 border border-white/10">
                <div class="flex flex-col items-center gap-4 w-full">
                    <AppLogoDefault width="93" height="92" />
                    <div class="flex flex-col gap-2 w-full text-center">
                        <div class="text-3xl font-medium text-white leading-tight">Reset Password</div>
                        <p class="text-white/80 text-sm">Enter your new password below to regain access to your account.</p>
                    </div>
                </div>

                <div class="flex flex-col items-center gap-6 w-full">
                    <div class="flex flex-col gap-4 w-full">
                        <IconField>
                            <InputIcon class="pi pi-envelope !text-white/70" />
                            <FloatLabel variant="on">
                                <InputText
                                    id="email"
                                    type="email"
                                    class="!ps-9 !appearance-none !border !border-white/0 !w-full !outline-0 !bg-white/10 !text-white placeholder:!text-white/50 !rounded-3xl !shadow-sm"
                                    v-model="email"
                                    required
                                />
                                <label for="email" class="ps-7 !appearance-none !outline-0 !bg-white/0 !text-white placeholder:!text-white/50 !rounded-3xl !shadow-sm">Email Address</label>
                            </FloatLabel>
                        </IconField>

                        <IconField>
                            <InputIcon class="pi pi-lock !text-white/70" />
                            <FloatLabel variant="on">
                                <InputText
                                    id="password"
                                    :type="inputTypePassword"
                                    class="!ps-9 !appearance-none !border !border-white/0 !w-full !outline-0 !bg-white/10 !text-white placeholder:!text-white/50 !rounded-3xl !shadow-sm"
                                    v-model="password"
                                    required
                                />
                                <label for="password" class="ps-7 !appearance-none !outline-0 !bg-white/0 !text-white placeholder:!text-white/50 !rounded-3xl !shadow-sm">New Password (min 12 chars)</label>
                            </FloatLabel>
                            <InputIcon @click="togglePasswordVisibility" :class="inputIconPassword" />
                        </IconField>

                        <IconField>
                            <InputIcon class="pi pi-lock !text-white/70" />
                            <FloatLabel variant="on">
                                <InputText
                                    id="password_confirmation"
                                    :type="inputTypePassword"
                                    class="!ps-9 !appearance-none !border !border-white/0 !w-full !outline-0 !bg-white/10 !text-white placeholder:!text-white/50 !rounded-3xl !shadow-sm"
                                    v-model="password_confirmation"
                                    required
                                />
                                <label for="password_confirmation" class="ps-7 !appearance-none !outline-0 !bg-white/0 !text-white placeholder:!text-white/50 !rounded-3xl !shadow-sm">Confirm New Password</label>
                            </FloatLabel>
                        </IconField>
                    </div>

                    <Button
                        type="submit"
                        label="Reset Password"
                        :loading="isSubmitting"
                        :disabled="isSubmitting"
                        class="!w-full !rounded-3xl !bg-surface-950 !border !border-surface-950 !text-white hover:!bg-surface-950/80"
                    />
                </div>

                <router-link to="/auth/login" class="text-white/80 cursor-pointer hover:text-white/90 text-sm">
                    Back to Sign In
                </router-link>
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
.reset-background {
    background-image: url('@/assets/pictures/signin-glass.jpg');
    min-height: 100vh;
}
</style>
