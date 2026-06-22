<template>
    <div>
        <h2 class="text-base font-semibold text-gray-800 mb-4">Profile Information</h2>

        <div v-if="fetching" class="text-sm text-gray-400">Loading…</div>

        <form v-else @submit.prevent="update" novalidate class="space-y-4 max-w-md">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                <input v-model="form.name" type="text" :class="inputClass(errors.name)" placeholder="Your name" />
                <p v-if="errors.name" class="mt-1 text-xs text-red-500">{{ errors.name }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input v-model="form.email" type="email" :class="inputClass(errors.email)" placeholder="you@example.com" />
                <p v-if="errors.email" class="mt-1 text-xs text-red-500">{{ errors.email }}</p>
            </div>

            <div class="pt-1">
                <button
                    type="submit"
                    :disabled="loading"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-6 py-2.5 rounded-lg transition-colors disabled:opacity-50"
                >
                    {{ loading ? 'Saving…' : 'Save changes' }}
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { reactive, ref, onMounted } from 'vue';
import { useToast } from 'vue-toast-notification';
import AuthService from '../../services/AuthService';

const toast    = useToast();
const loading  = ref(false);
const fetching = ref(true);

const form   = reactive({ name: '', email: '' });
const errors = reactive({ name: '', email: '' });

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
    return valid;
}

onMounted(async () => {
    try {
        const { data } = await AuthService.getProfile();
        form.name  = data.name  ?? '';
        form.email = data.email ?? '';
    } catch (_) {
        toast.error('Failed to load profile.', { position: 'top-right', duration: 3000 });
    } finally {
        fetching.value = false;
    }
});

async function update() {
    if (!validate()) return;
    loading.value = true;
    try {
        await AuthService.updateProfile(form);
        toast.success('Profile updated successfully.', { position: 'top-right', duration: 3000 });
    } catch (e) {
        const msg = e.response?.data?.message ?? 'Failed to update profile.';
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
