<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Formula;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
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
