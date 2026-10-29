import AppLayout from '@/layout/AppLayout.vue';
import { createRouter, createWebHistory } from 'vue-router';
import { authStore } from '../stores/authStore';

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
                    component: () => import('@/views/Dashboard.vue')
                },
                {
                    path: '/public/schoolprofile',
                    name: 'schoolprofile',
                    meta: { breadcrumb: 'School Profile' },
                    component: () => import('@/views/pages/public/SchoolProfile.vue')
                },
                {
                    path: '/public/educationlevels',
                    name: 'educationLevels',
                    meta: { breadcrumb: 'Education Levels', requiresAbility: 'master.view' },
                    component: () => import('@/views/pages/public/EducationLevels.vue')
                },
                {
                    path: '/public/educationschools',
                    name: 'educationSchools',
                    meta: { breadcrumb: 'Education Schools', requiresAbility: 'master.view' },
                    component: () => import('@/views/pages/public/EducationSchools.vue')
                },
                {
                    path: '/public/phasestatuses',
                    name: 'phasestatuses',
                    meta: { breadcrumb: 'Phase Status', requiresAbility: 'master.view' },
                    component: () => import('@/views/pages/public/PhaseStatus.vue')
                },
                {
                    path: '/public/professions',
                    name: 'professions',
                    meta: { breadcrumb: 'Profession', requiresAbility: 'master.view' },
                    component: () => import('@/views/pages/public/Profession.vue')
                },
                {
                    path: '/public/schoolyears',
                    name: 'schoolyears',
                    meta: { breadcrumb: 'School Years', requiresAbility: 'master.view' },
                    component: () => import('@/views/pages/public/SchoolYears.vue')
                },
                {
                    path: '/public/schoolrombels',
                    name: 'schoolrombels',
                    meta: { breadcrumb: 'School Rombels', requiresAbility: 'master.view' },
                    component: () => import('@/views/pages/public/SchoolRombels.vue')
                },
                {
                    path: '/public/studentmajors',
                    name: 'studentmajors',
                    meta: { breadcrumb: 'Student Majors', requiresAbility: 'master.view' },
                    component: () => import('@/views/pages/public/StudentMajors.vue')
                },
                {
                    path: '/public/students',
                    name: 'students',
                    meta: { breadcrumb: 'Students', requiresAbility: 'students.view' },
                    component: () => import('@/views/pages/public/Student.vue')
                },
                {
                    path: '/public/teachers',
                    name: 'teachers',
                    meta: { breadcrumb: 'Teachers', requiresAbility: 'employees.view' },
                    props: { employeeTypeName: 'Pendidik' },
                    component: () => import('@/views/pages/public/EmployeeDirectory.vue')
                },
                {
                    path: '/public/employees',
                    name: 'employees',
                    meta: { breadcrumb: 'Employees', requiresAbility: 'employees.view' },
                    props: { employeeTypeName: 'Tenaga Kependidikan' },
                    component: () => import('@/views/pages/public/EmployeeDirectory.vue')
                },
                {
                    path: '/profile/user',
                    name: 'profileUser',
                    meta: { breadcrumb: 'Profile' },
                    component: () => import('@/views/pages/auth/Profile.vue')
                },
                {
                    path: '/master/accounts',
                    name: 'masteraccounts',
                    meta: { breadcrumb: 'Accounts', requiresAbility: 'users.view' },
                    component: () => import('@/views/pages/master/Accounts.vue')
                },
                {
                    path: '/master/typeusers',
                    name: 'mastertypeusers',
                    meta: { breadcrumb: 'Type Users', requiresAbility: 'master.manage' },
                    component: () => import('@/views/pages/master/TypeUsers.vue')
                },
                {
                    path: '/master/roles',
                    name: 'masterroles',
                    meta: { breadcrumb: 'Roles & Abilities', requiresAbility: 'roles.view' },
                    component: () => import('@/views/pages/master/Roles.vue')
                },
                {
                    path: '/uikit/formlayout',
                    name: 'formlayout',
                    component: () => import('@/views/uikit/FormLayout.vue')
                },
                {
                    path: '/uikit/input',
                    name: 'input',
                    component: () => import('@/views/uikit/InputDoc.vue')
                },
                {
                    path: '/uikit/button',
                    name: 'button',
                    component: () => import('@/views/uikit/ButtonDoc.vue')
                },
                {
                    path: '/uikit/table',
                    name: 'table',
                    component: () => import('@/views/uikit/TableDoc.vue')
                },
                {
                    path: '/uikit/list',
                    name: 'list',
                    component: () => import('@/views/uikit/ListDoc.vue')
                },
                {
                    path: '/uikit/tree',
                    name: 'tree',
                    component: () => import('@/views/uikit/TreeDoc.vue')
                },
                {
                    path: '/uikit/panel',
                    name: 'panel',
                    component: () => import('@/views/uikit/PanelsDoc.vue')
                },

                {
                    path: '/uikit/overlay',
                    name: 'overlay',
                    component: () => import('@/views/uikit/OverlayDoc.vue')
                },
                {
                    path: '/uikit/media',
                    name: 'media',
                    component: () => import('@/views/uikit/MediaDoc.vue')
                },
                {
                    path: '/uikit/message',
                    name: 'message',
                    component: () => import('@/views/uikit/MessagesDoc.vue')
                },
                {
                    path: '/uikit/file',
                    name: 'file',
                    component: () => import('@/views/uikit/FileDoc.vue')
                },
                {
                    path: '/uikit/menu',
                    name: 'menu',
                    component: () => import('@/views/uikit/MenuDoc.vue')
                },
                {
                    path: '/uikit/charts',
                    name: 'charts',
                    component: () => import('@/views/uikit/ChartDoc.vue')
                },
                {
                    path: '/uikit/misc',
                    name: 'misc',
                    component: () => import('@/views/uikit/MiscDoc.vue')
                },
                {
                    path: '/uikit/timeline',
                    name: 'timeline',
                    component: () => import('@/views/uikit/TimelineDoc.vue')
                },
                {
                    path: '/pages/empty',
                    name: 'empty',
                    component: () => import('@/views/pages/Empty.vue')
                },
                {
                    path: '/pages/crud',
                    name: 'crud',
                    component: () => import('@/views/pages/Crud.vue')
                },
                {
                    path: '/documentation',
                    name: 'documentation',
                    component: () => import('@/views/pages/Documentation.vue')
                }
            ]
        },
        {
            path: '/landing',
            name: 'landing',
            meta: { public: true },
            component: () => import('@/views/pages/Landing.vue')
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

    // Evaluasi kemampuan spesifik per halaman (berlaku pada navigasi langsung dan antar-child)
    const requiredAbility = to.meta?.requiresAbility;
    if (requiredAbility && !useAuthStore.can(requiredAbility)) {
        console.warn(`Access denied: missing required ability "${requiredAbility}".`);
        next({ path: '/auth/access' });
        return;
    }

    next();
});

export default router;
