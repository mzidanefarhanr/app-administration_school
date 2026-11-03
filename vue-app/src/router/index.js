import AppLayout from '@/layout/AppLayout.vue';
import { createRouter, createWebHistory } from 'vue-router';
import { authStore } from '../stores/authStore';
import { isRouteAbilitySatisfied } from './routeAbility.mjs';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            component: AppLayout,
            meta: { requiresAuth: true },
            children: [
                {
                    path: '/',
                    name: 'dashboard',
                    meta: { breadcrumb: 'Dasbor' },
                    component: () => import('@/views/Dashboard.vue')
                },
                {
                    path: '/public/schoolprofile',
                    name: 'schoolprofile',
                    meta: { breadcrumb: 'Profil Sekolah' },
                    component: () => import('@/views/pages/public/SchoolProfile.vue')
                },
                {
                    path: '/public/tasks',
                    name: 'tasks',
                    meta: { breadcrumb: 'Pusat Tugas & Pengingat', requiresAbility: 'tasks.view' },
                    component: () => import('@/views/tasks/TasksView.vue')
                },
                {
                    path: '/public/educationlevels',
                    name: 'educationLevels',
                    meta: { breadcrumb: 'Jenjang Pendidikan', requiresAbility: 'master.view' },
                    component: () => import('@/views/pages/public/EducationLevels.vue')
                },
                {
                    path: '/public/educationschools',
                    name: 'educationSchools',
                    meta: { breadcrumb: 'Sekolah', requiresAbility: 'master.view' },
                    component: () => import('@/views/pages/public/EducationSchools.vue')
                },
                {
                    path: '/public/phasestatuses',
                    name: 'phasestatuses',
                    meta: { breadcrumb: 'Status Fase', requiresAbility: 'master.view' },
                    component: () => import('@/views/pages/public/PhaseStatus.vue')
                },
                {
                    path: '/public/professions',
                    name: 'professions',
                    meta: { breadcrumb: 'Profesi', requiresAbility: 'master.view' },
                    component: () => import('@/views/pages/public/Profession.vue')
                },
                {
                    path: '/public/schoolyears',
                    name: 'schoolyears',
                    meta: { breadcrumb: 'Tahun Ajaran', requiresAbility: 'master.view' },
                    component: () => import('@/views/pages/public/SchoolYears.vue')
                },
                {
                    path: '/public/schoolrombels',
                    name: 'schoolrombels',
                    meta: { breadcrumb: 'Rombongan Belajar', requiresAbility: 'students.view' },
                    component: () => import('@/views/pages/public/SchoolRombels.vue')
                },
                {
                    path: '/public/progression',
                    name: 'progression',
                    meta: { breadcrumb: 'Kenaikan & Kelulusan', requiresAbility: 'students.view' },
                    component: () => import('@/views/pages/public/StudentProgression.vue')
                },
                {
                    path: '/public/studentmajors',
                    name: 'studentmajors',
                    meta: { breadcrumb: 'Jurusan Siswa', requiresAbility: 'master.view' },
                    component: () => import('@/views/pages/public/StudentMajors.vue')
                },
                {
                    path: '/public/students',
                    name: 'students',
                    meta: { breadcrumb: 'Data Siswa', requiresAbility: 'students.view' },
                    component: () => import('@/views/pages/public/Student.vue')
                },
                {
                    path: '/public/letters/incoming',
                    name: 'incomingLetters',
                    meta: { breadcrumb: 'Surat Masuk', requiresAbility: 'letters.view' },
                    component: () => import('@/views/pages/public/IncomingLetters.vue')
                },
                {
                    path: '/public/letters/outgoing',
                    name: 'outgoingLetters',
                    meta: { breadcrumb: 'Surat Keluar', requiresAbility: 'letters.view' },
                    component: () => import('@/views/pages/public/OutgoingLetters.vue')
                },
                {
                    path: '/public/letters/settings',
                    name: 'letterSettings',
                    meta: { breadcrumb: 'Pengaturan Surat', requiresAbility: 'letters.view' },
                    component: () => import('@/views/pages/public/LetterSettings.vue')
                },
                {
                    path: '/public/letters/dispositions',
                    name: 'letterDispositions',
                    meta: { breadcrumb: 'Disposisi Surat', requiresAbility: 'letters.view' },
                    component: () => import('@/views/pages/public/LetterDispositions.vue')
                },
                {
                    path: '/public/leaves',
                    name: 'leaves',
                    meta: { breadcrumb: 'Pengajuan Izin & Cuti', requiresAbility: ['leaves.view', 'leaves.request'] },
                    component: () => import('@/views/pages/public/Leaves.vue')
                },
                {
                    path: '/public/archives',
                    name: 'archives',
                    meta: { breadcrumb: 'Repositori Arsip', requiresAbility: 'archives.view' },
                    component: () => import('@/views/pages/public/Archives.vue')
                },
                {
                    path: '/public/teaching/schedules',
                    alias: '/teaching/schedules',
                    name: 'teachingSchedules',
                    meta: { breadcrumb: 'Penugasan & Jadwal', requiresAbility: 'teaching.view' },
                    component: () => import('@/views/teaching/TeachingSchedulesView.vue')
                },
                {
                    path: '/public/teaching/journals',
                    alias: '/teaching/journals',
                    name: 'teachingJournals',
                    meta: { breadcrumb: 'Jurnal Mengajar', requiresAbility: 'teaching.view' },
                    component: () => import('@/views/teaching/TeachingJournalsView.vue')
                },
                {
                    path: '/public/teaching/reports',
                    alias: '/teaching/reports',
                    name: 'teachingReports',
                    meta: { breadcrumb: 'Laporan Kepatuhan Jurnal', requiresAbility: 'teaching.view' },
                    component: () => import('@/views/teaching/TeachingReportsView.vue')
                },
                {
                    path: '/public/data-quality',
                    name: 'dataquality',
                    meta: { breadcrumb: 'Kualitas Data', requiresAbility: ['students.view', 'employees.view'] },
                    component: () => import('@/views/pages/public/DataQuality.vue')
                },
                {
                    path: '/public/reports/school',
                    name: 'schoolReports',
                    meta: { breadcrumb: 'Laporan Sekolah', requiresAbility: 'students.view' },
                    component: () => import('@/views/reports/SchoolReportsView.vue')
                },
                {
                    path: '/public/teachers',
                    name: 'teachers',
                    meta: { breadcrumb: 'Direktori Guru', requiresAbility: 'employees.view' },
                    props: { employeeTypeName: 'Pendidik' },
                    component: () => import('@/views/pages/public/EmployeeDirectory.vue')
                },
                {
                    path: '/public/employees',
                    name: 'employees',
                    meta: { breadcrumb: 'Direktori Pegawai', requiresAbility: 'employees.view' },
                    props: { employeeTypeName: 'Tenaga Kependidikan' },
                    component: () => import('@/views/pages/public/EmployeeDirectory.vue')
                },
                {
                    path: '/profile/user',
                    name: 'profileUser',
                    meta: { breadcrumb: 'Profil Pengguna' },
                    component: () => import('@/views/pages/auth/Profile.vue')
                },
                {
                    path: '/master/accounts',
                    name: 'masteraccounts',
                    meta: { breadcrumb: 'Manajemen Pengguna', requiresAbility: 'users.view' },
                    component: () => import('@/views/pages/master/Accounts.vue')
                },
                {
                    path: '/master/typeusers',
                    name: 'mastertypeusers',
                    meta: { breadcrumb: 'Tipe Pengguna', requiresAbility: 'master.manage' },
                    component: () => import('@/views/pages/master/TypeUsers.vue')
                },
                {
                    path: '/master/roles',
                    name: 'masterroles',
                    meta: { breadcrumb: 'Peran & Hak Akses', requiresAbility: 'roles.view' },
                    component: () => import('@/views/pages/master/Roles.vue')
                }
            ]
        },
        {
            path: '/pages/notfound',
            name: 'notfound',
            meta: { public: true },
            component: () => import('@/views/pages/NotFound.vue')
        },
        {
            path: '/auth/login',
            name: 'login',
            meta: { guestOnly: true },
            component: () => import('@/views/pages/auth/Login_2.vue')
        },
        {
            path: '/auth/forgot-password',
            name: 'forgotPassword',
            meta: { guestOnly: true },
            component: () => import('@/views/pages/auth/ForgotPassword.vue')
        },
        {
            path: '/password-reset/:token',
            name: 'passwordReset',
            meta: { guestOnly: true },
            component: () => import('@/views/pages/auth/ResetPassword.vue')
        },
        {
            path: '/auth/reset-password/:token',
            name: 'resetPassword',
            meta: { guestOnly: true },
            component: () => import('@/views/pages/auth/ResetPassword.vue')
        },
        {
            path: '/auth/logout',
            name: 'logout',
            meta: { requiresAuth: true },
            component: () => import('@/views/pages/auth/Logout.vue')
        },
        {
            path: '/auth/access',
            name: 'accessDenied',
            meta: { public: true },
            component: () => import('@/views/pages/auth/Access.vue')
        },
        {
            path: '/auth/error',
            name: 'error',
            meta: { public: true },
            component: () => import('@/views/pages/auth/Error.vue')
        },
        {
            path: '/:pathMatch(.*)',
            name: 'notFound',
            meta: { public: true },
            component: () => import('@/views/pages/NotFound.vue')
        }
    ]
});

