<script setup>
import { useLayout } from '@/layout/composables/layout';
import AppUsers from './AppUsers.vue';
import AppLogoDefault from '@/layout/AppLogoDefault.vue';
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocale } from '@/i18n/useLocale';

const buttonHighlight_1 = ref(false);
const inputClassButton_1 = ref('');
const buttonHighlight_2 = ref(false);
const inputClassButton_2 = ref('');
const buttonHighlight_3 = ref(false);
const inputClassButton_3 = ref('');

const { toggleMenu, toggleDarkMode, isDarkTheme } = useLayout();
const { t } = useI18n();
const { locale: currentLocale, locales, setLocale } = useLocale();

const switchLocale = (value) => {
    setLocale(value);
};

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
            </router-link>
        </div>

        <div class="layout-topbar-actions">
            <div class="layout-config-menu">
                <!-- Language Selector -->
                <div class="relative">
                    <button
                        type="button"
                        class="layout-topbar-action"
                        v-styleclass="{ selector: '@next', enterFromClass: 'hidden', enterActiveClass: 'animate-scalein', leaveToClass: 'hidden', leaveActiveClass: 'animate-fadeout', hideOnOutsideClick: true }"
                        :title="currentLocale === 'id' ? 'Bahasa: Indonesia' : 'Language: English'"
                    >
                        <i class="pi pi-globe"></i>
                        <span>{{ t('common.language.label') }}</span>
                    </button>

                    <div
                        class="config-panel hidden absolute top-[3.25rem] right-0 w-48 p-2 bg-surface-0 dark:bg-surface-900 border border-surface rounded-border origin-top shadow-[0px_3px_5px_rgba(0,0,0,0.02),0px_0px_2px_rgba(0,0,0,0.05),0px_1px_4px_rgba(0,0,0,0.08)] z-50"
                    >
                        <div class="px-2 py-1.5 text-xs font-semibold uppercase tracking-wider text-surface-400 border-b border-surface-100 dark:border-surface-800 mb-1">
                            {{ t('common.language.label') }}
                        </div>
                        <div class="flex flex-col gap-1">
                            <button
                                v-for="item in locales"
                                :key="item.value"
                                type="button"
                                class="flex items-center justify-between px-3 py-2 text-sm rounded-md transition-colors w-full text-left border-0 bg-transparent cursor-pointer"
                                :class="currentLocale === item.value ? 'bg-primary-50 dark:bg-primary-950/50 text-primary-700 dark:text-primary-300 font-semibold' : 'text-surface-700 dark:text-surface-200 hover:bg-surface-100 dark:hover:bg-surface-800'"
                                @click="switchLocale(item.value)"
                            >
                                <span class="flex items-center gap-2.5">
                                    <span class="text-base">{{ item.value === 'id' ? '🇮🇩' : '🇬🇧' }}</span>
                                    <span>{{ item.label }}</span>
                                </span>
                                <i v-if="currentLocale === item.value" class="pi pi-check text-xs text-primary font-bold"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <button type="button" class="layout-topbar-action" @click="toggleDarkMode" :title="isDarkTheme ? 'Mode Terang' : 'Mode Gelap'">
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
                        <span>{{ t('layout.topbar.notifications') }}</span>
                    </button>
                    <button type="button" @click="togglebuttonHighlight_2" :class="inputClassButton_2">
                        <i class="pi pi-envelope"></i>
                        <span>{{ t('layout.topbar.messages') }}</span>
                    </button>
                    <div class="relative">
                        <button
                            v-styleclass="{ selector: '@next', enterFromClass: 'hidden', enterActiveClass: 'animate-scalein', leaveToClass: 'hidden', leaveActiveClass: 'animate-fadeout', hideOnOutsideClick: true }"
                            type="button"
                            @click="togglebuttonHighlight_3"
                            :class="inputClassButton_3"
                        >
                            <i class="pi pi-user"></i>
                            <span>{{ t('layout.topbar.profile') }}</span>
                        </button>
                        <AppUsers />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
