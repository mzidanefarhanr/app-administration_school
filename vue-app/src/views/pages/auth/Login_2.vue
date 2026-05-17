<script setup>
import Button from 'primevue/button';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import ImagesLogin from '@/assets/pictures/signin-glass.jpg';
import logoDefault from '@/assets/pictures/logo_default.jpg';
import { ref, onMounted } from 'vue';
import apiLogin from '@/apiLogin';
import { useRouter } from 'vue-router';
import { authStore } from '@/stores/authStore.js';
import gifSuccess from '@/assets/gif/success.gif';
import AppLogoDefault from '@/layout/AppLogoDefault.vue';


const router = useRouter();
const useAuthStore = authStore();
const password = ref('');
const inputTypePassword = ref('');
const inputIconPassword = ref('');
const showPassword = ref(false);
const showUser = ref([]);
const form = ref({
    username: '',
    password: ''
});
const _method= "PATCH";

const visibleStaticBackdropDanger = ref(false);
var titleDanger = ref('');
var fieldDanger = ref('');
const visibleStaticBackdropSuccess = ref(false);
var titleSuccess = ref('');
var fieldSuccess = ref('');
const visibleStaticBackdropWaiting = ref(false);
var titleWaiting = ref('');
var fieldWaiting = ref('');

//function visibleStaticBackdropDangers
function visibleStaticBackdropDangers() {
    visibleStaticBackdropDanger.value = !visibleStaticBackdropDanger.value;
}

//function visibleStaticBackdropWaitings
function visibleStaticBackdropWaitings() {
    visibleStaticBackdropWaiting.value = !visibleStaticBackdropWaiting.value;
}

//function visibleStaticBackdropSuccesss
function visibleStaticBackdropSuccesss() {
    visibleStaticBackdropSuccess.value = !visibleStaticBackdropSuccess.value;
}

