<template>
    <div class="min-h-screen bg-gray-50 flex">

        <!-- Sidebar -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-50 w-64 bg-gray-900 text-white flex flex-col transition-transform duration-300 ease-in-out',
                'lg:relative lg:translate-x-0',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full'
            ]"
        >
            <!-- Logo -->
            <div class="flex items-center justify-between h-16 px-6 border-b border-gray-700 shrink-0">
                <span class="text-lg font-bold tracking-tight">{{ appName }}</span>
                <button class="lg:hidden text-gray-400 hover:text-white" @click="sidebarOpen = false">
                    <font-awesome-icon icon="times-circle" />
                </button>
            </div>

            <!-- Nav Links -->
            <nav class="flex-1 overflow-y-auto mt-4 px-3 space-y-1">
                <router-link
                    v-for="link in navLinks"
                    :key="link.name"
                    :to="{ name: link.name }"
                    active-class="bg-indigo-600 text-white"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-gray-400 hover:bg-gray-700 hover:text-white transition-colors"
                    @click="sidebarOpen = false"
                >
                    <font-awesome-icon :icon="link.icon" class="w-4 shrink-0" fixed-width />
                    {{ link.label }}
                </router-link>
            </nav>

            <!-- Sidebar footer -->
            <div class="shrink-0 p-3 border-t border-gray-700">
                <button
                    @click="logout"
                    class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-gray-400 hover:bg-red-600 hover:text-white transition-colors"
                >
                    <font-awesome-icon icon="sign-out-alt" fixed-width />
                    Logout
                </button>
            </div>
        </aside>

        <!-- Mobile overlay -->
        <div v-if="sidebarOpen" class="fixed inset-0 z-40 bg-black/50 lg:hidden" @click="sidebarOpen = false" />

        <!-- Main -->
        <div class="flex-1 flex flex-col min-w-0">

            <!-- Top bar -->
            <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-4 lg:px-6 sticky top-0 z-30 shrink-0">
                <button class="lg:hidden text-gray-500 hover:text-gray-700" @click="sidebarOpen = true">
                    <font-awesome-icon icon="bars" />
                </button>

                <div class="ml-auto flex items-center gap-3" ref="userMenuRef">
                    <button
                        @click="userMenuOpen = !userMenuOpen"
                        class="flex items-center gap-2 text-sm text-gray-700 hover:text-gray-900 focus:outline-none"
                    >
                        <div class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-white text-xs font-bold">
                            {{ authStore.userInitial }}
                        </div>
                        <span class="hidden sm:block font-medium">{{ authStore.userName }}</span>
                        <font-awesome-icon :icon="userMenuOpen ? 'angle-up' : 'angle-down'" class="text-gray-400 text-xs" />
                    </button>

                    <!-- Dropdown -->
                    <div v-if="userMenuOpen" class="absolute right-4 top-14 w-44 bg-white rounded-xl shadow-lg border border-gray-100 py-1 z-50">
                        <router-link
                            :to="{ name: 'settings.profile' }"
                            class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                            @click="userMenuOpen = false"
                        >
                            <font-awesome-icon icon="user" fixed-width class="text-gray-400" />
                            Profile
                        </router-link>
                        <router-link
                            :to="{ name: 'settings.password' }"
                            class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                            @click="userMenuOpen = false"
                        >
                            <font-awesome-icon icon="lock" fixed-width class="text-gray-400" />
                            Password
                        </router-link>
                        <hr class="my-1 border-gray-100" />
                        <button
                            @click="logout"
                            class="w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50"
                        >
                            <font-awesome-icon icon="sign-out-alt" fixed-width />
                            Logout
                        </button>
                    </div>
                </div>
            </header>

            <!-- Page content -->
            <main class="flex-1 p-4 lg:p-6 overflow-auto">
                <router-view />
            </main>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../store/auth';
import AuthService from '../services/AuthService';
import { useToast } from 'vue-toast-notification';

const router    = useRouter();
const authStore = useAuthStore();
const toast     = useToast();

const sidebarOpen  = ref(false);
const userMenuOpen = ref(false);
const userMenuRef  = ref(null);

const appName = import.meta.env.VITE_APP_NAME ?? 'MyApp';

const navLinks = [
    { name: 'dashboard',        label: 'Dashboard', icon: 'home'             },
    { name: 'contracts',        label: 'Contracts', icon: 'file-contract'    },
    { name: 'formulas',         label: 'Formula',   icon: 'calculator'       },
    { name: 'settings.profile', label: 'Settings',  icon: 'cog'              },
];

async function logout() {
    try { await AuthService.logout(); } catch (_) { /* clear local state regardless */ }
    authStore.logout();
    toast.success('Logged out successfully', { position: 'top-right', duration: 3000 });
    router.push({ name: 'Login' });
}

function onClickOutside(e) {
    if (userMenuRef.value && !userMenuRef.value.contains(e.target)) {
        userMenuOpen.value = false;
    }
}

onMounted(()  => document.addEventListener('mousedown', onClickOutside));
onUnmounted(() => document.removeEventListener('mousedown', onClickOutside));
</script>
