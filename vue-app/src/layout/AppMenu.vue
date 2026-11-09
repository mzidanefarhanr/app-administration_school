<script setup>
import { computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';

import AppMenuItem from './AppMenuItem.vue';
import { authStore } from '@/stores/authStore';

const useAuthStore = authStore();
const { t } = useI18n();

// Setiap grup menu diberi kemampuan bernama (`requires`) bila perlu.
// `null` berarti tersedia untuk semua pengguna yang login.
// Menggunakan computed agar label reaktif saat bahasa berubah tanpa reload.
const model = computed(() => [
    {
        label: t('menu.group.home'),
        requires: null,
        items: [
            { label: t('menu.item.dashboard'), icon: 'pi pi-fw pi-home', to: '/' },
            { label: t('menu.item.schoolProfile'), icon: 'pi pi-fw pi-building-columns', to: '/public/schoolprofile' },
            { label: t('menu.item.tasks'), icon: 'pi pi-fw pi-check-square', to: '/public/tasks', requires: 'tasks.view' }
        ]
    },
    {
        label: t('menu.group.master'),
        requires: 'master.view',
        items: [
            { label: t('menu.item.educationLevels'), icon: 'pi pi-fw pi-sort-amount-up', to: '/public/educationlevels' },
            { label: t('menu.item.schools'), icon: 'pi pi-fw pi-building-columns', to: '/public/educationschools' },
            { label: t('menu.item.phaseStatuses'), icon: 'pi pi-fw pi-flag', to: '/public/phasestatuses', class: 'rotated-icon' },
            { label: t('menu.item.professions'), icon: 'pi pi-fw pi-briefcase', to: '/public/professions', class: 'rotated-icon' },
            { label: t('menu.item.studentMajors'), icon: 'pi pi-fw pi-compass', to: '/public/studentmajors', class: 'rotated-icon' }
        ]
    },
    {
        label: t('menu.group.academic'),
        requires: null,
        items: [
            { label: t('menu.item.schoolRombels'), icon: 'pi pi-fw pi-table', to: '/public/schoolrombels', requires: 'students.view' },
            { label: t('menu.item.progression'), icon: 'pi pi-fw pi-sitemap', to: '/public/progression', requires: 'students.view' },
            { label: t('menu.item.schoolYears'), icon: 'pi pi-fw pi-calendar', to: '/public/schoolyears', requires: 'master.view' }
        ]
    },
    {
        label: t('menu.group.studentStaff'),
        requires: null,
        items: [
            { label: t('menu.item.students'), icon: 'pi pi-fw pi-graduation-cap', to: '/public/students', requires: 'students.view' },
            { label: t('menu.item.teachers'), icon: 'pi pi-fw pi-id-card', to: '/public/teachers', requires: 'employees.view' },
            { label: t('menu.item.employees'), icon: 'pi pi-fw pi-address-book', to: '/public/employees', requires: 'employees.view' },
            { label: t('menu.item.dataQuality'), icon: 'pi pi-fw pi-check-circle', to: '/public/data-quality', requires: ['students.view', 'employees.view'] },
            { label: t('menu.item.schoolReports'), icon: 'pi pi-fw pi-chart-line', to: '/public/reports/school', requires: 'students.view' }
        ]
    },
    {
        label: t('menu.group.letters'),
        requires: 'letters.view',
        items: [
            { label: t('menu.item.incomingLetters'), icon: 'pi pi-fw pi-inbox', to: '/public/letters/incoming', requires: 'letters.view' },
            { label: t('menu.item.outgoingLetters'), icon: 'pi pi-fw pi-send', to: '/public/letters/outgoing', requires: 'letters.view' },
            { label: t('menu.item.letterDispositions'), icon: 'pi pi-fw pi-user-plus', to: '/public/letters/dispositions', requires: 'letters.view' },
            { label: t('menu.item.letterSettings'), icon: 'pi pi-fw pi-cog', to: '/public/letters/settings', requires: 'letters.view' }
        ]
    },
    {
        label: t('menu.group.leaves'),
        requires: ['leaves.view', 'leaves.request'],
        items: [{ label: t('menu.item.leaves'), icon: 'pi pi-fw pi-calendar-minus', to: '/public/leaves', requires: ['leaves.view', 'leaves.request'] }]
    },
    {
        label: t('menu.group.archives'),
        requires: 'archives.view',
        items: [{ label: t('menu.item.archives'), icon: 'pi pi-fw pi-folder', to: '/public/archives', requires: 'archives.view' }]
    },
    {
        label: t('menu.group.teaching'),
        requires: 'teaching.view',
        items: [
            { label: t('menu.item.teachingSchedules'), icon: 'pi pi-fw pi-calendar-clock', to: '/public/teaching/schedules', requires: 'teaching.view' },
            { label: t('menu.item.teachingJournals'), icon: 'pi pi-fw pi-book', to: '/public/teaching/journals', requires: 'teaching.view' },
            { label: t('menu.item.teachingReports'), icon: 'pi pi-fw pi-chart-bar', to: '/public/teaching/reports', requires: 'teaching.view' }
        ]
    },
    {
        label: t('menu.group.myAccount'),
        requires: null,
        items: [{ label: t('menu.item.profileUser'), icon: 'pi pi-fw pi-user', to: '/profile/user' }]
    },
    {
        label: t('menu.group.access'),
        requires: 'users.view',
        items: [
            { label: t('menu.item.masterAccounts'), icon: 'pi pi-fw pi-users', to: '/master/accounts', requires: 'users.view' },
            { label: t('menu.item.masterTypeUsers'), icon: 'pi pi-fw pi-user-edit', to: '/master/typeusers', requires: 'master.manage' },
            { label: t('menu.item.masterRoles'), icon: 'pi pi-fw pi-shield', to: '/master/roles', requires: 'roles.view' }
        ]
    },
    {
        label: t('menu.group.system'),
        requires: ['backup.manage', 'users.manage'],
        items: [
            { label: t('menu.item.systemStatus'), icon: 'pi pi-fw pi-server', to: '/system/status', requires: ['backup.manage', 'users.manage'] },
            { label: t('menu.item.systemPerformance'), icon: 'pi pi-fw pi-gauge', to: '/system/performance', requires: ['backup.manage', 'users.manage'] }
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
        <template v-for="(item, i) in visibleModel" :key="item.label || i">
            <app-menu-item v-if="!item.separator && useAuthStore.hasLogin" :item="item" :index="i"></app-menu-item>
            <li v-if="item.separator && useAuthStore.hasLogin" class="menu-separator"></li>
        </template>
    </ul>
</template>

<style lang="scss" scoped></style>
