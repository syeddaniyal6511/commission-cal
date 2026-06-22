<?php

namespace App\Services;

use App\Models\CommissionCalculation;
use App\Models\Contract;
use App\Models\Formula;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class CommissionEngineService
{
    protected ExpressionLanguage $el;

    public function __construct()
    {
        $this->el = new ExpressionLanguage();
    }

    public function calculate(Contract $contract): array
    {
        $formula = Formula::where('is_active', true)
            ->with(['dependentVariables' => fn ($q) => $q->orderBy('execution_order')])
            ->firstOrFail();

        $variables = [
            'AnnualUsage'    => (float) $contract->annual_usage,
            'ContractValue'  => (float) $contract->contract_value,
            'ContractLength' => (int)   $contract->contract_length,
            'RiskScore'      => (float) $contract->risk_score,
        ];

        $steps = [];

        foreach ($variables as $name => $value) {
            $steps[] = ['type' => 'input', 'name' => $name, 'value' => $value];
        }

        foreach ($formula->dependentVariables as $var) {
            $value                 = $this->el->evaluate($var->expression, $variables);
            $variables[$var->name] = $value;

            $steps[] = [
                'type'       => 'computed',
                'name'       => $var->name,
                'expression' => $var->expression,
                'value'      => $value,
            ];
        }

        $commission = $this->el->evaluate($formula->expression, $variables);

        $steps[] = [
            'type'       => 'result',
            'expression' => $formula->expression,
            'value'      => $commission,
        ];

        $calculation = CommissionCalculation::create([
            'contract_id'     => $contract->id,
            'formula_id'      => $formula->id,
            'formula_version' => $formula->version,
            'commission'      => $commission,
            'variables_json'  => $variables,
            'steps_json'      => $steps,
        ]);

        return [
            'contract_no'     => $contract->contract_no,
            'formula_id'      => $formula->id,
            'formula_version' => $formula->version,
            'commission'      => $commission,
            'variables'       => $variables,
            'steps'           => $steps,
            'calculation_id'  => $calculation->id,
            'calculated_at'   => $calculation->created_at,
        ];
    }

    public function history(Contract $contract, int $perPage = 10): LengthAwarePaginator
    {
        return CommissionCalculation::where('contract_id', $contract->id)
            ->latest()
            ->paginate($perPage);
    }
}
