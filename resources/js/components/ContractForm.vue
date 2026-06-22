<script setup>
import { reactive, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: Object,
        required: true,
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
    saving: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'submit']);

const form = reactive({ ...props.modelValue });

watch(
    () => props.modelValue,
    (val) => Object.assign(form, val),
    { deep: true },
);

watch(form, (val) => emit('update:modelValue', { ...val }), { deep: true });

function handleSubmit() {
    emit('submit', { ...form });
}
</script>

<template>
    <form @submit.prevent="handleSubmit" class="space-y-5">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Contract No <span class="text-red-500">*</span>
            </label>
            <input
                v-model="form.contract_no"
                type="text"
                maxlength="50"
                placeholder="e.g. CON-000123"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-orange/50 focus:border-brand-orange transition"
                :class="{ 'border-red-400': errors?.contract_no }"
            />
            <p v-if="errors?.contract_no" class="mt-1 text-xs text-red-500">{{ errors.contract_no[0] }}</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Annual Usage (kWh) <span class="text-red-500">*</span>
                </label>
                <input
                    v-model="form.annual_usage"
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="0.00"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-orange/50 focus:border-brand-orange transition"
                    :class="{ 'border-red-400': errors?.annual_usage }"
                />
                <p v-if="errors?.annual_usage" class="mt-1 text-xs text-red-500">{{ errors.annual_usage[0] }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Contract Value (£) <span class="text-red-500">*</span>
                </label>
                <input
                    v-model="form.contract_value"
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="0.00"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-orange/50 focus:border-brand-orange transition"
                    :class="{ 'border-red-400': errors?.contract_value }"
                />
                <p v-if="errors?.contract_value" class="mt-1 text-xs text-red-500">{{ errors.contract_value[0] }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Contract Length (months) <span class="text-red-500">*</span>
                </label>
                <select
                    v-model="form.contract_length"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-orange/50 focus:border-brand-orange transition"
                    :class="{ 'border-red-400': errors?.contract_length }"
                >
                    <option value="" disabled>Select length</option>
                    <option :value="12">12 months</option>
                    <option :value="24">24 months</option>
                    <option :value="36">36 months</option>
                    <option :value="48">48 months</option>
                    <option :value="60">60 months</option>
                </select>
                <p v-if="errors?.contract_length" class="mt-1 text-xs text-red-500">{{ errors.contract_length[0] }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Risk Score (0–100) <span class="text-red-500">*</span>
                </label>
                <input
                    v-model="form.risk_score"
                    type="number"
                    min="0"
                    max="100"
                    step="0.01"
                    placeholder="0.00"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-orange/50 focus:border-brand-orange transition"
                    :class="{ 'border-red-400': errors?.risk_score }"
                />
                <p v-if="errors?.risk_score" class="mt-1 text-xs text-red-500">{{ errors.risk_score[0] }}</p>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <slot name="actions">
                <button
                    type="submit"
                    :disabled="saving"
                    class="px-5 py-2 rounded-lg bg-brand-orange text-white text-sm font-medium hover:bg-brand-orange/90 disabled:opacity-50 disabled:cursor-not-allowed transition"
                >
                    {{ saving ? 'Saving…' : 'Save contract' }}
                </button>
            </slot>
        </div>
    </form>
</template>
