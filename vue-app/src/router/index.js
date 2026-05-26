import AppLayout from '@/layout/AppLayout.vue';
import { createRouter, createWebHistory } from 'vue-router';
import { authStore } from '../stores/authStore';

const authNav = async (to, from, next) => {
    const useAuthStore = authStore();
    await useAuthStore.checkLogin();

    if (useAuthStore.hasLogin && to.name == 'login') {
        useAuthStore.getToken();
        next({ path: '/' }); //success acces to dashboard
    } else if (!useAuthStore.hasLogin && to.name !== 'login') {
        next({ path: '/auth/login' }); //not acces to dashboard
    } else {
        // Ambil data user dari store
        // await useAuthStore.getUser();
        const user = useAuthStore.AllUserAssign;
        const typeUserId = user?.type_user_id;
        // console.log('to.meta.hasLogin: ', useAuthStore.hasLogin);
        // Jika User ID = 2 dan mencoba akses path yang dimulai dengan /master
        if (typeUserId == 2 && to.path.startsWith('/master')) {
            console.warn('Akses ditolak: User tidak boleh mengakses area Master');
            next({ path: '/auth/access' }); // Redirect ke halaman error/no access
        }
        else {
            // Jika typeUserId = 1 atau User ID 2 akses path non-master
            next();
        }

    }
};

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            beforeEnter: authNav,
            component: AppLayout,
            children: [
                {
                    path: '/',
                    name: 'dashboard',
                    component: () => import('@/views/Dashboard.vue')
                },
                {
                    path: '/public/educationlevels',
                    name: 'educationLevels',
                    meta: { breadcrumb: 'Education Levels' },
                    component: () => import('@/views/pages/public/EducationLevels.vue')
                },
                {
                    path: '/public/educationschools',
                    name: 'educationSchools',
                    meta: { breadcrumb: 'Education Schools' },
                    component: () => import('@/views/pages/public/EducationSchools.vue')
                },
                {
                    path: '/public/phasestatuses',
                    name: 'phasestatuses',
                    meta: { breadcrumb: 'Phase Status' },
                    component: () => import('@/views/pages/public/PhaseStatus.vue')
                },
                {
                    path: '/public/professions',
                    name: 'professions',
                    meta: { breadcrumb: 'Profession' },
                    component: () => import('@/views/pages/public/Profession.vue')
                },
                {
                    path: '/public/schoolyears',
                    name: 'schoolyears',
                    meta: { breadcrumb: 'School Years' },
                    component: () => import('@/views/pages/public/SchoolYears.vue')
                },
                {
                    path: '/public/schoolrombels',
                    name: 'schoolrombels',
                    meta: { breadcrumb: 'School Rombels' },
                    component: () => import('@/views/pages/public/SchoolRombels.vue')
                },
                {
                    path: '/public/studentmajors',
                    name: 'studentmajors',
                    meta: { breadcrumb: 'Student Majors' },
                    component: () => import('@/views/pages/public/StudentMajors.vue')
                },
                {
                    path: '/public/students',
                    name: 'students',
                    meta: { breadcrumb: 'Students' },
                    component: () => import('@/views/pages/public/Student.vue')
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
                    meta: { breadcrumb: 'Accounts' },
                    component: () => import('@/views/pages/master/Accounts.vue')
                },
                {
                    path: '/master/typeusers',
                    name: 'mastertypeusers',
                    meta: { breadcrumb: 'Type Users' },
                    component: () => import('@/views/pages/master/TypeUsers.vue')
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
            // beforeEnter: authNav,
            component: () => import('@/views/pages/Landing.vue')
        },
        {
            path: '/pages/notfound',
            name: 'notfound',
            // beforeEnter: authNav,
            component: () => import('@/views/pages/NotFound.vue')
        },

        {
            path: '/auth/login',
            name: 'login',
            beforeEnter: authNav,
            component: () => import('@/views/pages/auth/Login_2.vue')
        },
        {
            path: '/auth/logout',
            name: 'logout',
            beforeEnter: authNav,
            component: () => import('@/views/pages/auth/Logout.vue')
        },
        {
            path: '/auth/access',
            name: 'accessDenied',
            // beforeEnter: authNav,
            component: () => import('@/views/pages/auth/Access.vue')
        },
        {
            path: '/auth/error',
            name: 'error',
            // beforeEnter: authNav,
            component: () => import('@/views/pages/auth/Error.vue')
        },
        {
            path: '/:pathMatch(.*)',
            name: 'notFound',
            component: () => import('@/views/pages/NotFound.vue')
        }
    ]
});

export default router;
