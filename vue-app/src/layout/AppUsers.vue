<script setup>
import logoDefault from '@/assets/pictures/logo_default.jpg';
import { ref, onMounted } from 'vue';
import { authStore } from '@/stores/authStore';
import { useRouter } from 'vue-router';
import apiLogin from '@/apiLogin';
import gifSuccess from '@/assets/gif/success.gif';
import { useToast } from "primevue/usetoast";

const toast = useToast();
const useAuthStore = authStore();
const router = useRouter();
const saveUser = ref([]);
const saveCompany = ref([]);
const visibleStaticBackdropSuccess = ref(false);
var titleSuccess = ref('');
var fieldSuccess = ref('');
const visibleStaticBackdropWaiting = ref(false);
var titleWaiting = ref('');
var fieldWaiting = ref('');
const _method= "PATCH";

//function visibleStaticBackdropWaitings
function visibleStaticBackdropWaitings() {
    visibleStaticBackdropWaiting.value = !visibleStaticBackdropWaiting.value;
}

//function visibleStaticBackdropSuccesss
function visibleStaticBackdropSuccesss() {
    visibleStaticBackdropSuccess.value = !visibleStaticBackdropSuccess.value;
}

const firstLogout = async () => {
    await apiLogin.post('/logout')
        .then((response) => {
            // alert('Logout successfully');
            useAuthStore.logOut();
            // router.replace({ path: '/auth/login' });
            visibleStaticBackdropWaitings();
            visibleStaticBackdropSuccesss();
            titleSuccess = 'Success!!';
            fieldSuccess = 'Logout validated, please wait a moment.';
            setTimeout(function () {
                visibleStaticBackdropSuccesss();
                router.push({ path: '/auth/login' });
            }, 2000);
            // console.log(response);
        })
        .catch((error) => {
            console.log(error);
        });
}

const clickLogedOut = async () => {
    visibleStaticBackdropWaitings();

    await apiLogin.post(`/api/firstlogin/${saveUser.value.id}`, {
        first_login_at: false,
        status_active: false,
        _method: _method,
    }).then(response => {
        firstLogout();
        // console.log(response.data.message);
        // console.log(response.data);
    }).catch(error => {
        console.error(error);
    });


};

function clickLogOt() {
    clickLogedOut();
}

//function ShowProfile
function clickShowProfile() {
    router.push({ name: 'profileUser' });
}

//method fetchDataUserRole
const fetchDataUserRole = async () => {
    await useAuthStore.getUser();
    saveUser.value = useAuthStore.AllUserAssign;
    // console.log(saveUser.value.first_login_at == true);

    let ceremonial = `Welcome, ${saveUser.value.name}`;

    if (saveUser.value.first_login_at == true) {
        try {
            const response = await apiLogin.post(`/api/firstlogin/${saveUser.value.id}`, {
                first_login_at: false, // Set ke false agar tidak muncul lagi
                status_active: true,
                _method: _method,
            });
            // console.log(response);

            if (response.data.success === true) {
                // Update state lokal segera agar sinkron
                // saveUser.value.first_login_at = false;

                toast.add({
                    severity: 'success',
                    summary: ceremonial,
                    detail: 'Please record your attendance and check your pending tasks.',
                    life: 5000
                });
            }
        } catch (error) {
            // Gunakan console.warn atau error untuk debugging internal
            console.error('Failed to update first login status:', error);

            // Opsional: Tetap tampilkan toast meskipun API update gagal
            // agar user tidak melewatkan absen
        }
    }
    // saveCompany.value = '' + saveUser.value.company;
    // console.log(useAuthStore.authUser);
    // console.log(saveUser.value.status_user);
};

onMounted(() => {
    fetchDataUserRole();
});
</script>

<template>
    <div
        class="config-panel hidden absolute top-[3.25rem] right-0 w-64 p-4 bg-surface-0 dark:bg-surface-900 border border-surface rounded-border origin-top shadow-[0px_3px_5px_rgba(0,0,0,0.02),0px_0px_2px_rgba(0,0,0,0.05),0px_1px_4px_rgba(0,0,0,0.08)]"
    >
        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-2">
                <button v-ripple class="relative overflow-hidden w-full border-0 bg-transparent flex items-start p-2 pl-4 hover:bg-surface-100 dark:hover:bg-surface-800 rounded-none cursor-pointer transition-colors duration-200">
                    <Avatar image="https://primefaces.org/cdn/primevue/images/avatar/amyelsner.png" class="mr-2" shape="circle" />
                    <span class="inline-flex flex-col items-start">
                        <span class="font-bold">{{ saveUser ? saveUser.name : "Loading.." }}</span>
                        <span class="text-sm">{{ saveUser.status_user ? saveUser.status_user.name : "Loading.." }}</span>
                    </span>
                </button>
            </div>
            <!-- <Divider type="solid" /> -->
            <div class="flex flex-col gap-2">
                <span class="text-surface-900 dark:text-white font-bold">Users</span>
                <button
                    @click="clickShowProfile()"
                    v-ripple
                    class="relative overflow-hidden w-full border-0 bg-transparent flex items-start p-2 pl-4 hover:bg-surface-100 dark:hover:bg-surface-800 rounded-none cursor-pointer transition-colors duration-200"
                >
                    <i class="pi pi-user-edit pe-2"></i>
                    <span> Profile</span>
                </button>
                <Button label="Logout" icon="pi pi-sign-out" @click="clickLogOt()" type="button" class="inputClassButton_1" />
            </div>
        </div>
    </div>

    <!-- <Button label="Success" @click="visibleStaticBackdropSuccesss" /> -->
    <Dialog v-model:visible="visibleStaticBackdropSuccess" modal :header="titleSuccess">
        <div>{{ fieldSuccess }} <Image fluid :src="gifSuccess" width="55" height="55" /></div>
    </Dialog>

    <!-- <Button label="Waiting" @click="visibleStaticBackdropWaitings" /> -->
    <Dialog v-model:visible="visibleStaticBackdropWaiting" modal header="Processing Request">
        <div class="text-center justify-content-center align-items-center">
            <i class="pi pi-spin pi-spinner" style="font-size: 2rem; color: green"></i>
            <p class="text-success" style="font-size: medium">Loading...</p>
        </div>
    </Dialog>
</template>

<style scoped>
.button-link {
    cursor: pointer;
}
.button-link :hover {
    border-color: red;
    border: 10px;
}
</style>
