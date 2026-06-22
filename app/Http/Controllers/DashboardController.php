<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Formula;
use Illuminate\Http\JsonResponse;

/**
 * @group Dashboard
 *
 * High-level stats for the application dashboard.
 */
class DashboardController extends Controller
{
    /**
     * Get dashboard stats.
     *
     * Returns the total contract count and the currently active formula version.
     */
    public function stats(): JsonResponse
    {
        $activeFormula = Formula::where('is_active', true)->first();

        return response()->json([
            'contract_count'  => Contract::count(),
            'active_formula'  => $activeFormula
                ? 'v' . $activeFormula->version
                : 'None',
        ]);
    }
}
