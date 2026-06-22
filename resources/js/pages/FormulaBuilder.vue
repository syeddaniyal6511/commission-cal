<template>
    <!-- Toast notifications -->
    <TransitionGroup
        name="toast"
        tag="div"
        class="fixed bottom-4 right-4 z-50 flex w-80 flex-col gap-2"
    >
        <div
            v-for="toast in toasts"
            :key="toast.id"
            class="flex items-start gap-3 rounded-xl px-4 py-3 text-sm font-medium shadow-lg"
            :class="toast.type === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white'"
        >
            <span class="flex-1">{{ toast.message }}</span>
            <button @click="removeToast(toast.id)" class="shrink-0 opacity-80 transition hover:opacity-100">✕</button>
        </div>
    </TransitionGroup>

    <!-- Page -->
    <div class="min-h-screen bg-slate-50">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">

                <!-- Section header -->
                <div class="mb-7">
                    <h1 class="text-2xl font-bold text-brand-blue">Commission formula</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Create new version of commission formula.
                    </p>
                </div>

                <!-- Version -->
                <div class="mb-5">
                    <label class="block text-sm font-medium text-brand-blue">Version</label>
                    <input
                        v-model="form.version"
                        placeholder="e.g. 1.0"
                        class="mt-1.5 w-48 rounded-lg border px-4 py-2.5 text-sm text-brand-blue placeholder:text-slate-400 focus:outline-none focus:ring-2"
                        :class="fieldError('version') ? 'border-red-400 focus:border-red-400 focus:ring-red-400/20' : 'border-slate-200 focus:border-brand-orange focus:ring-brand-orange/20'"
                    />
                    <p v-if="fieldError('version')" class="mt-1 text-xs text-red-500">{{ fieldError('version') }}</p>
                </div>

                <!-- Expression builder -->
                <div class="mb-5">
                    <label class="block text-sm font-medium text-brand-blue">Main expression</label>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Use base variables or sub-variables — e.g. <span class="font-mono">(AnnualUsage * 0.05) + (ContractLength * 100)</span>
                    </p>
                    <textarea
                        ref="expressionRef"
                        v-model="form.expression"
                        rows="3"
                        placeholder="(AnnualUsage * 0.05) + (ContractLength * 100)"
                        class="mt-1.5 w-full resize-none rounded-lg border px-4 py-2.5 font-mono text-sm text-brand-blue placeholder:text-slate-400 focus:outline-none focus:ring-2"
                        :class="fieldError('expression') ? 'border-red-400 focus:border-red-400 focus:ring-red-400/20' : 'border-slate-200 focus:border-brand-orange focus:ring-brand-orange/20'"
                        @mouseup="trackCursor"
                        @keyup="trackCursor"
                    />
                    <p v-if="fieldError('expression')" class="mt-1 text-xs text-red-500">{{ fieldError('expression') }}</p>

                    <!-- Variable chips -->
                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                        <button
                            v-for="v in BASE_VARIABLES"
                            :key="v"
                            type="button"
                            @click="appendVariable(v)"
                            class="rounded-md border border-brand-blue/20 bg-brand-blue/5 px-2.5 py-1 font-mono text-xs font-medium text-brand-blue transition hover:border-brand-orange/40 hover:bg-brand-orange/5 hover:text-brand-orange"
                        >{{ v }}</button>

                        <template v-if="definedSubVarNames.length">
                            <span class="text-slate-300">|</span>
                            <button
                                v-for="v in definedSubVarNames"
                                :key="v"
                                type="button"
                                @click="appendVariable(v)"
                                class="rounded-md border border-brand-orange/30 bg-brand-orange/5 px-2.5 py-1 font-mono text-xs font-medium text-brand-orange transition hover:border-brand-orange/60 hover:bg-brand-orange/10"
                            >{{ v }}</button>
                        </template>
                    </div>

                    <p class="mt-1 text-right text-xs" :class="form.expression.length > 1900 ? 'text-amber-500' : 'text-slate-400'">
                        {{ form.expression.length }}/2000
                    </p>
                </div>

                <!-- Sub-variables -->
                <div class="mb-6">
                    <div class="mb-1 flex items-center gap-2">
                        <span class="text-sm font-medium text-brand-blue">Sub-variables</span>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">optional</span>
                    </div>
                    <p class="mb-3 text-xs text-slate-500">
                        Define intermediate values to chain calculations — e.g.
                        <span class="font-mono">BaseCommission = AnnualUsage * 0.05</span>
                    </p>

                    <TransitionGroup name="var-row" tag="div" class="space-y-2">
                        <div v-for="(variable, i) in form.variables" :key="variable._key">
                            <div class="flex items-center gap-2">
                                <input
                                    v-model="variable.name"
                                    @input="enforceUpperStart(i)"
                                    placeholder="VarName"
                                    class="w-36 shrink-0 rounded-lg border px-3 py-2 font-mono text-sm text-brand-blue placeholder:text-slate-400 focus:outline-none focus:ring-2"
                                    :class="varFieldError(variable.name) ? 'border-red-400 bg-red-50/40 focus:border-red-400 focus:ring-red-400/20' : 'border-slate-200 focus:border-brand-orange focus:ring-brand-orange/20'"
                                />
                                <span class="shrink-0 text-sm text-slate-400">=</span>
                                <input
                                    :ref="el => { if (el) subExprRefs[i] = el }"
                                    v-model="variable.expression"
                                    placeholder="AnnualUsage * 0.05"
                                    class="min-w-0 flex-1 rounded-lg border px-3 py-2 font-mono text-sm text-brand-blue placeholder:text-slate-400 focus:outline-none focus:ring-2"
                                    :class="varFieldError(variable.name) ? 'border-red-400 bg-red-50/40 focus:border-red-400 focus:ring-red-400/20' : 'border-slate-200 focus:border-brand-orange focus:ring-brand-orange/20'"
                                    @mouseup="trackSubCursor(i)"
                                    @keyup="trackSubCursor(i)"
                                />
                                <button
                                    type="button"
                                    @click="removeVariable(i)"
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-red-50 hover:text-red-500"
                                    title="Remove variable"
                                >✕</button>
                            </div>

                            <!-- Per-row error -->
                            <p v-if="varFieldError(variable.name)" class="mt-1 flex items-start gap-1.5 rounded-md bg-red-50 px-2.5 py-1.5 text-xs text-red-700">
                                <span class="mt-px shrink-0">⚠</span>
                                <span>{{ varFieldError(variable.name) }}</span>
                            </p>

                            <!-- Reference chips -->
                            <div class="mt-1.5 flex flex-wrap items-center gap-1">
                                <span class="mr-0.5 text-xs text-slate-400">Insert:</span>
                                <button
                                    v-for="chip in availableVarsFor(i)"
                                    :key="chip"
                                    type="button"
                                    @click="appendToSubVar(i, chip)"
                                    class="rounded border px-2 py-0.5 font-mono text-xs transition"
                                    :class="BASE_VARIABLES.includes(chip)
                                        ? 'border-brand-blue/20 bg-brand-blue/5 text-brand-blue hover:border-brand-orange/40 hover:bg-brand-orange/5 hover:text-brand-orange'
                                        : 'border-brand-orange/30 bg-brand-orange/5 text-brand-orange hover:border-brand-orange/60 hover:bg-brand-orange/10'"
                                >{{ chip }}</button>
                            </div>
                        </div>
                    </TransitionGroup>

                    <p v-if="form.variables.length > 0" class="mt-1 text-xs text-slate-400">
                        Names are auto-formatted to PascalCase — e.g. "base commission" → <span class="font-mono">BaseCommission</span>
                    </p>

                    <div class="mt-3">
                        <button
                            v-if="form.variables.length < 8"
                            type="button"
                            @click="addVariable"
                            class="rounded-lg border border-dashed border-slate-300 px-4 py-2 text-sm text-slate-500 transition hover:border-brand-orange/50 hover:text-brand-orange"
                        >+ Add sub-variable</button>
                        <p v-else class="text-xs text-slate-400">Maximum 8 sub-variables reached.</p>
                    </div>
                </div>

                <!-- Validation result panel -->
                <Transition name="slide-fade">
                    <div
                        v-if="validationResult"
                        class="mb-5 rounded-xl border p-4"
                        :class="validationResult.valid ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50'"
                    >
                        <template v-if="validationResult.valid">
                            <div class="flex items-center gap-2">
                                <span class="text-base leading-none text-green-600">✓</span>
                                <p class="font-semibold text-green-800">Formula is valid</p>
                            </div>
                            <p class="mt-1 text-xs text-green-700">
                                Ready to save — version <span class="font-mono font-semibold">{{ form.version }}</span>
                            </p>
                        </template>
                        <template v-else>
                            <div class="flex items-center gap-2">
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-red-500 text-xs font-bold text-white">!</span>
                                <p class="font-semibold text-red-800">
                                    {{ validationResult.errors.length }} issue{{ validationResult.errors.length !== 1 ? 's' : '' }} found
                                </p>
                            </div>
                            <ul class="mt-2 space-y-1.5">
                                <li
                                    v-for="(e, i) in validationResult.errors"
                                    :key="i"
                                    class="flex items-start gap-1.5 text-xs text-red-700"
                                >
                                    <span class="mt-px shrink-0 font-bold">·</span>
                                    <span>
                                        <span v-if="e.variable_name" class="mr-1 font-mono font-semibold">{{ e.variable_name }}:</span>
                                        {{ e.message }}
                                    </span>
                                </li>
                            </ul>
                        </template>
                    </div>
                </Transition>

                <!-- Action buttons -->
                <div class="flex flex-wrap gap-3">
                    <button
                        type="button"
                        @click="handleValidate"
                        class="flex items-center gap-2 rounded-lg border border-brand-blue/20 px-5 py-2.5 text-sm font-medium text-brand-blue transition hover:border-brand-blue/40 hover:bg-brand-blue/5"
                    >
                        Validate
                    </button>

                    <button
                        type="button"
                        @click="handleSave"
                        :disabled="saving"
                        class="flex items-center gap-2 rounded-lg border border-brand-orange px-5 py-2.5 text-sm font-semibold text-brand-orange transition hover:bg-brand-orange/5 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        <svg v-if="saving" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        {{ saving ? 'Saving…' : 'Save formula' }}
                    </button>
                </div>

            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, watch, nextTick } from 'vue';
