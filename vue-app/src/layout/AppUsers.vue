<script setup>
import { ref, onMounted, computed } from 'vue';
import { authStore } from '@/stores/authStore';
import { useRouter } from 'vue-router';
import gifSuccess from '@/assets/gif/success.gif';

const useAuthStore = authStore();
const router = useRouter();

const visibleStaticBackdropSuccess = ref(false);
const titleSuccess = ref('');
const fieldSuccess = ref('');
const visibleStaticBackdropWaiting = ref(false);

// Nama tampilan pengguna login (dari check-auth). Null-safe.
const userName = computed(() => useAuthStore.displayName);
const roleLabels = {
    'admin-sistem': 'System Administrator',
    'operator-sekolah': 'School Operator',
    'kepala-sekolah': 'Principal',
    'wakil-kesiswaan': 'Vice Principal for Student Affairs'
};
const displayedRoles = computed(() => useAuthStore.roles.map((role) => roleLabels[role] ?? role).join(', '));

//function visibleStaticBackdropWaitings
function visibleStaticBackdropWaitings() {
    visibleStaticBackdropWaiting.value = !visibleStaticBackdropWaiting.value;
}

//function visibleStaticBackdropSuccesss
function visibleStaticBackdropSuccesss() {
    visibleStaticBackdropSuccess.value = !visibleStaticBackdropSuccess.value;
}

//function clickLogOt — end session via backend (store clears state)
function clickLogOt() {
    visibleStaticBackdropWaitings();
    useAuthStore.logOut().then((result) => {
        visibleStaticBackdropWaitings();
        visibleStaticBackdropSuccesss();
        titleSuccess.value = 'Signed out';
        fieldSuccess.value = result?.serverSuccess !== false ? 'You have been signed out. Please wait a moment.' : 'Signed out locally. Could not reach server to terminate session.';
        setTimeout(function () {
            visibleStaticBackdropSuccesss();
            router.push({ path: '/auth/login' });
        }, 1500);
    });
}

//function ShowProfile
function clickShowProfile() {
    router.push({ name: 'profileUser' });
}

onMounted(() => {
    // Identitas sesi dari kontrak ringan check-auth (bukan /api/users).
    useAuthStore.fetchIdentity();
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
                        <span class="font-bold">{{ userName || 'Loading...' }}</span>
                        <span class="text-sm">{{ displayedRoles }}</span>
                    </span>
                </button>
            </div>
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

    <!-- Success Dialog -->
    <Dialog v-model:visible="visibleStaticBackdropSuccess" modal :header="titleSuccess">
        <div>{{ fieldSuccess }} <Image fluid :src="gifSuccess" width="55" height="55" /></div>
    </Dialog>

    <!-- Waiting Dialog -->
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
