<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'vue-toast-notification';
import ContractForm from '../../components/ContractForm.vue';
import useContracts from '../../composables/useContracts.js';

const router = useRouter();
const toast  = useToast();

const { saving, createContract } = useContracts();

const form   = ref({ contract_no: '', annual_usage: '', contract_value: '', contract_length: '', risk_score: '' });
const errors = ref({});

async function handleSubmit(data) {
    errors.value = {};
    const result = await createContract(data);
    if (result.success) {
        toast.success('Contract created.');
        router.push({ name: 'contracts' });
    } else {
        errors.value = result.errors ?? {};
        if (!result.errors) toast.error(result.message);
    }
}
</script>

<template>
    <div class="max-w-2xl mx-auto space-y-6">
        <div class="flex items-center gap-3">
            <RouterLink
                :to="{ name: 'contracts' }"
                class="text-gray-400 hover:text-gray-600 transition"
            >
                <font-awesome-icon icon="arrow-left" />
            </RouterLink>
            <div>
                <h1 class="text-xl font-semibold text-gray-900">New contract</h1>
                <p class="text-sm text-gray-500">Add a contract to the system.</p>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <ContractForm
                v-model="form"
                :errors="errors"
                :saving="saving"
                @submit="handleSubmit"
            >
                <template #actions>
                    <RouterLink
                        :to="{ name: 'contracts' }"
                        class="px-4 py-2 rounded-lg border border-gray-300 text-gray-600 text-sm hover:bg-gray-50 transition"
                    >
                        Cancel
                    </RouterLink>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="px-5 py-2 rounded-lg bg-brand-orange text-white text-sm font-medium hover:bg-brand-orange/90 disabled:opacity-50 disabled:cursor-not-allowed transition"
                    >
                        {{ saving ? 'Saving…' : 'Create contract' }}
                    </button>
                </template>
            </ContractForm>
        </div>
    </div>
</template>