import useFormulas from '../composables/useFormulas.js';

const BASE_VARIABLES = ['AnnualUsage', 'ContractValue', 'ContractLength', 'RiskScore'];

const { saving, error, saveFormula } = useFormulas();

// ── Form state ────────────────────────────────────────────────
const form = reactive({
    version:    '',
    expression: '',
    variables:  [],
});

const validationResult = ref(null);
const expressionRef    = ref(null);
const cursorPos        = ref(0);
const subExprRefs      = ref([]);
const subVarCursors    = ref([]);

// ── Toasts ────────────────────────────────────────────────────
const toasts = ref([]);

function addToast(type, message) {
    const id = Date.now() + Math.random();
    toasts.value.push({ id, type, message });
    setTimeout(() => removeToast(id), 3000);
}

function removeToast(id) {
    toasts.value = toasts.value.filter(t => t.id !== id);
}

// ── Computed ──────────────────────────────────────────────────
const definedSubVarNames = computed(() =>
    form.variables.map(v => v.name).filter(n => n.trim().length > 0)
);

// ── Watchers — reset validation when form changes ─────────────
watch(
    [() => form.version, () => form.expression, () => form.variables],
    () => { validationResult.value = null; },
    { deep: true }
);

// ── Sub-variable management ───────────────────────────────────
function addVariable() {
    if (form.variables.length >= 8) return;
    form.variables.push({ name: '', expression: '', _key: Date.now() });
}

