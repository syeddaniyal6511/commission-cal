<template>
    <div class="min-h-screen bg-slate-50">
        <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">

            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-brand-blue">Formulas</h1>
                    <p class="mt-1 text-sm text-slate-500">Manage and activate commission formula versions.</p>
                </div>
                <router-link
                    :to="{ name: 'formulas.create' }"
                    class="rounded-lg bg-brand-orange px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90"
                >
                    + New formula
                </router-link>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-400">
                Loading formulas…
            </div>

            <!-- Error -->
            <div v-else-if="error" class="rounded-2xl border border-red-200 bg-red-50 p-6 text-sm text-red-700">
                {{ error }}
            </div>

            <!-- Empty -->
            <div
                v-else-if="formulas.length === 0"
                class="rounded-2xl border border-dashed border-slate-200 bg-white p-14 text-center"
            >
                <p class="text-4xl">📐</p>
                <p class="mt-3 text-sm font-medium text-slate-600">No formulas yet</p>
                <p class="mt-1 text-xs text-slate-400">Create your first commission formula to get started.</p>
                <router-link
                    :to="{ name: 'formulas.create' }"
                    class="mt-4 inline-block rounded-lg bg-brand-orange px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90"
                >
                    Create formula
                </router-link>
            </div>

            <!-- Formula list -->
            <div v-else class="space-y-3">
                <div
                    v-for="formula in sortedFormulas"
                    :key="formula.id"
                    class="flex overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                >
                    <!-- Active accent bar -->
                    <div class="w-1 shrink-0 transition-colors" :class="formula.is_active ? 'bg-green-500' : 'bg-slate-200'"></div>

                    <div class="flex flex-1 flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                        <!-- Left: info -->
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs font-semibold text-slate-600">
                                    v{{ formula.version }}
                                </span>
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="formula.is_active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'"
                                >
                                    {{ formula.is_active ? 'Active' : 'Inactive' }}
                                </span>
                                <span v-if="formula.dependent_variables?.length" class="text-xs text-slate-400">
                                    {{ formula.dependent_variables.length }} sub-variable{{ formula.dependent_variables.length !== 1 ? 's' : '' }}
                                </span>
                            </div>

                            <!-- Sub-variable expressions -->
                            <div v-if="formula.dependent_variables?.length" class="mt-2 space-y-1">
                                <div
                                    v-for="v in formula.dependent_variables"
                                    :key="v.id"
                                    class="flex items-center gap-1.5 rounded-md bg-brand-orange/5 px-2.5 py-1.5 font-mono text-xs text-slate-500"
                                >
                                    <span class="font-semibold text-brand-orange">{{ v.name }}</span>
                                    <span class="text-slate-300">=</span>
                                    <span>{{ v.expression }}</span>
                                </div>
                            </div>

                            <!-- Main expression -->
                            <p class="mt-1.5 rounded-md bg-slate-50 px-2.5 py-1.5 font-mono text-xs text-slate-600">
                                {{ formula.expression }}
                            </p>

                            <p class="mt-2 text-xs text-slate-400">
                                Created {{ formatDate(formula.created_at) }}
                            </p>
                        </div>

                        <!-- Right: actions -->
                        <div class="shrink-0">
                            <span v-if="formula.is_active" class="rounded-lg border border-green-200 bg-green-50 px-4 py-2 text-xs font-semibold text-green-700">
                                Currently active
                            </span>
                            <button
                                v-else
                                @click="openSimulation(formula.id)"
                                class="rounded-lg border border-brand-blue/20 px-4 py-2 text-xs font-medium text-brand-blue transition hover:border-brand-blue/40 hover:bg-brand-blue/5"
                            >
                                Activate
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Simulation modal -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="modal" class="fixed inset-0 z-50 flex items-center justify-center p-4">

                <!-- Backdrop -->
                <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="closeModal" />

                <!-- Panel -->
                <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl">

                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                        <div>
                            <h2 class="text-base font-bold text-brand-blue">Formula impact</h2>
                            <p class="mt-0.5 text-xs text-slate-400">Simulation — no records will be changed</p>
                        </div>
                        <button
                            @click="closeModal"
                            :disabled="activating"
                            class="flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 disabled:opacity-40"
                        >✕</button>
                    </div>

                    <!-- Loading state -->
                    <div v-if="simulating" class="flex flex-col items-center justify-center gap-3 px-6 py-12 text-slate-400">
                        <svg class="h-6 w-6 animate-spin text-brand-orange" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <p class="text-sm">Running simulation across all contracts…</p>
                    </div>

                    <!-- Results -->
                    <div v-else-if="simResult" class="px-6 py-5 space-y-4">

                        <!-- Contracts count -->
                        <div class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3">
                            <span class="text-sm text-slate-500">Contracts affected</span>
                            <span class="text-sm font-bold text-slate-800">
                                {{ simResult.affected_contracts.toLocaleString() }}
                                <span class="text-xs font-normal text-slate-400">/ {{ simResult.total_contracts.toLocaleString() }}</span>
                            </span>
                        </div>

                        <!-- Commission comparison -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-xl border border-slate-100 px-4 py-3">
                                <p class="text-xs text-slate-400 mb-1">Current total</p>
                                <p class="font-mono text-sm font-bold text-slate-700">£{{ formatMoney(simResult.current_total) }}</p>
                            </div>
                            <div class="rounded-xl border border-brand-orange/20 bg-brand-orange/5 px-4 py-3">
                                <p class="text-xs text-slate-400 mb-1">New total</p>
                                <p class="font-mono text-sm font-bold text-brand-orange">£{{ formatMoney(simResult.simulated_total) }}</p>
                            </div>
                        </div>

                        <!-- Difference -->
                        <div
                            class="flex items-center justify-between rounded-xl border px-4 py-3"
                            :class="simResult.difference >= 0
                                ? 'border-emerald-200 bg-emerald-50'
                                : 'border-red-200 bg-red-50'"
                        >
                            <span class="text-sm font-medium" :class="simResult.difference >= 0 ? 'text-emerald-700' : 'text-red-700'">
                                Difference
                            </span>
                            <div class="text-right">
                                <p class="font-mono text-sm font-bold" :class="simResult.difference >= 0 ? 'text-emerald-700' : 'text-red-700'">
                                    {{ simResult.difference >= 0 ? '+' : '' }}£{{ formatMoney(simResult.difference) }}
                                </p>
                                <p class="text-xs" :class="simResult.difference >= 0 ? 'text-emerald-500' : 'text-red-500'">
                                    {{ simResult.difference_percent >= 0 ? '+' : '' }}{{ simResult.difference_percent }}%
                                </p>
                            </div>
                        </div>

                        <p class="text-xs text-slate-400 text-center">
                            This simulation does not save any records. Click Activate to apply the new formula.
                        </p>
                    </div>

                    <!-- Footer actions -->
                    <div v-if="!simulating" class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4">
                        <button
                            @click="closeModal"
                            :disabled="activating"
                            class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50 disabled:opacity-40"
                        >
                            Cancel
                        </button>
                        <button
                            @click="confirmActivate"
                            :disabled="activating || !simResult"
                            class="flex items-center gap-2 rounded-lg bg-brand-orange px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <svg v-if="activating" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            {{ activating ? 'Activating…' : 'Activate formula' }}
                        </button>
                    </div>

                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from 'vue-toast-notification';
