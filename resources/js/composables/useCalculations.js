import { ref } from 'vue';
import axios from 'axios';

export default function useCalculations() {
    const calculations = ref([]);
    const pagination   = ref(null);
    const loading      = ref(false);
    const error        = ref(null);

    function extractError(err) {
        return err?.response?.data?.message ?? err?.message ?? 'An unexpected error occurred.';
    }

    async function fetchCalculations(contractId, page = 1) {
        loading.value = true;
        error.value   = null;
        try {
            const { data } = await axios.get(`/api/contracts/${contractId}/calculations`, {
                params: { page },
            });
            calculations.value = data.data;
            pagination.value   = {
                current_page: data.current_page,
                last_page:    data.last_page,
                total:        data.total,
                from:         data.from,
                to:           data.to,
            };
        } catch (err) {
            error.value = extractError(err);
        } finally {
            loading.value = false;
        }
    }

    return { calculations, pagination, loading, error, fetchCalculations };
}