function removeVariable(index) {
    form.variables.splice(index, 1);
}

function enforceUpperStart(index) {
    const v = form.variables[index];
    if (!v.name) return;
    v.name = v.name
        .replace(/[^a-zA-Z0-9\s]/g, '')
        .replace(/\s+([a-zA-Z0-9])/g, (_, c) => c.toUpperCase())
        .replace(/\s+/g, '')
        .replace(/^(.)/, c => c.toUpperCase());
}

// ── Expression chip insert ────────────────────────────────────
function trackCursor() {
    const ta = expressionRef.value;
    if (ta) cursorPos.value = ta.selectionStart;
}

function appendVariable(name) {
    const ta    = expressionRef.value;
    const start = ta ? (ta.selectionStart ?? cursorPos.value) : form.expression.length;
    const end   = ta ? (ta.selectionEnd   ?? start)           : start;

    const before = form.expression.slice(0, start);
    const after  = form.expression.slice(end);
    const prefix = before.length > 0 && !/[\s(]$/.test(before) ? ' ' : '';
    const suffix = after.length  > 0 && !/^[\s)+\-*/]/.test(after) ? ' ' : '';

    form.expression = before + prefix + name + suffix + after;

    nextTick(() => {
        if (ta) {
            ta.focus();
            const pos = start + prefix.length + name.length + suffix.length;
            ta.setSelectionRange(pos, pos);
            cursorPos.value = pos;
        }
    });
}

