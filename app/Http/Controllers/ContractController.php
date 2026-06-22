<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContractRequest;
use App\Http\Requests\UpdateContractRequest;
use App\Models\Contract;
use App\Services\ContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function __construct(private readonly ContractService $contractService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);
        abort_if($perPage < 1 || $perPage > 100, 422, 'per_page must be between 1 and 100.');

        $contracts = $this->contractService->paginate(
            search:  $request->string('search')->trim()->value(),
            perPage: $perPage,
        );

        return response()->json($contracts);
    }

    public function store(StoreContractRequest $request): JsonResponse
    {
        $contract = $this->contractService->store($request->validated());

        return response()->json($contract, 201);
    }

    public function show(Contract $contract): JsonResponse
    {
        return response()->json($contract);
    }

    public function update(UpdateContractRequest $request, Contract $contract): JsonResponse
    {
        $contract = $this->contractService->update($contract, $request->validated());

        return response()->json($contract);
    }

    public function destroy(Contract $contract): JsonResponse
    {
        $this->contractService->destroy($contract);

        return response()->json(null, 204);
    }
}
