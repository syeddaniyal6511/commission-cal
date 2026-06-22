<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFormulaRequest;
use App\Models\Formula;
use App\Services\FormulaService;
use App\Services\FormulaSimulationService;
use Illuminate\Http\JsonResponse;

/**
 * @group Formulas
 *
 * Endpoints for managing and simulating commission formulas.
 */
class FormulaController extends Controller
{
    public function __construct(
        private readonly FormulaService $formulaService,
        private readonly FormulaSimulationService $simulationService,
    ) {}

    /**
     * List all formulas.
     */
    public function index(): JsonResponse
    {
        return response()->json($this->formulaService->index());
    }

    /**
     * Create a new formula.
     */
    public function store(StoreFormulaRequest $request): JsonResponse
    {
        $formula = $this->formulaService->store($request->validated());

        return response()->json($formula, 201);
    }

    /**
     * Activate a formula.
     *
     * Sets the given formula as the active one used for commission calculations.
     */
    public function activate(Formula $formula): JsonResponse
    {
        return response()->json($this->formulaService->activate($formula));
    }

    /**
     * Simulate a formula.
     *
     * Returns computed output values for a formula without persisting anything.
     */
    public function simulate(Formula $formula): JsonResponse
    {
        $formula->load(['dependentVariables' => fn ($q) => $q->orderBy('execution_order')]);

        return response()->json($this->simulationService->simulate($formula));
    }
}