//function togglePasswordVisibility
function togglePasswordVisibility() {
    showPassword.value = !showPassword.value;
    // console.log(showPassword.value);
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

const firstLogin = async (event) => {
    const first_login_at = true;
    const status_active = true;
    await apiLogin.post(`/api/firstlogin/${showUser.value.id}`, {
        first_login_at: first_login_at,
        status_active: status_active,
        _method: _method,
    }).then(response => {
        if (response.data.success === true) {
            visibleStaticBackdropWaitings();
            titleSuccess = 'Success';
            fieldSuccess = 'Login validated, please wait a moment.';
            visibleStaticBackdropSuccesss();

            setTimeout(function () {
                visibleStaticBackdropSuccesss();
                router.push({ path: '/' });
            }, 2000);
        }
        // console.log(response.data.message);
        // console.log(response.data);
    }).catch(error => {
        console.error(error);
        if (error.response.status === 422) {
            // alert('"These credentials do not match our records."');
            // getMessage.value = error.response.data.message;
            visibleStaticBackdropWaitings();
            titleDanger = 'Login Failed';
            fieldDanger = error.response?.data?.message || 'Invalid username or password.';;
            visibleStaticBackdropDangers();
        } else {
            // alert('An error occurred');
            // getMessage.value = error.response.data.message;
            visibleStaticBackdropWaitings();
            titleDanger = 'Login Failed';
            fieldDanger = error.response?.data?.message;
            visibleStaticBackdropDangers();
        }
    });
};

//Before
// const authenticated = async (event) => {
//     const usersCheck = ref([]);
//     await apiLogin.get('/api/users').then((response) => {
//         //set response data to state "Users"
//         usersCheck.value = response.data.data;
//         // console.log(usersCheck.value)
//     });
//     showUser.value = usersCheck.value.find((users) => users.username == form.value.username);

//     if (showUser.value.status_user_id === 1) {
//         firstLogin();
//     } else {
//         //Gagal status
//         visibleStaticBackdropWaitings();
//         titleDanger = 'GAGAL!!';
//         fieldDanger = 'Akun dinonaktifkan, silahkan hubungi Admin Website!!';
//         visibleStaticBackdropDangers();
//     }
// };
//After
const authenticated = async () => {
    try {
        // Ambil data user yang sedang login saat ini saja (lebih aman daripada fetch semua users)
        // await authStore().checkLogin();
        // const currentUser = authStore().AllUserAssign;
        await authStore().getUser();
        const currentUser = authStore().authUser;

        if (!currentUser) {
             throw new Error("User data not found.");
        }

        // Simpan ke showUser agar bisa dipakai firstLogin()
        showUser.value = currentUser;

        // Cek status_user_id (1 = Aktif, 2 = Non-Aktif)
        if (currentUser.status_user_id === 1) {
            await firstLogin();
        } else {
            // Jika tidak aktif, paksa logout di store agar tidak nyangkut
            authStore().$reset();
            await apiLogin.post('/logout').then((response) => {
                useAuthStore.logOut();
            }).catch((error) => {
                console.log(error);
            });

            visibleStaticBackdropWaitings();
            titleDanger = 'Account Inactive';
            fieldDanger = 'Your account is disabled. Please contact the administrator.';
            visibleStaticBackdropDangers();

            setTimeout(function () {
                visibleStaticBackdropDangers();
                router.push({ path: '/auth/login' });
            }, 2000);
        }
    } catch (error) {
        console.error(error);
        visibleStaticBackdropWaitings();
        titleDanger = 'Authentication Error';
        fieldDanger = 'Failed to validate user session.';
        visibleStaticBackdropDangers();
    }
};

//Before
// const submitLogin = async () => {
//     // await useAuthStore.getToken();
//     visibleStaticBackdropWaitings();
//     await apiLogin
//         .post('/login', {
//             username: form.value.username,
//             password: form.value.password
//         })
//         .then((response) => {
//             if (response.status === 204) {
//                 authenticated();
//             }
//         })
//         .catch((error) => {
//             console.error(error);
//             if (error.response.status === 422) {
//                 // alert('"These credentials do not match our records."');
//                 // getMessage.value = error.response.data.message;
//                 visibleStaticBackdropWaitings();
//                 titleDanger = 'GAGAL!!';
//                 fieldDanger = 'Username atau Password tidak tervalidasi, silahkan masukkan data dengan benar!!';
//                 visibleStaticBackdropDangers();
//             } else {
//                 // alert('An error occurred');
//                 // getMessage.value = error.response.data.message;
//                 visibleStaticBackdropWaitings();
//                 titleDanger = 'GAGAL!!';
//                 fieldDanger = error.response.data.message;
//                 visibleStaticBackdropDangers();
//             }
//         });

// };
//After
const submitLogin = async () => {
    // 1. RESET STATE: Bersihkan sisa session/data user sebelumnya
    const useAuthStore = authStore();
    useAuthStore.$reset();

    visibleStaticBackdropWaitings();

    try {
        const response = await apiLogin.post('/login', form.value);

        // Jika Laravel menggunakan Sanctum/Fortify, status 204 atau 200 berarti sukses
        if (response.status === 204 || response.status === 200) {
            // Jalankan fungsi pengecekan status user
            await authenticated();
        }
    } catch (error) {
        // console.error(error);
        visibleStaticBackdropWaitings(); // Tutup loading
        titleDanger = 'Login Failed';

        // Ambil pesan error langsung dari Laravel (termasuk pesan "Akun tidak aktif")
        fieldDanger = error.response?.data?.message || 'Invalid credentials.';

        visibleStaticBackdropDangers();
    }
};

//run hook "onMounted"
onMounted(() => {
    fetchValidate();
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
                    <Button type="submit" label="Sign In" class="!w-full !rounded-3xl !bg-surface-950 !border !border-surface-950 !text-white hover:!bg-surface-950/80" />
                </div>
                <a class="text-white/80 cursor-pointer hover:text-white/90">Forgot Password?</a>
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
        <Dialog v-model:visible="visibleStaticBackdropWaiting" modal header="Processing Request">
            <div class="text-center justify-content-center align-items-center">
                <i class="pi pi-spin pi-spinner" style="font-size: 2rem; color: green"></i>
                <p class="text-success mt-3" style="font-size: 1.1rem; font-weight: 500;">
                Please wait, your data is being processed...
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
