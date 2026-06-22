<script setup>
import { ref, onMounted, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { useToast } from 'vue-toast-notification';
import useContracts from '../../composables/useContracts.js';

const toast = useToast();

const {
    contracts, pagination, loading, calculating,
    fetchContracts, deleteContract, calculateCommission,
} = useContracts();

const search      = ref('');
const page        = ref(1);
const perPage     = ref(15);
const deleteId    = ref(null);
const deleteLabel = ref('');

const calcResult     = ref(null);
const calcContractNo = ref('');
const calcCurrentId  = ref(null);
const calculatingId  = ref(null);

let searchTimer = null;

async function load() {
    await fetchContracts({ search: search.value, page: page.value, per_page: perPage.value });
}

onMounted(load);

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        page.value = 1;
        load();
    }, 350);
});

function changePage(p) {
    if (p < 1 || p > pagination.value?.last_page) return;
    page.value = p;
    load();
}

function confirmDelete(contract) {
    deleteId.value    = contract.id;
    deleteLabel.value = contract.contract_no;
}

function cancelDelete() {
    deleteId.value    = null;
    deleteLabel.value = '';
}

async function handleDelete() {
    const id = deleteId.value;
    cancelDelete();
    const result = await deleteContract(id);
    if (result.success) {
        toast.success('Contract deleted.');
        load();
    } else {
        toast.error(result.message);
    }
}

async function handleCalculate(contract) {
    calcResult.value     = null;
    calculatingId.value  = contract.id;
    const result = await calculateCommission(contract.id);
    calculatingId.value  = null;
    if (result.success) {
        calcResult.value     = result.data;
        calcContractNo.value = contract.contract_no;
        calcCurrentId.value  = contract.id;
    } else {
        toast.error(result.message);
    }
}

function closeModal() {
    calcResult.value = null;
}

