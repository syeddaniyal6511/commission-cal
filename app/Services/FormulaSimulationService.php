<?php

namespace App\Services;

use App\Models\CommissionCalculation;
use App\Models\Contract;
use App\Models\Formula;
use Illuminate\Support\Facades\DB;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class FormulaSimulationService
{
    public function simulate(Formula $formula): array
    {
        $el = new ExpressionLanguage();

        $formula->loadMissing(['dependentVariables' => fn ($q) => $q->orderBy('execution_order')]);

        // Latest commission per contract in 2 queries (avoids N+1)
        $latestIds = CommissionCalculation::select(DB::raw('MAX(id) as id'))
            ->groupBy('contract_id')
            ->pluck('id');

        $currentCommissions = CommissionCalculation::whereIn('id', $latestIds)
            ->pluck('commission', 'contract_id');

        $totalContracts    = 0;
        $affectedContracts = 0;
        $currentTotal      = 0.0;
        $simulatedTotal    = 0.0;

        Contract::chunk(200, function ($contracts) use (
            $el, $formula, $currentCommissions,
            &$totalContracts, &$affectedContracts, &$currentTotal, &$simulatedTotal
        ) {
            foreach ($contracts as $contract) {
                $totalContracts++;

                $current = (float) ($currentCommissions[$contract->id] ?? 0);
                $currentTotal += $current;

                $variables = [
                    'AnnualUsage'    => (float) $contract->annual_usage,
                    'ContractValue'  => (float) $contract->contract_value,
                    'ContractLength' => (int)   $contract->contract_length,
                    'RiskScore'      => (float) $contract->risk_score,
                ];

                foreach ($formula->dependentVariables as $var) {
                    $variables[$var->name] = $el->evaluate($var->expression, $variables);
                }

                $simulated = (float) $el->evaluate($formula->expression, $variables);
                $simulatedTotal += $simulated;

                if (abs($simulated - $current) > 0.0001) {
                    $affectedContracts++;
                }
            }
        });

        $difference        = $simulatedTotal - $currentTotal;
        $differencePercent = $currentTotal > 0
            ? round(($difference / $currentTotal) * 100, 2)
            : 0;

        return [
            'total_contracts'    => $totalContracts,
            'affected_contracts' => $affectedContracts,
            'current_total'      => round($currentTotal, 4),
            'simulated_total'    => round($simulatedTotal, 4),
            'difference'         => round($difference, 4),
            'difference_percent' => $differencePercent,
        ];
    }
}
