import { ref } from 'vue';
import axios from 'axios';

export default function useContracts() {
    const contracts   = ref([]);
    const contract    = ref(null);
    const pagination  = ref(null);
    const loading     = ref(false);
    const saving      = ref(false);
    const calculating = ref(false);
    const error       = ref(null);

    function extractError(err) {
        return err?.response?.data?.message ?? err?.message ?? 'An unexpected error occurred.';
    }

    async function fetchContracts(params = {}) {
        loading.value = true;
        error.value   = null;
        try {
            const { data } = await axios.get('/api/contracts', { params });
            contracts.value  = data.data;
            pagination.value = {
                current_page:  data.current_page,
                last_page:     data.last_page,
                per_page:      data.per_page,
                total:         data.total,
                from:          data.from,
                to:            data.to,
            };
        } catch (err) {
            error.value = extractError(err);
        } finally {
            loading.value = false;
        }
    }

    async function fetchContract(id) {
        loading.value  = true;
        error.value    = null;
        contract.value = null;
        try {
            const { data } = await axios.get(`/api/contracts/${id}`);
            contract.value = data;
        } catch (err) {
            error.value = extractError(err);
        } finally {
            loading.value = false;
        }
    }

    async function createContract(payload) {
        saving.value = true;
        error.value  = null;
        try {
            const { data } = await axios.post('/api/contracts', payload);
            return { success: true, data };
        } catch (err) {
            const message = extractError(err);
            const errors  = err?.response?.data?.errors ?? null;
            error.value   = message;
            return { success: false, message, errors };
        } finally {
            saving.value = false;
        }
    }

    async function updateContract(id, payload) {
        saving.value = true;
        error.value  = null;
        try {
            const { data } = await axios.put(`/api/contracts/${id}`, payload);
            return { success: true, data };
        } catch (err) {
            const message = extractError(err);
            const errors  = err?.response?.data?.errors ?? null;
            error.value   = message;
            return { success: false, message, errors };
        } finally {
            saving.value = false;
        }
    }

    async function deleteContract(id) {
        error.value = null;
        try {
            await axios.delete(`/api/contracts/${id}`);
            return { success: true };
        } catch (err) {
            const message = extractError(err);
            error.value   = message;
            return { success: false, message };
        }
    }

    async function calculateCommission(id) {
        calculating.value = true;
        error.value       = null;
        try {
            const { data } = await axios.post(`/api/contracts/${id}/calculate`);
            return { success: true, data };
        } catch (err) {
            const message = extractError(err);
            error.value   = message;
            return { success: false, message };
        } finally {
            calculating.value = false;
        }
    }

    return {
        contracts,
        contract,
        pagination,
        loading,
        saving,
        calculating,
        error,
        fetchContracts,
        fetchContract,
        createContract,
        updateContract,
        deleteContract,
        calculateCommission,
    };
}
