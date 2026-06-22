import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../store/auth';

function isAuthenticated() {
    return useAuthStore().isAuthenticated;
}

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', redirect: { name: 'Login' } },

        // Auth (guest) routes — use the minimal centered layout
        { path: '/login',    name: 'Login',    component: () => import('../pages/auth/Login.vue'),    meta: { layout: 'default' } },
        { path: '/register', name: 'register', component: () => import('../pages/auth/Register.vue'), meta: { layout: 'default' } },

        // Protected routes — use the sidebar/navbar dashboard layout
        {
            path: '/dashboard',
            name: 'dashboard',
            component: () => import('../pages/Dashboard.vue'),
            meta: { requiresAuth: true, layout: 'dashboard' },
        },
        {
            path: '/formulas',
            name: 'formulas',
            component: () => import('../pages/FormulaList.vue'),
            meta: { requiresAuth: true, layout: 'dashboard' },
        },
        {
            path: '/formulas/create',
            name: 'formulas.create',
            component: () => import('../pages/FormulaBuilder.vue'),
            meta: { requiresAuth: true, layout: 'dashboard' },
        },
        {
            path: '/contracts',
            name: 'contracts',
            component: () => import('../pages/contracts/ContractList.vue'),
            meta: { requiresAuth: true, layout: 'dashboard' },
        },
        {
            path: '/contracts/create',
            name: 'contracts.create',
            component: () => import('../pages/contracts/ContractCreate.vue'),
            meta: { requiresAuth: true, layout: 'dashboard' },
        },
        {
            path: '/contracts/:id/edit',
            name: 'contracts.edit',
            component: () => import('../pages/contracts/ContractEdit.vue'),
            meta: { requiresAuth: true, layout: 'dashboard' },
        },
        {
            path: '/contracts/:id/calculations',
            name: 'contracts.calculations',
            component: () => import('../pages/contracts/ContractCalculations.vue'),
            meta: { requiresAuth: true, layout: 'dashboard' },
        },

        {
            path: '/settings',
            component: () => import('../pages/settings/index.vue'),
            meta: { requiresAuth: true, layout: 'dashboard' },
            children: [
                { path: '', redirect: { name: 'settings.profile' } },
                { path: 'profile',  name: 'settings.profile',  component: () => import('../pages/settings/profile.vue') },
                { path: 'password', name: 'settings.password', component: () => import('../pages/settings/password.vue') },
            ],
        },

        { path: '/:catchAll(.*)', redirect: { name: 'Login' } },
    ],
});

router.beforeEach((to, from, next) => {
    if (to.meta.requiresAuth && !isAuthenticated()) {
        next({ name: 'Login' });
    } else {
        next();
    }
});

export default router;
