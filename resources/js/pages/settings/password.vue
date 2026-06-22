<template>
    <div>
        <h2 class="text-base font-semibold text-gray-800 mb-4">Change Password</h2>

        <form @submit.prevent="updatePassword" novalidate class="space-y-4 max-w-md">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                <input
                    v-model="form.password"
                    type="password"
                    :class="inputClass(errors.password)"
                    placeholder="Min. 8 characters"
                    autocomplete="new-password"
                />
                <p v-if="errors.password" class="mt-1 text-xs text-red-500">{{ errors.password }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                <input
                    v-model="form.password_confirmation"
                    type="password"
                    :class="inputClass(errors.password_confirmation)"
                    placeholder="Repeat password"
                    autocomplete="new-password"
                />
                <p v-if="errors.password_confirmation" class="mt-1 text-xs text-red-500">{{ errors.password_confirmation }}</p>
            </div>

            <div class="pt-1">
                <button
                    type="submit"
                    :disabled="loading"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-6 py-2.5 rounded-lg transition-colors disabled:opacity-50"
                >
                    {{ loading ? 'Updating…' : 'Update password' }}
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useToast } from 'vue-toast-notification';
import AuthService from '../../services/AuthService';

const toast   = useToast();
const loading = ref(false);

const form   = reactive({ password: '', password_confirmation: '' });
const errors = reactive({ password: '', password_confirmation: '' });

const inputClass = (error) => [
    'w-full px-4 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors',
    error ? 'border-red-400 bg-red-50' : 'border-gray-300',
];

function validate() {
    errors.password = '';
    errors.password_confirmation = '';
    let valid = true;

    if (!form.password) { errors.password = 'Password is required.'; valid = false; }
    else if (form.password.length < 8) { errors.password = 'Password must be at least 8 characters.'; valid = false; }

    if (form.password !== form.password_confirmation) {
        errors.password_confirmation = 'Passwords do not match.';
        valid = false;
    }

    return valid;
}

async function updatePassword() {
    if (!validate()) return;
    loading.value = true;
    try {
        await AuthService.updatePassword(form);
        toast.success('Password updated successfully.', { position: 'top-right', duration: 3000 });
        form.password = '';
        form.password_confirmation = '';
    } catch (e) {
        const msg = e.response?.data?.message ?? 'Failed to update password.';
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
