<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Services\CommissionEngineService;
use Illuminate\Http\JsonResponse;

/**
 * @group Commissions
 *
 * Endpoints for calculating and viewing commission history on contracts.
 */
class CommissionController extends Controller
{
    public function __construct(private readonly CommissionEngineService $engine) {}

    /**
     * Calculate commission for a contract.
     *
     * Runs the active formula against the given contract and persists the result.
     */
    public function calculate(Contract $contract): JsonResponse
    {
        return response()->json($this->engine->calculate($contract));
    }

    /**
     * Get commission calculation history for a contract.
     */
    public function history(Contract $contract): JsonResponse
    {
        return response()->json($this->engine->history($contract));
    }
}
