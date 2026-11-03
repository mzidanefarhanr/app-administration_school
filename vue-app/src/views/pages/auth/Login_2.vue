<script setup>
import Button from 'primevue/button';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import { ref, onMounted } from 'vue';
import apiLogin from '@/apiLogin';
import { useRouter } from 'vue-router';
import { authStore } from '@/stores/authStore.js';
import gifSuccess from '@/assets/gif/success.gif';
import AppLogoDefault from '@/layout/AppLogoDefault.vue';

const router = useRouter();
const useAuthStore = authStore();
const inputTypePassword = ref('');
const inputIconPassword = ref('');
const showPassword = ref(false);
const showUser = ref(null);
const isSubmitting = ref(false);
const form = ref({
    username: '',
    password: ''
});

const visibleStaticBackdropDanger = ref(false);
const titleDanger = ref('');
const fieldDanger = ref('');
const visibleStaticBackdropSuccess = ref(false);
const titleSuccess = ref('');
const fieldSuccess = ref('');
const visibleStaticBackdropWaiting = ref(false);
const titleWaiting = ref('Processing Request');
const fieldWaiting = ref('Please wait, your data is being processed...');

//function togglePasswordVisibility
function togglePasswordVisibility() {
    showPassword.value = !showPassword.value;
    fetchValidate();
}
const fetchValidate = async () => {
    if (showPassword.value == true) {
        inputTypePassword.value = 'text';
        inputIconPassword.value = 'pi pi-eye-slash !text-white/70';
    } else {
        inputTypePassword.value = 'password';
        inputIconPassword.value = 'pi pi-eye !text-white/70';
    }
};

const submitLogin = async () => {
    if (isSubmitting.value) return;
    isSubmitting.value = true;

    // Bersihkan sesi / identitas sebelumnya sebelum login baru
    useAuthStore.$reset();

    visibleStaticBackdropWaiting.value = true;

    try {
        const response = await apiLogin.post('/login', form.value);

        if (response.status === 204 || response.status === 200) {
            // Tunggu identitas dan kemampuan siap sebelum navigasi
            const currentUser = await useAuthStore.fetchIdentity();

            if (!currentUser || !useAuthStore.hasLogin) {
                throw new Error('Failed to validate user session.');
            }

            showUser.value = currentUser;

            // Validasi status akun (status_active boolean / status_user_id)
            if (currentUser.status_active === false || (currentUser.status_user_id !== undefined && currentUser.status_user_id !== 1)) {
                await useAuthStore.logOut();
                visibleStaticBackdropWaiting.value = false;
                titleDanger.value = 'Account Inactive';
                fieldDanger.value = 'Your account is disabled. Please contact the administrator.';
                visibleStaticBackdropDanger.value = true;
                isSubmitting.value = false;
                return;
            }

            // Catat login pertama akun actor sendiri jika didukung backend
            try {
                await apiLogin.post(`/api/firstlogin/${currentUser.id}`, {
                    first_login_at: true,
                    _method: 'PATCH'
                });
            } catch (firstLoginErr) {
                console.warn('First login update deferred:', firstLoginErr);
            }

            visibleStaticBackdropWaiting.value = false;
            titleSuccess.value = 'Success';
            fieldSuccess.value = 'Login validated, please wait a moment.';
            visibleStaticBackdropSuccess.value = true;

            setTimeout(function () {
                visibleStaticBackdropSuccess.value = false;
                isSubmitting.value = false;
                router.push({ path: '/' });
            }, 1000);
        } else {
            throw new Error('Unexpected login response status: ' + response.status);
        }
    } catch (error) {
        visibleStaticBackdropWaiting.value = false;
        isSubmitting.value = false;

        if (error.message === 'Failed to validate user session.') {
            titleDanger.value = 'Authentication Error';
            fieldDanger.value = 'Failed to validate user session.';
        } else if (!error.response) {
            titleDanger.value = 'Login Failed';
            fieldDanger.value = 'Could not reach the API server. Check your connection or API configuration and try again.';
        } else if (error.response.status === 422) {
            titleDanger.value = 'Login Failed';
            fieldDanger.value = error.response.data?.message || 'Invalid username or password.';
        } else if (error.response.status === 401) {
            titleDanger.value = 'Authentication Error';
            fieldDanger.value = error.response.data?.message || 'Authentication failed. Please check your credentials.';
        } else {
            titleDanger.value = 'Login Failed';
            fieldDanger.value = error.response.data?.message || error.message || 'An unexpected error occurred. Please try again.';
        }

        visibleStaticBackdropDanger.value = true;
    }
};

//run hook "onMounted"
onMounted(() => {
    fetchValidate();
    try {
        const flash = sessionStorage.getItem('auth_flash_message');
        if (flash) {
            sessionStorage.removeItem('auth_flash_message');
            titleDanger.value = 'Session Expired';
            fieldDanger.value = flash;
            visibleStaticBackdropDanger.value = true;
        }
    } catch (_) {
        // ignore storage errors
    }
});
</script>

