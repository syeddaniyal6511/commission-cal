<template>
    <div class="max-w-2xl">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Settings</h1>

        <!-- Tab navigation -->
        <div class="border-b border-gray-200 mb-6">
            <nav class="flex gap-1">
                <router-link
                    v-for="tab in tabs"
                    :key="tab.route"
                    :to="{ name: tab.route }"
                    active-class="border-indigo-600 text-indigo-600"
                    class="flex items-center gap-2 px-4 py-3 text-sm font-medium text-gray-500 border-b-2 border-transparent hover:text-gray-700 hover:border-gray-300 transition-colors -mb-px"
                >
                    <font-awesome-icon :icon="tab.icon" class="text-xs" />
                    {{ tab.name }}
                </router-link>
            </nav>
        </div>

        <!-- Tab content -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
            <router-view />
        </div>
    </div>
</template>

<script setup>
const tabs = [
    { icon: 'user', name: 'Profile',  route: 'settings.profile'  },
    { icon: 'lock', name: 'Password', route: 'settings.password' },
];
</script>