// Guard global: dievaluasi pada navigasi langsung maupun antar-child
router.beforeEach(async (to, from, next) => {
    const useAuthStore = authStore();

    // Inisialisasi identitas sesi saat awal buka atau refresh
    if (useAuthStore.identity === null && useAuthStore.hasLogin === null) {
        await useAuthStore.fetchIdentity();
    }

    const isAuthenticated = !!useAuthStore.hasLogin;
    const isGuestOnly = to.matched.some((record) => record.meta?.guestOnly);
    const isPublic = to.matched.some((record) => record.meta?.public);
    const requiresAuth = to.matched.some((record) => record.meta?.requiresAuth) || (!isPublic && !isGuestOnly);

    // Pengguna yang sudah login dilarang membuka halaman guest-only (login/forgot-password/reset-password)
    if (isAuthenticated && isGuestOnly) {
        next({ path: '/' });
        return;
    }

    // Pengguna belum login ditolak dari rute terproteksi
    if (!isAuthenticated && requiresAuth) {
        next({ path: '/auth/login' });
        return;
    }

    // Evaluasi kemampuan spesifik per halaman (berlaku pada navigasi langsung dan antar-child).
    // `requiresAbility` boleh berupa satu string (lama) atau array yang lolos bila salah satu ada.
    const requiredAbility = to.meta?.requiresAbility;
    if (!isRouteAbilitySatisfied(requiredAbility, useAuthStore)) {
        const missing = Array.isArray(requiredAbility) ? requiredAbility.join(' or ') : requiredAbility;
        console.warn(`Access denied: missing required ability "${missing}".`);
        next({ path: '/auth/access' });
        return;
    }

    next();
});

export default router;
