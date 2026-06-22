<template>
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-lg p-8">
            <h1 class="text-2xl font-bold text-gray-800 mb-1">Welcome back</h1>
            <p class="text-gray-500 text-sm mb-6">Sign in to your account</p>

            <form @submit.prevent="handleLogin" novalidate>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input
                        v-model="form.email"
                        type="email"
                        :class="inputClass(errors.email)"
                        placeholder="you@example.com"
                        autocomplete="email"
                    />
                    <p v-if="errors.email" class="mt-1 text-xs text-red-500">{{ errors.email }}</p>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input
                        v-model="form.password"
                        type="password"
                        :class="inputClass(errors.password)"
                        placeholder="••••••••"
                        autocomplete="current-password"
                    />
                    <p v-if="errors.password" class="mt-1 text-xs text-red-500">{{ errors.password }}</p>
                </div>

                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 rounded-lg text-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {{ loading ? 'Signing in…' : 'Sign in' }}
                </button>
            </form>

            <p class="mt-5 text-center text-sm text-gray-500">
                Don't have an account?
                <router-link to="/register" class="text-indigo-600 font-medium hover:underline">Create one</router-link>
            </p>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'vue-toast-notification';
import { useAuthStore } from '../../store/auth';
import AuthService from '../../services/AuthService';

const router    = useRouter();
const authStore = useAuthStore();
const toast     = useToast();
const loading   = ref(false);

const form = reactive({ email: '', password: '' });
const errors = reactive({ email: '', password: '' });

const inputClass = (error) => [
    'w-full px-4 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors',
    error ? 'border-red-400 bg-red-50' : 'border-gray-300',
];

function validate() {
    errors.email    = '';
    errors.password = '';
    let valid = true;

    if (!form.email) {
        errors.email = 'Email is required.';
        valid = false;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
        errors.email = 'Enter a valid email address.';
        valid = false;
    }

    if (!form.password) {
        errors.password = 'Password is required.';
        valid = false;
    } else if (form.password.length < 8) {
        errors.password = 'Password must be at least 8 characters.';
        valid = false;
    }

    return valid;
}

async function handleLogin() {
    if (!validate()) return;
    loading.value = true;
    try {
        const data = await AuthService.login(form);
        authStore.login(data);
        toast.success('Logged in successfully', { position: 'top-right', duration: 3000 });
        router.push({ name: 'dashboard' });
    } catch (e) {
        const msg = e.response?.data?.message ?? 'Login failed. Please try again.';
        toast.error(msg, { position: 'top-right', duration: 4000 });
        if (e.response?.data?.errors) {
            Object.entries(e.response.data.errors).forEach(([k, v]) => {
                if (k in errors) errors[k] = Array.isArray(v) ? v[0] : v;
            });
        }
    } finally {
        loading.value = false;
    }
}
</script>
