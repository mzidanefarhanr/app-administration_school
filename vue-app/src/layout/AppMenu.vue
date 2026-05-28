<script setup>
import { ref } from 'vue';

import AppMenuItem from './AppMenuItem.vue';
import { authStore } from '@/stores/authStore';
import { onMounted } from 'vue';

const useAuthStore = authStore();
const saveUser = ref('');
const model = ref([
    {
        label: 'Home',
        access: 2,
        items: [
            { label: 'Dashboard', icon: 'pi pi-fw pi-home', to: '/' },
            { label: 'Profile School', icon: 'pi pi-fw pi-building-columns', to: '/public/schoolprofile' },
        ]
    },
    {
        label: 'Masters',
        access: 2,
        items: [
            { label: 'Education Levels', icon: 'pi pi-fw pi-chart-bar', to: '/public/educationlevels' },
            { label: 'Education Schools', icon: 'pi pi-fw pi-building-columns', to: '/public/educationschools' },
            { label: 'Phase Status', icon: 'pi pi-fw pi-building', to: '/public/phasestatuses', class: 'rotated-icon' },
            { label: 'Professions', icon: 'pi pi-fw pi-briefcase', to: '/public/professions', class: 'rotated-icon' },
            { label: 'School Rombels', icon: 'pi pi-fw pi-book', to: '/public/schoolrombels', class: 'rotated-icon' },
            { label: 'School Years', icon: 'pi pi-fw pi-calendar', to: '/public/schoolyears', class: 'rotated-icon' },
            { label: 'Student Majors', icon: 'pi pi-fw pi-calendar', to: '/public/studentmajors', class: 'rotated-icon' },
        ]
    },
    {
        label: 'Public',
        access: 2,
        items: [
            { label: 'Students', icon: 'pi pi-fw pi-graduation-cap', to: '/public/students' },
            { label: 'Teachers', icon: 'pi pi-fw pi-slack', to: '#' },
            { label: 'Employees', icon: 'pi pi-fw pi-address-book', to: '#' },
            { label: 'Letters Form', icon: 'pi pi-fw pi-book', to: '#' },
        ]
    },
    {
        label: 'Personal',
        access: 2,
        items: [{ label: 'Profile', icon: 'pi pi-fw pi-user', to: '/profile/user' }]
    },
    {
        label: 'Admin',
        access: 1,
        items: [
            { label: 'Accounts', icon: 'pi pi-fw pi-users', to: '/master/accounts' },
            { label: 'User Type', icon: 'pi pi-fw pi-user-edit', to: '/master/typeusers' },
        ]
    },
    {
        label: 'UI Components',
        access: 2,
        items: [
            { label: 'Form Layout', icon: 'pi pi-fw pi-id-card', to: '/uikit/formlayout' },
            { label: 'Input', icon: 'pi pi-fw pi-check-square', to: '/uikit/input' },
            { label: 'Button', icon: 'pi pi-fw pi-mobile', to: '/uikit/button', class: 'rotated-icon' },
            { label: 'Table', icon: 'pi pi-fw pi-table', to: '/uikit/table' },
            { label: 'List', icon: 'pi pi-fw pi-list', to: '/uikit/list' },
            { label: 'Tree', icon: 'pi pi-fw pi-share-alt', to: '/uikit/tree' },
            { label: 'Panel', icon: 'pi pi-fw pi-tablet', to: '/uikit/panel' },
            { label: 'Overlay', icon: 'pi pi-fw pi-clone', to: '/uikit/overlay' },
            { label: 'Media', icon: 'pi pi-fw pi-image', to: '/uikit/media' },
            { label: 'Menu', icon: 'pi pi-fw pi-bars', to: '/uikit/menu' },
            { label: 'Message', icon: 'pi pi-fw pi-comment', to: '/uikit/message' },
            { label: 'File', icon: 'pi pi-fw pi-file', to: '/uikit/file' },
            { label: 'Chart', icon: 'pi pi-fw pi-chart-bar', to: '/uikit/charts' },
            { label: 'Timeline', icon: 'pi pi-fw pi-calendar', to: '/uikit/timeline' },
            { label: 'Misc', icon: 'pi pi-fw pi-circle', to: '/uikit/misc' }
        ]
    },
    {
        label: 'Pages',
        access: 2,
        icon: 'pi pi-fw pi-briefcase',
        to: '/pages',
        items: [
            {
                label: 'Landing',
                icon: 'pi pi-fw pi-globe',
                to: '/landing'
            },
            {
                label: 'Auth',
                icon: 'pi pi-fw pi-user',
                items: [
                    {
                        label: 'Login',
                        icon: 'pi pi-fw pi-sign-in',
                        to: '/auth/login'
                    },
                    {
                        label: 'Error',
                        icon: 'pi pi-fw pi-times-circle',
                        to: '/auth/error'
                    },
                    {
                        label: 'Access Denied',
                        icon: 'pi pi-fw pi-lock',
                        to: '/auth/access'
                    }
                ]
            },
            {
                label: 'Crud',
                icon: 'pi pi-fw pi-pencil',
                to: '/pages/crud'
            },
            {
                label: 'Not Found',
                icon: 'pi pi-fw pi-exclamation-circle',
                to: '/pages/notfound'
            },
            {
                label: 'Empty',
                icon: 'pi pi-fw pi-circle-off',
                to: '/pages/empty'
            }
        ]
    },
    {
        label: 'Hierarchy',
        access: 2,
        items: [
            {
                label: 'Submenu 1',
                icon: 'pi pi-fw pi-bookmark',
                items: [
                    {
                        label: 'Submenu 1.1',
                        icon: 'pi pi-fw pi-bookmark',
                        items: [
                            { label: 'Submenu 1.1.1', icon: 'pi pi-fw pi-bookmark' },
                            { label: 'Submenu 1.1.2', icon: 'pi pi-fw pi-bookmark' },
                            { label: 'Submenu 1.1.3', icon: 'pi pi-fw pi-bookmark' }
                        ]
                    },
                    {
                        label: 'Submenu 1.2',
                        icon: 'pi pi-fw pi-bookmark',
                        items: [{ label: 'Submenu 1.2.1', icon: 'pi pi-fw pi-bookmark' }]
                    }
                ]
            },
            {
                label: 'Submenu 2',
                icon: 'pi pi-fw pi-bookmark',
                items: [
                    {
                        label: 'Submenu 2.1',
                        icon: 'pi pi-fw pi-bookmark',
                        items: [
                            { label: 'Submenu 2.1.1', icon: 'pi pi-fw pi-bookmark' },
                            { label: 'Submenu 2.1.2', icon: 'pi pi-fw pi-bookmark' }
                        ]
                    },
                    {
                        label: 'Submenu 2.2',
                        icon: 'pi pi-fw pi-bookmark',
                        items: [{ label: 'Submenu 2.2.1', icon: 'pi pi-fw pi-bookmark' }]
                    }
                ]
            }
        ]
    },
    {
        label: 'Get Started',
        access: 2,
        items: [
            {
                label: 'Documentation',
                icon: 'pi pi-fw pi-book',
                to: '/documentation'
            },
            {
                label: 'View Source',
                icon: 'pi pi-fw pi-github',
                url: 'https://github.com/primefaces/sakai-vue',
                target: '_blank'
            }
        ]
    }
]);
//method fetchDataUserRole
const fetchDataUserRole = async () => {
    await useAuthStore.getUser();
    const datas = useAuthStore.AllUserAssign;
    saveUser.value = datas.type_user_id;
    // console.log(saveUser.value <= 1);
};
onMounted(() => {
    fetchDataUserRole();
});
</script>

<template>
    <ul class="layout-menu">
        <template v-for="(item, i) in model" :key="item">
            <app-menu-item v-if="!item.separator && saveUser <= item.access && saveUser" :item="item" :index="i"></app-menu-item>
            <li v-if="item.separator && saveUser <= item.access && saveUser" class="menu-separator"></li>
            <!-- <br><small>{{ saveUser }} <= {{ item.access }} = {{ saveUser <= item.access }}</small> -->
        </template>
    </ul>
</template>

<style lang="scss" scoped></style>
