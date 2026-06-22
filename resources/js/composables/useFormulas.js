import { ref } from 'vue';
import axios from 'axios';

export default function useFormulas() {
    const formulas        = ref([]);
    const loading         = ref(false);
    const saving          = ref(false);
    const error           = ref(null);

    function extractError(err) {
        return (
            err?.response?.data?.message ||
            err?.response?.data?.error   ||
            err?.message                 ||
            'An unexpected error occurred.'
        );
    }

    async function fetchFormulas() {
        loading.value = true;
        error.value   = null;
        try {
            const { data } = await axios.get('/api/formulas');
            formulas.value = data;
        } catch (err) {
            error.value = extractError(err);
        } finally {
            loading.value = false;
        }
    }

    async function saveFormula(payload) {
        saving.value = true;
        error.value  = null;
        try {
            const { data } = await axios.post('/api/formulas', payload);
            formulas.value.unshift(data);
            return data;
        } catch (err) {
            error.value = extractError(err);
            return null;
        } finally {
            saving.value = false;
        }
    }

    async function simulateFormula(id) {
        error.value = null;
        try {
            const { data } = await axios.get(`/api/formulas/${id}/simulate`);
            return data;
        } catch (err) {
            error.value = extractError(err);
            return null;
        }
    }

    async function activateFormula(id) {
        error.value = null;
        try {
            const { data } = await axios.post(`/api/formulas/${id}/activate`);
            formulas.value = formulas.value.map(f => ({ ...f, is_active: f.id === id }));
            return data;
        } catch (err) {
            error.value = extractError(err);
            return null;
        }
    }

    return {
        formulas,
        loading,
        saving,
        error,
        fetchFormulas,
        saveFormula,
        simulateFormula,
        activateFormula,
    };
}
