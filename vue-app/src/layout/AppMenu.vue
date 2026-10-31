<script setup>
import { ref, computed, onMounted } from 'vue';

import AppMenuItem from './AppMenuItem.vue';
import { authStore } from '@/stores/authStore';

const useAuthStore = authStore();

// Setiap grup menu diberi kemampuan bernama (`requires`) bila perlu.
// `null` berarti tersedia untuk semua pengguna yang login.
const model = ref([
    {
        label: 'Home',
        requires: null,
        items: [
            { label: 'Dashboard', icon: 'pi pi-fw pi-home', to: '/' },
            { label: 'School Profile', icon: 'pi pi-fw pi-building-columns', to: '/public/schoolprofile' },
        ]
    },
    {
        label: 'Master Data',
        requires: 'master.view',
        items: [
            { label: 'Education Levels', icon: 'pi pi-fw pi-chart-bar', to: '/public/educationlevels' },
            { label: 'Education Schools', icon: 'pi pi-fw pi-building-columns', to: '/public/educationschools' },
            { label: 'Phase Status', icon: 'pi pi-fw pi-building', to: '/public/phasestatuses', class: 'rotated-icon' },
            { label: 'Professions', icon: 'pi pi-fw pi-briefcase', to: '/public/professions', class: 'rotated-icon' },
            { label: 'Classes', icon: 'pi pi-fw pi-book', to: '/public/schoolrombels', class: 'rotated-icon' },
            { label: 'School Years', icon: 'pi pi-fw pi-calendar', to: '/public/schoolyears', class: 'rotated-icon' },
            { label: 'Student Majors', icon: 'pi pi-fw pi-calendar', to: '/public/studentmajors', class: 'rotated-icon' },
        ]
    },
    {
        label: 'Public',
        requires: null,
        items: [
            { label: 'Students', icon: 'pi pi-fw pi-graduation-cap', to: '/public/students', requires: 'students.view' },
            { label: 'Teachers', icon: 'pi pi-fw pi-slack', to: '/public/teachers', requires: 'employees.view' },
            { label: 'Employees', icon: 'pi pi-fw pi-address-book', to: '/public/employees', requires: 'employees.view' },
            { label: 'Data Quality', icon: 'pi pi-fw pi-check-circle', to: '/public/data-quality', requires: ['students.view', 'employees.view'] },
            { label: 'Letters Form', icon: 'pi pi-fw pi-book', to: '#' },
        ]
    },
    {
        label: 'Personal',
        requires: null,
        items: [{ label: 'Profile', icon: 'pi pi-fw pi-user', to: '/profile/user' }]
    },
    {
        label: 'Admin',
        requires: 'users.view',
        items: [
            { label: 'Accounts', icon: 'pi pi-fw pi-users', to: '/master/accounts', requires: 'users.view' },
            { label: 'User Type', icon: 'pi pi-fw pi-user-edit', to: '/master/typeusers', requires: 'master.manage' },
            { label: 'Roles & Abilities', icon: 'pi pi-fw pi-shield', to: '/master/roles', requires: 'roles.view' },
        ]
    },
    {
        label: 'UI Components',
        requires: null,
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
        requires: null,
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
        requires: null,
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
        requires: null,
        items: [
            {
                label: 'Documentation',
                icon: 'pi pi-fw pi-question',
                to: '/documentation'
            },
            {
                label: 'View Source',
                icon: 'pi pi-fw pi-github',
                url: 'https://github.com/primefaces/sakue-vue',
                target: '_blank'
            }
        ]
    }
]);

// Bila pengguna dapat mengakses setidaknya satu kemampuan grup/item.
const canSee = (requires) => {
    if (!requires) return true;

    return Array.isArray(requires) ? useAuthStore.canAny(requires) : useAuthStore.can(requires);
};

// Item grup yang tampil, difilter berdasarkan kemampuan dan item individual.
const visibleModel = computed(() =>
    model.value
        .filter((group) => canSee(group.requires))
        .map((group) => ({
            ...group,
            items: (group.items || []).filter((item) => canSee(item.requires))
        }))
        .filter((group) => (group.items ? group.items.length > 0 : true))
);

onMounted(() => {
    // Identitas & kemampuan dari kontrak ringan check-auth (bukan /api/users).
    useAuthStore.fetchIdentity();
});
</script>

<template>
    <ul class="layout-menu">
        <template v-for="(item, i) in visibleModel" :key="item">
            <app-menu-item v-if="!item.separator && useAuthStore.hasLogin" :item="item" :index="i"></app-menu-item>
            <li v-if="item.separator && useAuthStore.hasLogin" class="menu-separator"></li>
        </template>
    </ul>
</template>

<style lang="scss" scoped></style>