function formatNumber(val, decimals = 2) {
    return Number(val).toLocaleString('en-GB', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
}

</script>

<template>
    <div class="space-y-6">

        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Contracts</h1>
                <p v-if="pagination" class="text-sm text-gray-500 mt-0.5">
                    {{ pagination.total.toLocaleString() }} total contracts
                </p>
            </div>
            <RouterLink
                :to="{ name: 'contracts.create' }"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-brand-orange text-white text-sm font-medium hover:bg-brand-orange/90 transition"
            >
                <font-awesome-icon icon="plus" />
                New contract
            </RouterLink>
        </div>

        <!-- Search -->
        <div class="relative">
            <font-awesome-icon
                icon="magnifying-glass"
                class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"
            />
            <input
                v-model="search"
                type="text"
                placeholder="Search by contract no…"
                class="w-full sm:w-80 rounded-lg border border-gray-300 pl-9 pr-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-orange/50 focus:border-brand-orange transition"
            />
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div v-if="loading" class="flex items-center justify-center py-16 text-gray-400 text-sm gap-2">
                <font-awesome-icon icon="circle-notch" spin />
                Loading…
            </div>

            <template v-else-if="contracts.length">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="text-left px-4 py-3 font-medium text-gray-600">Contract No</th>
                                <th class="text-right px-4 py-3 font-medium text-gray-600">Annual Usage</th>
                                <th class="text-right px-4 py-3 font-medium text-gray-600">Value (£)</th>
                                <th class="text-center px-4 py-3 font-medium text-gray-600">Length</th>
                                <th class="text-center px-4 py-3 font-medium text-gray-600">Risk Score</th>
                                <th class="text-right px-4 py-3 font-medium text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr
                                v-for="c in contracts"
                                :key="c.id"
                                class="hover:bg-gray-50 transition"
                            >
                                <td class="px-4 py-3 font-mono font-medium text-gray-900">{{ c.contract_no }}</td>
                                <td class="px-4 py-3 text-right text-gray-700">{{ formatNumber(c.annual_usage) }}</td>
                                <td class="px-4 py-3 text-right text-gray-700">{{ formatNumber(c.contract_value) }}</td>
                                <td class="px-4 py-3 text-center text-gray-700">{{ c.contract_length }} mo</td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        class="inline-block px-2 py-0.5 rounded-full text-xs font-medium"
                                        :class="
                                            c.risk_score >= 75 ? 'bg-red-100 text-red-700' :
                                            c.risk_score >= 40 ? 'bg-yellow-100 text-yellow-700' :
                                            'bg-green-100 text-green-700'
                                        "
                                    >
                                        {{ Number(c.risk_score).toFixed(2) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div v-if="deleteId === c.id" class="inline-flex items-center gap-2 text-xs">
                                        <span class="text-gray-600">Delete?</span>
                                        <button @click="handleDelete" class="text-red-600 font-medium hover:underline">Yes</button>
                                        <button @click="cancelDelete" class="text-gray-500 hover:underline">Cancel</button>
                                    </div>
                                    <div v-else class="inline-flex items-center gap-3">
                                        <button
                                            @click="handleCalculate(c)"
                                            :disabled="calculatingId === c.id"
                                            class="inline-flex items-center gap-1 text-xs font-medium text-brand-blue hover:text-brand-orange transition disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            <font-awesome-icon
                                                :icon="calculatingId === c.id ? 'circle-notch' : 'calculator'"
                                                :spin="calculatingId === c.id"
                                                class="text-[11px]"
                                            />
                                            Calculate
                                        </button>
                                        <RouterLink
                                            :to="{ name: 'contracts.calculations', params: { id: c.id }, query: { contract_no: c.contract_no } }"
                                            class="inline-flex items-center gap-1 text-xs font-medium text-gray-500 hover:text-brand-orange transition"
                                        >
                                            <font-awesome-icon icon="clock" class="text-[11px]" />
                                            History
                                        </RouterLink>
                                        <RouterLink
                                            :to="{ name: 'contracts.edit', params: { id: c.id } }"
                                            class="text-xs font-medium text-gray-500 hover:text-brand-orange transition"
                                        >
                                            Edit
                                        </RouterLink>
                                        <button
                                            @click="confirmDelete(c)"
                                            class="text-xs font-medium text-red-500 hover:text-red-700 transition"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="pagination && pagination.last_page > 1"
                    class="flex items-center justify-between px-4 py-3 border-t border-gray-200 text-sm text-gray-600"
                >
                    <span>Showing {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total.toLocaleString() }}</span>
                    <div class="flex items-center gap-1">
                        <button
                            @click="changePage(pagination.current_page - 1)"
                            :disabled="pagination.current_page === 1"
                            class="px-2 py-1 rounded hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition"
                        >
                            <font-awesome-icon icon="chevron-left" class="text-xs" />
                        </button>
                        <template v-for="p in pagination.last_page" :key="p">
                            <button
                                v-if="Math.abs(p - pagination.current_page) <= 2 || p === 1 || p === pagination.last_page"
                                @click="changePage(p)"
                                class="px-2.5 py-1 rounded text-xs transition"
                                :class="p === pagination.current_page ? 'bg-brand-orange text-white font-medium' : 'hover:bg-gray-100'"
                            >{{ p }}</button>
                            <span
                                v-else-if="Math.abs(p - pagination.current_page) === 3"
                                class="px-1 text-gray-400"
                            >…</span>
                        </template>
                        <button
                            @click="changePage(pagination.current_page + 1)"
                            :disabled="pagination.current_page === pagination.last_page"
                            class="px-2 py-1 rounded hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition"
                        >
                            <font-awesome-icon icon="chevron-right" class="text-xs" />
                        </button>
                    </div>
                </div>
            </template>

            <div v-else class="flex flex-col items-center justify-center py-16 text-gray-400 gap-2">
                <font-awesome-icon icon="file-contract" class="text-3xl" />
                <p class="text-sm">No contracts found.</p>
            </div>
        </div>

        <!-- Commission Result Modal -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="calcResult"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40"
                @click.self="closeModal"
            >
                <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="transition ease-in duration-150"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div v-if="calcResult" class="bg-white rounded-2xl shadow-xl w-full max-w-md">

                        <!-- Modal header -->
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                            <div>
                                <h2 class="text-base font-semibold text-gray-900">Commission result</h2>
                                <p class="text-xs text-gray-500 mt-0.5 font-mono">{{ calcContractNo }}</p>
                            </div>
                            <button
                                @click="closeModal"
                                class="text-gray-400 hover:text-gray-600 transition"
                            >
                                <font-awesome-icon icon="times-circle" />
                            </button>
                        </div>

                        <!-- Commission highlight -->
                        <div class="px-6 py-5 bg-brand-orange/5 border-b border-brand-orange/10">
                            <p class="text-xs font-medium text-brand-orange uppercase tracking-wide mb-1">Calculated commission</p>
                            <p class="text-3xl font-bold text-gray-900">
                                £{{ formatNumber(calcResult.commission, 4) }}
                            </p>
                            <p class="text-xs text-gray-500 mt-1">
                                Formula version <span class="font-medium text-gray-700">{{ calcResult.formula_version }}</span>
                                &nbsp;·&nbsp; Calculation #{{ calcResult.calculation_id }}
                            </p>
                        </div>

                        <!-- Trace steps -->
                        <div class="px-6 py-4 space-y-4 max-h-72 overflow-y-auto">

                            <!-- Inputs -->
                            <div>
                                <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-2 flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-blue-400 inline-block"></span>
                                    Input values
                                </p>
                                <div class="space-y-1">
                                    <template v-for="step in calcResult.steps?.filter(s => s.type === 'input')" :key="step.name">
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600">{{ step.name }}</span>
                                            <span class="font-mono text-gray-900">{{ formatNumber(step.value, 2) }}</span>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Computed steps -->
                            <template v-if="calcResult.steps?.some(s => s.type === 'computed')">
                                <div>
                                    <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-2 flex items-center gap-1.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-brand-orange inline-block"></span>
                                        Calculation steps
                                    </p>
                                    <div class="space-y-2">
                                        <template v-for="(step, idx) in calcResult.steps?.filter(s => s.type === 'computed')" :key="step.name">
                                            <div class="bg-orange-50 rounded-lg px-3 py-2">
                                                <div class="flex justify-between items-start gap-2">
                                                    <div class="min-w-0">
                                                        <span class="text-xs text-orange-600 font-bold">{{ idx + 1 }}. {{ step.name }}</span>
                                                        <p class="text-[11px] font-mono text-gray-400 truncate">= {{ step.expression }}</p>
                                                    </div>
                                                    <span class="font-mono text-sm text-orange-700 font-medium shrink-0">{{ formatNumber(step.value, 4) }}</span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <!-- Result expression -->
                            <div v-if="calcResult.steps?.find(s => s.type === 'result')">
                                <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-2 flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span>
                                    Formula
                                </p>
                                <p class="text-xs font-mono text-gray-500 bg-gray-50 rounded px-3 py-2">
                                    {{ calcResult.steps.find(s => s.type === 'result').expression }}
                                </p>
                            </div>
                        </div>

                        <div class="px-6 pb-5 flex gap-2">
                            <RouterLink
                                :to="{ name: 'contracts.calculations', params: { id: calcCurrentId }, query: { contract_no: calcContractNo } }"
                                @click="closeModal"
                                class="flex-1 py-2 rounded-lg border border-gray-200 text-sm text-gray-600 hover:bg-gray-50 transition text-center"
                            >
                                <font-awesome-icon icon="clock" class="mr-1 text-xs" />
                                Full history
                            </RouterLink>
                            <button
                                @click="closeModal"
                                class="flex-1 py-2 rounded-lg bg-brand-orange text-white text-sm hover:bg-brand-orange/90 transition"
                            >
                                Done
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>

    </div>
</template>
