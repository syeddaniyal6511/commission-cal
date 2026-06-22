<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import useCalculations from '../../composables/useCalculations.js';

const route = useRoute();

const { calculations, pagination, loading, error, fetchCalculations } = useCalculations();

const page       = ref(1);
const expanded   = ref([]);

const contractNo = route.query.contract_no ?? `#${route.params.id}`;

onMounted(() => load());

async function load() {
    await fetchCalculations(route.params.id, page.value);
    if (calculations.value.length > 0 && !expanded.value.includes(calculations.value[0].id)) {
        expanded.value = [calculations.value[0].id];
    }
}

async function changePage(p) {
    if (p < 1 || p > pagination.value?.last_page) return;
    page.value = p;
    expanded.value = [];
    await load();
}

function toggle(id) {
    if (expanded.value.includes(id)) {
        expanded.value = expanded.value.filter(x => x !== id);
    } else {
        expanded.value = [...expanded.value, id];
    }
}

function isExpanded(id) {
    return expanded.value.includes(id);
}

function formatNum(val, decimals = 4) {
    return Number(val).toLocaleString('en-GB', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
}

function formatDate(str) {
    return new Date(str).toLocaleString('en-GB', {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit', second: '2-digit',
    });
}

const INPUT_KEYS = ['AnnualUsage', 'ContractValue', 'ContractLength', 'RiskScore'];

function inputSteps(steps) {
    return steps?.filter(s => s.type === 'input') ?? [];
}
function computedSteps(steps) {
    return steps?.filter(s => s.type === 'computed') ?? [];
}
function resultStep(steps) {
    return steps?.find(s => s.type === 'result') ?? null;
}
</script>

<template>
    <div class="mx-auto max-w-3xl px-1 py-2">

        <!-- Header -->
        <div class="mb-8 flex items-start gap-4">
            <RouterLink
                :to="{ name: 'contracts' }"
                class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-200 text-slate-400 transition hover:border-slate-300 hover:text-slate-600"
            >
                <font-awesome-icon icon="arrow-left" class="text-xs" />
            </RouterLink>
            <div>
                <h1 class="text-xl font-bold text-brand-blue">Calculation history</h1>
                <p class="mt-0.5 font-mono text-sm text-slate-400">{{ contractNo }}</p>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex flex-col items-center justify-center gap-3 py-24 text-slate-400">
            <font-awesome-icon icon="circle-notch" spin class="text-2xl" />
            <p class="text-sm">Loading history…</p>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="rounded-xl border border-red-100 bg-red-50 px-5 py-4 text-sm text-red-600">
            {{ error }}
        </div>

        <!-- Empty -->
        <div v-else-if="!calculations.length" class="flex flex-col items-center justify-center gap-3 py-24 text-slate-400">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100">
                <font-awesome-icon icon="clock" class="text-xl text-slate-400" />
            </div>
            <p class="text-sm">No calculations yet for this contract.</p>
        </div>

        <!-- Calculations list -->
        <div v-else class="space-y-3">
            <div
                v-for="calc in calculations"
                :key="calc.id"
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-shadow hover:shadow-md"
            >
                <!-- Card header -->
                <button
                    class="group w-full px-5 py-4 text-left transition hover:bg-slate-50/70"
                    @click="toggle(calc.id)"
                >
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-4">

                            <!-- Index bubble -->
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-orange/10 text-xs font-bold text-brand-orange">
                                #{{ calc.id }}
                            </div>

                            <!-- Commission + meta -->
                            <div>
                                <div class="flex items-baseline gap-2">
                                    <span class="text-lg font-bold text-slate-800 tabular-nums">
                                        £{{ formatNum(calc.commission) }}
                                    </span>
                                    <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-500">
                                        v{{ calc.formula_version ?? '?' }}
                                    </span>
                                </div>
                                <p class="mt-0.5 text-xs text-slate-400">{{ formatDate(calc.created_at) }}</p>
                            </div>
                        </div>

                        <!-- Chevron -->
                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-slate-300 transition group-hover:bg-slate-100 group-hover:text-slate-500">
                            <font-awesome-icon :icon="isExpanded(calc.id) ? 'angle-up' : 'angle-down'" class="text-sm" />
                        </div>
                    </div>
                </button>

                <!-- Expandable body -->
                <Transition
                    enter-active-class="transition-all ease-out duration-200 overflow-hidden"
                    enter-from-class="max-h-0 opacity-0"
                    enter-to-class="max-h-[2000px] opacity-100"
                    leave-active-class="transition-all ease-in duration-150 overflow-hidden"
                    leave-from-class="max-h-[2000px] opacity-100"
                    leave-to-class="max-h-0 opacity-0"
                >
                    <div v-if="isExpanded(calc.id)">

                        <!-- Meta strip -->
                        <div class="border-y border-slate-100 bg-slate-50 px-5 py-3">
                            <div class="flex flex-wrap gap-x-8 gap-y-1.5">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-slate-400">Formula version</span>
                                    <span class="font-mono text-xs font-semibold text-slate-700">{{ calc.formula_version ?? '—' }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-slate-400">Date</span>
                                    <span class="text-xs font-medium text-slate-700">{{ formatDate(calc.created_at) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-6 px-5 py-5">

                            <!-- Input values -->
                            <div>
                                <div class="mb-3 flex items-center gap-2">
                                    <span class="h-1.5 w-1.5 rounded-full bg-blue-400"></span>
                                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Input values</p>
                                </div>
                                <div class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-slate-100 bg-slate-100">
                                    <template v-if="calc.steps_json">
                                        <div
                                            v-for="step in inputSteps(calc.steps_json)"
                                            :key="step.name"
                                            class="flex items-center justify-between bg-white px-4 py-3"
                                        >
                                            <span class="text-sm font-medium text-slate-600">{{ step.name }}</span>
                                            <span class="font-mono text-sm font-semibold text-brand-blue tabular-nums">{{ formatNum(step.value, 2) }}</span>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div
                                            v-for="key in INPUT_KEYS"
                                            :key="key"
                                            class="flex items-center justify-between bg-white px-4 py-3"
                                        >
                                            <span class="text-sm font-medium text-slate-600">{{ key }}</span>
                                            <span class="font-mono text-sm font-semibold text-brand-blue tabular-nums">
                                                {{ formatNum(calc.variables_json?.[key] ?? 0, 2) }}
                                            </span>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Calculation steps — timeline -->
                            <template v-if="calc.steps_json && computedSteps(calc.steps_json).length">
                                <div>
                                    <div class="mb-3 flex items-center gap-2">
                                        <span class="h-1.5 w-1.5 rounded-full bg-brand-orange"></span>
                                        <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Calculation steps</p>
                                    </div>

                                    <div class="relative pl-6">
                                        <!-- vertical line -->
                                        <div class="absolute left-[9px] top-3 bottom-3 w-px bg-slate-200"></div>

                                        <div class="space-y-3">
                                            <div
                                                v-for="(step, idx) in computedSteps(calc.steps_json)"
                                                :key="step.name"
                                                class="relative"
                                            >
                                                <!-- dot -->
                                                <div class="absolute -left-6 top-3 flex h-[18px] w-[18px] items-center justify-center rounded-full border-2 border-white bg-brand-orange text-[9px] font-bold text-white shadow-sm">
                                                    {{ idx + 1 }}
                                                </div>

                                                <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                                                    <div class="flex items-start justify-between gap-4">
                                                        <div class="min-w-0">
                                                            <p class="text-sm font-semibold text-slate-800">{{ step.name }}</p>
                                                            <p class="mt-0.5 truncate font-mono text-xs text-slate-400">= {{ step.expression }}</p>
                                                        </div>
                                                        <span class="shrink-0 font-mono text-sm font-bold text-brand-orange tabular-nums">
                                                            {{ formatNum(step.value) }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Final result -->
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-px">
                                <div class="rounded-[15px] bg-white px-5 py-4">
                                    <div class="mb-3 flex items-center gap-2">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Final result</p>
                                    </div>
                                    <p
                                        v-if="calc.steps_json && resultStep(calc.steps_json)"
                                        class="mb-3 font-mono text-xs text-slate-400"
                                    >
                                        {{ resultStep(calc.steps_json).expression }}
                                    </p>
                                    <div class="flex items-end justify-between">
                                        <span class="text-sm font-medium text-slate-500">Commission</span>
                                        <span class="text-3xl font-bold tracking-tight text-brand-blue tabular-nums">
                                            £{{ formatNum(calc.commission) }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </Transition>
            </div>

            <!-- Pagination -->
            <div
                v-if="pagination && pagination.last_page > 1"
                class="flex items-center justify-between pt-1 text-sm text-slate-500"
            >
                <span class="text-xs text-slate-400">
                    Showing {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }}
                </span>
                <div class="flex items-center gap-1">
                    <button
                        @click="changePage(pagination.current_page - 1)"
                        :disabled="pagination.current_page === 1"
                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-400 transition hover:border-slate-300 hover:text-slate-600 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        <font-awesome-icon icon="chevron-left" class="text-xs" />
                    </button>
                    <span class="min-w-[80px] text-center text-xs text-slate-500">
                        {{ pagination.current_page }} / {{ pagination.last_page }}
                    </span>
                    <button
                        @click="changePage(pagination.current_page + 1)"
                        :disabled="pagination.current_page === pagination.last_page"
                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-400 transition hover:border-slate-300 hover:text-slate-600 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        <font-awesome-icon icon="chevron-right" class="text-xs" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
