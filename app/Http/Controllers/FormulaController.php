<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFormulaRequest;
use App\Models\Formula;
use App\Services\FormulaService;
use App\Services\FormulaSimulationService;
use Illuminate\Http\JsonResponse;

class FormulaController extends Controller
{
    public function __construct(
        private readonly FormulaService $formulaService,
        private readonly FormulaSimulationService $simulationService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->formulaService->index());
    }

    public function store(StoreFormulaRequest $request): JsonResponse
    {
        $formula = $this->formulaService->store($request->validated());

        return response()->json($formula, 201);
    }

    public function activate(Formula $formula): JsonResponse
    {
        return response()->json($this->formulaService->activate($formula));
    }

    public function simulate(Formula $formula): JsonResponse
    {
        $formula->load(['dependentVariables' => fn ($q) => $q->orderBy('execution_order')]);

        return response()->json($this->simulationService->simulate($formula));
    }
}