// ── Sub-variable expression chip insert ──────────────────────
function trackSubCursor(index) {
    const el = subExprRefs.value[index];
    if (el) subVarCursors.value[index] = el.selectionStart;
}

function availableVarsFor(index) {
    const others = form.variables
        .filter((_, i) => i !== index)
        .map(v => v.name)
        .filter(n => n.trim().length > 0);
    return [...BASE_VARIABLES, ...others];
}

function appendToSubVar(index, name) {
    const el       = subExprRefs.value[index];
    const variable = form.variables[index];
    const start    = el ? (el.selectionStart ?? subVarCursors.value[index] ?? variable.expression.length) : variable.expression.length;
    const end      = el ? (el.selectionEnd   ?? start) : start;

    const before = variable.expression.slice(0, start);
    const after  = variable.expression.slice(end);
    const prefix = before.length > 0 && !/[\s(]$/.test(before) ? ' ' : '';
    const suffix = after.length  > 0 && !/^[\s)+\-*/]/.test(after) ? ' ' : '';

    variable.expression = before + prefix + name + suffix + after;

    nextTick(() => {
        if (el) {
            el.focus();
            const pos = start + prefix.length + name.length + suffix.length;
            el.setSelectionRange(pos, pos);
            subVarCursors.value[index] = pos;
        }
    });
}

// ── Frontend validation ───────────────────────────────────────
function hasBalancedParens(expr) {
    let depth = 0;
    for (const ch of expr) {
        if (ch === '(') depth++;
        else if (ch === ')') depth--;
        if (depth < 0) return false;
    }
    return depth === 0;
}

function referencesVar(expr, varName) {
    return new RegExp(`\\b${varName}\\b`).test(expr);
}

// Detects consecutive operators like +/, +*, */, **, +-, -*, etc.
function hasConsecutiveOperators(expr) {
    return /[+\-*/]\s*[+\-*/]/.test(expr);
}

// Extract unique PascalCase identifiers (all user-defined vars start with uppercase)
function extractIdentifiers(expr) {
    const matches = [...expr.matchAll(/\b([A-Z][a-zA-Z0-9]*)\b/g)];
    return [...new Set(matches.map(m => m[1]))];
}

function detectCircular(variables) {
    const deps = {};
    for (const v of variables) {
        const name = v.name?.trim();
        if (!name) continue;
        deps[name] = variables
            .map(o => o.name?.trim())
            .filter(n => n && n !== name && referencesVar(v.expression || '', n));
    }

    const visited = new Set();
    const stack   = new Set();

    function hasCycle(node) {
        visited.add(node);
        stack.add(node);
        for (const dep of (deps[node] || [])) {
            if (!visited.has(dep) ? hasCycle(dep) : stack.has(dep)) return true;
        }
        stack.delete(node);
        return false;
    }

    return Object.keys(deps).some(n => !visited.has(n) && hasCycle(n));
}

