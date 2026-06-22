<template>
    <div>
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Dashboard</h1>

        <!-- Stats grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div
                v-for="stat in stats"
                :key="stat.label"
                class="bg-white rounded-xl p-5 shadow-sm border border-gray-100 flex flex-col gap-3"
            >
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-500">{{ stat.label }}</span>
                    <div :class="['w-9 h-9 rounded-lg flex items-center justify-center', stat.bg]">
                        <font-awesome-icon :icon="stat.icon" :class="['text-sm', stat.color]" />
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900">
                    <span v-if="loadingStats">—</span>
                    <span v-else>{{ stat.value }}</span>
                </p>
                <p class="text-xs text-gray-400">{{ stat.sub }}</p>
            </div>
        </div>

        <!-- Welcome card -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
            <h2 class="text-lg font-semibold text-gray-800 mb-1">Welcome, {{ authStore.userName }}!</h2>
            <p class="text-gray-500 text-sm">This is your dashboard. Customize it to fit your project needs.</p>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useAuthStore } from '../store/auth';

const authStore    = useAuthStore();
const loadingStats = ref(true);

const contractCount  = ref(0);
const activeFormula  = ref('None');

onMounted(async () => {
    try {
        const { data } = await axios.get('/api/dashboard/stats');
        contractCount.value = data.contract_count;
        activeFormula.value = data.active_formula;
    } finally {
        loadingStats.value = false;
    }
});

const stats = computed(() => [
    {
        label: 'Total Contracts',
        value: contractCount.value,
        sub:   'All contracts in system',
        icon:  'file-contract',
        color: 'text-blue-600',
        bg:    'bg-blue-50',
    },
    {
        label: 'Active Formula',
        value: activeFormula.value,
        sub:   'Currently active formula version',
        icon:  'calculator',
        color: 'text-green-600',
        bg:    'bg-green-50',
    },
    {
        label: 'Revenue',
        value: '$0',
        sub:   'This month',
        icon:  'money-bill-wave',
        color: 'text-orange-600',
        bg:    'bg-orange-50',
    },
    {
        label: 'Reports',
        value: '0',
        sub:   'Generated reports',
        icon:  'file-text',
        color: 'text-purple-600',
        bg:    'bg-purple-50',
    },
]);
</script>