<template>
    <div class="px-8 py-20 md:px-12 lg:px-20 flex items-center justify-center backdrop-blur-3xl !bg-cover !bg-center login-background">
        <form @submit.prevent="submitLogin">
            <div class="px-8 md:px-12 lg:px-20 py-12 flex flex-col items-center gap-12 w-full max-w-xl backdrop-blur-2xl rounded-2xl bg-white/10 border border-white/10">
                <div class="flex flex-col items-center gap-4 w-full">
                    <!-- <img :src="logoDefault" alt="Logo Sekolah" width="93" height="92" /> -->
                    <AppLogoDefault width="93" height="92" />
                    <div class="flex flex-col gap-2 w-full">
                        <div class="text-center text-3xl font-medium text-white leading-tight">Welcome Back</div>
                        <!-- <div class="text-center">
                            <span class="text-white/80">Don't have an account? </span>
                            <a class="text-white/80 cursor-pointer hover:text-white/90 underline">Sign up</a>
                        </div> -->
                    </div>
                </div>
                <div class="flex flex-col items-center gap-8 w-full">
                    <div class="flex flex-col gap-6 w-full">
                        <IconField>
                            <InputIcon class="pi pi-user !text-white/70" />
                            <!-- <InputText type="text" class="!appearance-none !border !border-white/10 !w-full !outline-0 !bg-white/10 !text-white placeholder:!text-white/70 !rounded-3xl !shadow-sm" placeholder="Username" id="username" /> -->
                            <FloatLabel variant="on">
                                <InputText id="username" class="!ps-9 !appearance-none !border !border-white/0 !w-full !outline-0 !bg-white/10 !text-white placeholder:!text-white/50 !rounded-3xl !shadow-sm" v-model="form.username" />
                                <label for="username" class="ps-7 !appearance-none !outline-0 !bg-white/0 !text-white placeholder:!text-white/50 !rounded-3xl !shadow-sm">Username</label>
                            </FloatLabel>
                        </IconField>
                        <IconField>
                            <InputIcon class="pi pi-lock !text-white/70" />
                            <!-- <InputText type="password" class="!appearance-none !border !border-white/10 !w-full !outline-0 !bg-white/10 !text-white placeholder:!text-white/70 !rounded-3xl !shadow-sm" placeholder="Password" id="password" /> -->
                            <FloatLabel variant="on">
                                <InputText
                                    :type="inputTypePassword"
                                    id="password"
                                    class="!ps-9 !appearance-none !border !border-white/0 !w-full !outline-0 !bg-white/10 !text-white placeholder:!text-white/50 !rounded-3xl !shadow-sm"
                                    v-model="form.password"
                                />
                                <label for="password" class="ps-7 !appearance-none !outline-0 !bg-white/0 !text-white placeholder:!text-white/50 !rounded-3xl !shadow-sm">Password</label>
                            </FloatLabel>
                            <InputIcon @click="togglePasswordVisibility" :class="inputIconPassword" />
                        </IconField>
                    </div>
                    <Button type="submit" label="Sign In" :loading="isSubmitting" :disabled="isSubmitting" class="!w-full !rounded-3xl !bg-surface-950 !border !border-surface-950 !text-white hover:!bg-surface-950/80" />
                </div>
                <router-link to="/auth/forgot-password" class="text-white/80 cursor-pointer hover:text-white/90">Forgot Password?</router-link>
            </div>
        </form>

        <!-- <Button label="Danger" @click="visibleStaticBackdropDangers" /> -->
        <Dialog v-model:visible="visibleStaticBackdropDanger" modal :header="titleDanger" :style="{ width: '25rem' }">
            <div>
                {{ fieldDanger }}
            </div>
        </Dialog>

        <!-- <Button label="Success" @click="visibleStaticBackdropSuccesss" /> -->
        <Dialog v-model:visible="visibleStaticBackdropSuccess" modal :header="titleSuccess">
            <div>{{ fieldSuccess }} <Image fluid :src="gifSuccess" width="55" height="55" /></div>
        </Dialog>

        <!-- <Button label="Waiting" @click="visibleStaticBackdropWaitings" /> -->
        <Dialog v-model:visible="visibleStaticBackdropWaiting" modal :header="titleWaiting">
            <div class="text-center justify-content-center align-items-center">
                <i class="pi pi-spin pi-spinner" style="font-size: 2rem; color: green"></i>
                <p class="text-success mt-3" style="font-size: 1.1rem; font-weight: 500">
                    {{ fieldWaiting }}
                </p>
            </div>
        </Dialog>
    </div>
</template>

<style scoped>
.login-background {
    background-image: url('@/assets/pictures/signin-glass.jpg');
}
</style>