function runValidation() {
    const errors = [];

    if (!form.version.trim()) {
        errors.push({ field: 'version', message: 'Version is required.' });
    }

    // Collect fully-defined sub-variables first so we can cross-check references
    const activeVars = form.variables.filter(v => v.name?.trim() || v.expression?.trim());
    const definedNames = new Set(
        activeVars.map(v => v.name?.trim()).filter(Boolean)
    );
    const allKnown = new Set([...BASE_VARIABLES, ...definedNames]);

    // ── Main expression ──────────────────────────────────────
    if (!form.expression.trim()) {
        errors.push({ field: 'expression', message: 'Main expression is required.' });
    } else {
        if (!hasBalancedParens(form.expression)) {
            errors.push({ field: 'expression', message: 'Main expression has unbalanced parentheses.' });
        }
        if (hasConsecutiveOperators(form.expression)) {
            errors.push({ field: 'expression', message: 'Main expression has consecutive operators (e.g. +/, +*, */).' });
        }
        for (const id of extractIdentifiers(form.expression)) {
            if (!allKnown.has(id)) {
                errors.push({ field: 'expression', message: `"${id}" is not a base variable or defined sub-variable.` });
            }
        }
    }

    // ── Sub-variables ────────────────────────────────────────
    const seen = new Set();

    for (const v of activeVars) {
        const name = v.name?.trim();
        const expr = v.expression?.trim();

        if (!name) {
            errors.push({ field: 'variable', message: 'A sub-variable is missing its name.' });
            continue;
        }
        if (!expr) {
            errors.push({ field: 'variable', variable_name: name, message: 'Expression is required.' });
            continue;
        }
        if (seen.has(name)) {
            errors.push({ field: 'variable', variable_name: name, message: 'Duplicate variable name.' });
        }
        seen.add(name);

        if (referencesVar(expr, name)) {
            errors.push({ field: 'variable', variable_name: name, message: 'Variable cannot reference itself.' });
        }
        if (!hasBalancedParens(expr)) {
            errors.push({ field: 'variable', variable_name: name, message: 'Unbalanced parentheses.' });
        }
        if (hasConsecutiveOperators(expr)) {
            errors.push({ field: 'variable', variable_name: name, message: 'Expression has consecutive operators (e.g. +/, +*, */).' });
        }

        // Unknown identifiers inside this sub-variable's expression
        const validHere = new Set([...BASE_VARIABLES, ...[...definedNames].filter(n => n !== name)]);
        for (const id of extractIdentifiers(expr)) {
            if (!validHere.has(id)) {
                errors.push({ field: 'variable', variable_name: name, message: `"${id}" is not a base variable or defined sub-variable.` });
            }
        }
    }

    if (activeVars.length > 1 && detectCircular(activeVars)) {
        errors.push({ field: 'variable', message: 'Circular reference detected between sub-variables.' });
    }

    return { valid: errors.length === 0, errors };
}

// Helper — first error message for a given field key
function fieldError(field) {
    if (!validationResult.value || validationResult.value.valid) return null;
    const e = validationResult.value.errors.find(e => e.field === field && !e.variable_name);
    return e?.message ?? null;
}

// Helper — first error message for a sub-variable name
function varFieldError(name) {
    if (!name?.trim() || !validationResult.value || validationResult.value.valid) return null;
    const e = validationResult.value.errors.find(e => e.variable_name === name.trim());
    return e?.message ?? null;
}

// ── Validate button ───────────────────────────────────────────
function handleValidate() {
    validationResult.value = runValidation();
}

// ── Save button ───────────────────────────────────────────────
async function handleSave() {
    const result = runValidation();
    validationResult.value = result;

    if (!result.valid) return;

    const saved = await saveFormula({
        version:    form.version.trim(),
        expression: form.expression,
        variables:  form.variables
            .filter(v => v.name?.trim() && v.expression?.trim())
            .map((v, i) => ({ name: v.name.trim(), expression: v.expression.trim(), execution_order: i })),
    });

    if (saved) {
        addToast('success', `Formula v${saved.version} saved.`);
        resetForm();
    } else {
        addToast('error', error.value || 'Failed to save formula.');
    }
}

function resetForm() {
    form.version    = '';
    form.expression = '';
    form.variables  = [];
    validationResult.value = null;
}
</script>

<style>
/* Sub-variable rows */
.var-row-enter-active { transition: opacity 0.2s ease-out, transform 0.2s ease-out; }
.var-row-leave-active { transition: opacity 0.15s ease-in, transform 0.15s ease-in; position: absolute; width: 100%; }
.var-row-enter-from,
.var-row-leave-to     { opacity: 0; transform: translateX(-8px); }

/* Validation result panel */
.slide-fade-enter-active { transition: opacity 0.2s ease-out, transform 0.2s ease-out; }
.slide-fade-leave-active { transition: opacity 0.15s ease-in, transform 0.15s ease-in; }
.slide-fade-enter-from,
.slide-fade-leave-to    { opacity: 0; transform: translateY(-6px); }

/* Toast notifications */
.toast-enter-active { transition: opacity 0.25s ease-out, transform 0.25s ease-out; }
.toast-leave-active { transition: opacity 0.2s ease-in, transform 0.2s ease-in; }
.toast-enter-from,
.toast-leave-to     { opacity: 0; transform: translateX(1rem); }
</style>
