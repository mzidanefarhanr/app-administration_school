<script setup>
import { ref, computed, onMounted } from 'vue';

import AppMenuItem from './AppMenuItem.vue';
import { authStore } from '@/stores/authStore';

const useAuthStore = authStore();

// Setiap grup menu diberi kemampuan bernama (`requires`) bila perlu.
// `null` berarti tersedia untuk semua pengguna yang login.
const model = ref([
    {
        label: 'Beranda',
        requires: null,
        items: [
            { label: 'Dasbor', icon: 'pi pi-fw pi-home', to: '/' },
            { label: 'Profil Sekolah', icon: 'pi pi-fw pi-building-columns', to: '/public/schoolprofile' },
            { label: 'Pusat Tugas & Pengingat', icon: 'pi pi-fw pi-check-square', to: '/public/tasks', requires: 'tasks.view' }
        ]
    },
    {
        label: 'Data Master',
        requires: 'master.view',
        items: [
            { label: 'Jenjang Pendidikan', icon: 'pi pi-fw pi-chart-bar', to: '/public/educationlevels' },
            { label: 'Sekolah', icon: 'pi pi-fw pi-building-columns', to: '/public/educationschools' },
            { label: 'Status Fase', icon: 'pi pi-fw pi-building', to: '/public/phasestatuses', class: 'rotated-icon' },
            { label: 'Profesi', icon: 'pi pi-fw pi-briefcase', to: '/public/professions', class: 'rotated-icon' },
            { label: 'Jurusan Siswa', icon: 'pi pi-fw pi-calendar', to: '/public/studentmajors', class: 'rotated-icon' }
        ]
    },
    {
        label: 'Akademik',
        requires: null,
        items: [
            { label: 'Rombongan Belajar', icon: 'pi pi-fw pi-book', to: '/public/schoolrombels', requires: 'students.view' },
            { label: 'Kenaikan & Kelulusan', icon: 'pi pi-fw pi-sitemap', to: '/public/progression', requires: 'students.view' },
            { label: 'Tahun Ajaran', icon: 'pi pi-fw pi-calendar', to: '/public/schoolyears', requires: 'master.view' }
        ]
    },
    {
        label: 'Kesiswaan & Kepegawaian',
        requires: null,
        items: [
            { label: 'Data Siswa', icon: 'pi pi-fw pi-graduation-cap', to: '/public/students', requires: 'students.view' },
            { label: 'Direktori Guru', icon: 'pi pi-fw pi-slack', to: '/public/teachers', requires: 'employees.view' },
            { label: 'Direktori Pegawai', icon: 'pi pi-fw pi-address-book', to: '/public/employees', requires: 'employees.view' },
            { label: 'Kualitas Data', icon: 'pi pi-fw pi-check-circle', to: '/public/data-quality', requires: ['students.view', 'employees.view'] },
            { label: 'Laporan Sekolah', icon: 'pi pi-fw pi-chart-line', to: '/public/reports/school', requires: 'students.view' }
        ]
    },
    {
        label: 'Tata Persuratan',
        requires: 'letters.view',
        items: [
            { label: 'Surat Masuk', icon: 'pi pi-fw pi-inbox', to: '/public/letters/incoming', requires: 'letters.view' },
            { label: 'Surat Keluar', icon: 'pi pi-fw pi-send', to: '/public/letters/outgoing', requires: 'letters.view' },
            { label: 'Disposisi Surat', icon: 'pi pi-fw pi-user-plus', to: '/public/letters/dispositions', requires: 'letters.view' },
            { label: 'Pengaturan Surat', icon: 'pi pi-fw pi-cog', to: '/public/letters/settings', requires: 'letters.view' }
        ]
    },
    {
        label: 'Perizinan Pegawai',
        requires: ['leaves.view', 'leaves.request'],
        items: [{ label: 'Pengajuan Izin & Cuti', icon: 'pi pi-fw pi-calendar-minus', to: '/public/leaves', requires: ['leaves.view', 'leaves.request'] }]
    },
    {
        label: 'Arsip Dokumen',
        requires: 'archives.view',
        items: [{ label: 'Repositori Arsip', icon: 'pi pi-fw pi-folder', to: '/public/archives', requires: 'archives.view' }]
    },
    {
        label: 'Administrasi Pembelajaran',
        requires: 'teaching.view',
        items: [
            { label: 'Penugasan & Jadwal', icon: 'pi pi-fw pi-calendar-clock', to: '/public/teaching/schedules', requires: 'teaching.view' },
            { label: 'Jurnal Mengajar', icon: 'pi pi-fw pi-book', to: '/public/teaching/journals', requires: 'teaching.view' },
            { label: 'Laporan Kepatuhan Jurnal', icon: 'pi pi-fw pi-chart-bar', to: '/public/teaching/reports', requires: 'teaching.view' }
        ]
    },
    {
        label: 'Akun Saya',
        requires: null,
        items: [{ label: 'Profil Pengguna', icon: 'pi pi-fw pi-user', to: '/profile/user' }]
    },
    {
        label: 'Pengaturan Akses',
        requires: 'users.view',
        items: [
            { label: 'Manajemen Pengguna', icon: 'pi pi-fw pi-users', to: '/master/accounts', requires: 'users.view' },
            { label: 'Tipe Pengguna', icon: 'pi pi-fw pi-user-edit', to: '/master/typeusers', requires: 'master.manage' },
            { label: 'Peran & Hak Akses', icon: 'pi pi-fw pi-shield', to: '/master/roles', requires: 'roles.view' }
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
