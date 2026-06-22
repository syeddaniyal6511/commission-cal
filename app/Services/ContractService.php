<?php

namespace App\Services;

use App\Models\Contract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ContractService
{
    public function paginate(string $search = '', int $perPage = 15): LengthAwarePaginator
    {
        return Contract::query()
            ->when($search !== '', fn ($q) => $q->where('contract_no', 'like', "%{$search}%"))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function store(array $data): Contract
    {
        return Contract::create($data);
    }

    public function update(Contract $contract, array $data): Contract
    {
        $contract->update($data);

        return $contract->refresh();
    }

    public function destroy(Contract $contract): void
    {
        $contract->delete();
    }
}
