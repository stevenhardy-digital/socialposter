import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

// Import components
import Login from '../components/auth/Login.vue';
import Register from '../components/auth/Register.vue';
import Dashboard from '../components/Dashboard.vue';
import AccountSettings from '../components/accounts/AccountSettings.vue';
import BrandGuidelines from '../components/brand/BrandGuidelines.vue';
import PostManagement from '../components/posts/PostManagement.vue';
import Calendar from '../components/calendar/Calendar.vue';
import Analytics from '../components/analytics/Analytics.vue';
import SystemStatus from '../components/SystemStatus.vue';

const routes = [
    {
        path: '/login',
        name: 'Login',
        component: Login,
        meta: { requiresGuest: true }
    },
    {
        path: '/register',
        name: 'Register',
        component: Register,
        meta: { requiresGuest: true }
    },
    {
        path: '/',
        name: 'Dashboard',
        component: Dashboard,
        meta: { requiresAuth: true }
    },
    {
        path: '/accounts',
        name: 'AccountSettings',
        component: AccountSettings,
        meta: { requiresAuth: true }
    },
    {
        path: '/brand-guidelines',
        name: 'BrandGuidelines',
        component: BrandGuidelines,
        meta: { requiresAuth: true }
    },
    {
        path: '/posts',
        name: 'PostManagement',
        component: PostManagement,
        meta: { requiresAuth: true }
    },
    {
        path: '/calendar',
        name: 'Calendar',
        component: Calendar,
        meta: { requiresAuth: true }
    },
    {
        path: '/analytics',
        name: 'Analytics',
        component: Analytics,
        meta: { requiresAuth: true }
    },
    {
        path: '/system-status',
        name: 'SystemStatus',
        component: SystemStatus,
        meta: { requiresAuth: true }
    }
];

const router = createRouter({
    history: createWebHistory(),
    routes
});

// Navigation guards
router.beforeEach((to, from, next) => {
    const authStore = useAuthStore();
    
    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        next('/login');
    } else if (to.meta.requiresGuest && authStore.isAuthenticated) {
        next('/');
    } else {
        next();
    }
});

export default router;