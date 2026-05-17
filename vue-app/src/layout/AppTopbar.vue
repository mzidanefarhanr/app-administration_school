<script setup>
import { useLayout } from '@/layout/composables/layout';
import AppConfigurator from './AppConfigurator.vue';
import AppUsers from './AppUsers.vue';
import AppLogoDefault from '@/layout/AppLogoDefault.vue';
import logoDefault from '@/assets/pictures/logo_default.jpg';
import { ref, onMounted } from 'vue';
import { authStore } from '@/stores/authStore';
import { useRouter } from 'vue-router';
import apiLogin from '@/apiLogin';
import gifSuccess from '@/assets/gif/success.gif';

const buttonHighlight_1 = ref(false);
const inputClassButton_1 = ref('');
const buttonHighlight_2 = ref(false);
const inputClassButton_2 = ref('');
const buttonHighlight_3 = ref(false);
const inputClassButton_3 = ref('');

const { toggleMenu, toggleDarkMode, isDarkTheme } = useLayout();
const useAuthStore = authStore();
const router = useRouter();

//function togglebuttonHighlight_1
function togglebuttonHighlight_1() {
    buttonHighlight_1.value = !buttonHighlight_1.value;
    // console.log(buttonHighlight_1.value);
    fetchbuttonHighlight_1();
}
const fetchbuttonHighlight_1 = async () => {
    if (buttonHighlight_1.value === true) {
        inputClassButton_1.value = 'layout-topbar-action layout-topbar-action-highlight';
        inputClassButton_2.value = 'layout-topbar-action';
        inputClassButton_3.value = 'layout-topbar-action';
    } else {
        inputClassButton_1.value = 'layout-topbar-action';
    }
};
//function togglebuttonHighlight_2
function togglebuttonHighlight_2() {
    buttonHighlight_2.value = !buttonHighlight_2.value;
    // console.log(buttonHighlight_2.value);
    fetchbuttonHighlight_2();
}
const fetchbuttonHighlight_2 = async () => {
    if (buttonHighlight_2.value === true) {
        inputClassButton_1.value = 'layout-topbar-action';
        inputClassButton_2.value = 'layout-topbar-action layout-topbar-action-highlight';
        inputClassButton_3.value = 'layout-topbar-action';
    } else {
        inputClassButton_2.value = 'layout-topbar-action';
    }
};
//function togglebuttonHighlight_3
function togglebuttonHighlight_3() {
    buttonHighlight_3.value = !buttonHighlight_3.value;
    // console.log(buttonHighlight_3.value);
    fetchbuttonHighlight_3();
}
const fetchbuttonHighlight_3 = async () => {
    if (buttonHighlight_3.value === true) {
        inputClassButton_1.value = 'layout-topbar-action';
        inputClassButton_2.value = 'layout-topbar-action';
        inputClassButton_3.value = 'layout-topbar-action layout-topbar-action-highlight';
    } else {
        inputClassButton_3.value = 'layout-topbar-action';
    }
};

//run hook "onMounted"
onMounted(() => {
    fetchbuttonHighlight_1();
    fetchbuttonHighlight_2();
    fetchbuttonHighlight_3();
});
</script>

<template>
    <div class="layout-topbar">
        <div class="layout-topbar-logo-container">
            <button class="layout-menu-button layout-topbar-action" @click="toggleMenu">
                <i class="pi pi-bars"></i>
            </button>
            <!-- <router-link to="/" class="layout-topbar-logo">
                <img :src="logoDefault" alt="Logo Sekolah" width="54" height="11" />

                <span>Administration SMA Yappenda</span>
            </router-link> -->
            <router-link to="/" class="layout-topbar-logo">
                <AppLogoDefault />

                <span>Administration Apps</span>
            </router-link>
        </div>

        <div class="layout-topbar-actions">
            <div class="layout-config-menu">
                <button type="button" class="layout-topbar-action" @click="toggleDarkMode">
                    <i :class="['pi', { 'pi-moon': isDarkTheme, 'pi-sun': !isDarkTheme }]"></i>
                </button>
                <!-- <div class="relative">
                    <button
                        v-styleclass="{ selector: '@next', enterFromClass: 'hidden', enterActiveClass: 'animate-scalein', leaveToClass: 'hidden', leaveActiveClass: 'animate-fadeout', hideOnOutsideClick: true }"
                        type="button"
                        class="layout-topbar-action layout-topbar-action-highlight"
                    >
                        <i class="pi pi-palette"></i>
                    </button>
                    <AppConfigurator />
                </div> -->
            </div>

            <button
                class="layout-topbar-menu-button layout-topbar-action"
                v-styleclass="{ selector: '@next', enterFromClass: 'hidden', enterActiveClass: 'animate-scalein', leaveToClass: 'hidden', leaveActiveClass: 'animate-fadeout', hideOnOutsideClick: true }"
            >
                <i class="pi pi-ellipsis-v"></i>
            </button>

            <div class="layout-topbar-menu hidden lg:block">
                <div class="layout-topbar-menu-content">
                    <button type="button" @click="togglebuttonHighlight_1" :class="inputClassButton_1">
                        <i class="pi pi-bell"></i>
                        <span>Notification</span>
                    </button>
                    <button type="button" @click="togglebuttonHighlight_2" :class="inputClassButton_2">
                        <i class="pi pi-envelope"></i>
                        <span>Messages</span>
                    </button>
                    <div class="relative">
                        <button
                            v-styleclass="{ selector: '@next', enterFromClass: 'hidden', enterActiveClass: 'animate-scalein', leaveToClass: 'hidden', leaveActiveClass: 'animate-fadeout', hideOnOutsideClick: true }"
                            type="button"
                            @click="togglebuttonHighlight_3"
                            :class="inputClassButton_3"
                        >
                            <i class="pi pi-user"></i>
                            <span>Profile</span>
                        </button>
                        <AppUsers />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
