<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Services\CommissionEngineService;
use Illuminate\Http\JsonResponse;

class CommissionController extends Controller
{
    public function __construct(private readonly CommissionEngineService $engine) {}

    public function calculate(Contract $contract): JsonResponse
    {
        return response()->json($this->engine->calculate($contract));
    }

    public function history(Contract $contract): JsonResponse
    {
        return response()->json($this->engine->history($contract));
    }
}
