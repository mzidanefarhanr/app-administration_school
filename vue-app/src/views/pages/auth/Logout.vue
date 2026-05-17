<script setup>
import { ref, onMounted } from 'vue';
import { authStore } from '@/stores/authStore';
import { useRouter } from 'vue-router';
import apiLogin from '@/apiLogin';

const useAuthStore = authStore();

const clickLogedOut = async () => {
    visibleStaticBackdropWaiting.value = true;

    await apiLogin
        .post('/logout')
        .then((response) => {
            if (response.data.id != null) {
                // alert('Logout successfully');
                useAuthStore.logOut();
                // router.replace({ path: '/login' });
                visibleStaticBackdropWaiting.value = false;
                visibleStaticBackdropSuccess.value = true;
                titleSuccess = 'Success!!';
                fieldSuccess = 'Logout Berhasil, Tunggu Beberapa Saat!';
                setTimeout(function () {
                    visibleStaticBackdropSuccess.value = false;
                    router.push({ path: '/login' });
                }, 2000);
            } else {
                useAuthStore.logOut();
                // alert('Logout successfully');
                // router.replace({ path: '/login' });
                // router.push('/login');
                visibleStaticBackdropWaiting.value = false;
                visibleStaticBackdropSuccess.value = true;
                titleSuccess = 'Success!!';
                fieldSuccess = 'Logout Berhasil, Tunggu Beberapa Saat!';
                setTimeout(function () {
                    visibleStaticBackdropSuccess.value = false;
                    router.push({ path: '/login' });
                }, 2000);
            }
        })
        .catch((error) => {
            useAuthStore.logOut();
            console.log(error);
        });
};

function clickLogOt() {
    clickLogedOut();
}
onMounted(async () => {
    clickLogedOut();
});
</script>

<template></template>
