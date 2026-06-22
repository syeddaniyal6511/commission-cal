<template>
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-lg p-8">
            <h1 class="text-2xl font-bold text-gray-800 mb-1">Create account</h1>
            <p class="text-gray-500 text-sm mb-6">Get started for free</p>

            <form @submit.prevent="handleRegister" novalidate>
                <!-- Name -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                    <input v-model="form.name" type="text" :class="inputClass(errors.name)" placeholder="John Doe" autocomplete="name" />
                    <p v-if="errors.name" class="mt-1 text-xs text-red-500">{{ errors.name }}</p>
                </div>

                <!-- Email -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input v-model="form.email" type="email" :class="inputClass(errors.email)" placeholder="you@example.com" autocomplete="email" />
                    <p v-if="errors.email" class="mt-1 text-xs text-red-500">{{ errors.email }}</p>
                </div>

                <!-- Password -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input v-model="form.password" type="password" :class="inputClass(errors.password)" placeholder="Min. 8 characters" autocomplete="new-password" />
                    <p v-if="errors.password" class="mt-1 text-xs text-red-500">{{ errors.password }}</p>
                </div>

                <!-- Confirm Password -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                    <input v-model="form.password_confirmation" type="password" :class="inputClass(errors.password_confirmation)" placeholder="Repeat password" autocomplete="new-password" />
                    <p v-if="errors.password_confirmation" class="mt-1 text-xs text-red-500">{{ errors.password_confirmation }}</p>
                </div>

                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 rounded-lg text-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {{ loading ? 'Creating account…' : 'Create account' }}
                </button>
            </form>

            <p class="mt-5 text-center text-sm text-gray-500">
                Already have an account?
                <router-link to="/login" class="text-indigo-600 font-medium hover:underline">Sign in</router-link>
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

const form = reactive({
    name: '', email: '',
    password: '', password_confirmation: '',
});

const errors = reactive({
    name: '', email: '',
    password: '', password_confirmation: '',
});

const inputClass = (error) => [
    'w-full px-4 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors',
    error ? 'border-red-400 bg-red-50' : 'border-gray-300',
];

function validate() {
    Object.keys(errors).forEach(k => (errors[k] = ''));
    let valid = true;

    if (!form.name.trim()) { errors.name = 'Name is required.'; valid = false; }

    if (!form.email) { errors.email = 'Email is required.'; valid = false; }
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) { errors.email = 'Enter a valid email.'; valid = false; }

    if (!form.password) { errors.password = 'Password is required.'; valid = false; }
    else if (form.password.length < 8) { errors.password = 'Password must be at least 8 characters.'; valid = false; }

    if (form.password !== form.password_confirmation) {
        errors.password_confirmation = 'Passwords do not match.';
        valid = false;
    }

    return valid;
}

async function handleRegister() {
    if (!validate()) return;
    loading.value = true;
    try {
        const data = await AuthService.register(form);
        authStore.login(data);
        toast.success('Account created successfully', { position: 'top-right', duration: 3000 });
        router.push({ name: 'dashboard' });
    } catch (e) {
        const msg = e.response?.data?.message ?? 'Registration failed. Please try again.';
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