import useFormulas from '../composables/useFormulas.js';

const toast = useToast();
const { formulas, loading, error, fetchFormulas, simulateFormula, activateFormula } = useFormulas();

// ── Simulation modal state ────────────────────────────────────────────────────
const modal          = ref(false);
const simulating     = ref(false);
const activating     = ref(false);
const simResult      = ref(null);
const pendingId      = ref(null);

const sortedFormulas = computed(() =>
    [...formulas.value].sort((a, b) => {
        if (a.is_active !== b.is_active) return a.is_active ? -1 : 1;
        return b.id - a.id;
    })
);

async function openSimulation(id) {
    pendingId.value  = id;
    simResult.value  = null;
    modal.value      = true;
    simulating.value = true;

    const result = await simulateFormula(id);
    simulating.value = false;

    if (result) {
        simResult.value = result;
    } else {
        modal.value = false;
        toast.error(error.value || 'Simulation failed.', { position: 'top-right', duration: 3000 });
    }
}

function closeModal() {
    if (activating.value) return;
    modal.value     = false;
    simResult.value = null;
    pendingId.value = null;
}

async function confirmActivate() {
    activating.value = true;
    const result = await activateFormula(pendingId.value);
    activating.value = false;

    if (result) {
        modal.value = false;
        simResult.value = null;
        pendingId.value = null;
        toast.success('Formula activated successfully.', { position: 'top-right', duration: 3000 });
    } else {
        toast.error(error.value || 'Failed to activate formula.', { position: 'top-right', duration: 3000 });
    }
}

function formatMoney(val) {
    return Number(val).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}

onMounted(fetchFormulas);
</script>
