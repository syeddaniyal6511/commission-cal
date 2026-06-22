<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\Formula;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class ContractCommissionSeeder extends Seeder
{
    public function run(): void
    {
        $formula = Formula::where('is_active', true)
            ->with(['dependentVariables' => fn ($q) => $q->orderBy('execution_order')])
            ->first();

        if (!$formula) {
            $this->command->warn('No active formula found. Run FormulaSeeder first.');
            return;
        }

        $this->command->info("Calculating commissions using formula [{$formula->version}]...");

        $el    = new ExpressionLanguage();
        $total = Contract::count();
        $bar   = $this->command->getOutput()->createProgressBar($total);
        $bar->start();

        $now = now();

        // Chunk to avoid loading 1000 models at once
        Contract::chunk(200, function ($contracts) use ($formula, $el, $bar, $now) {
            $rows = [];

            foreach ($contracts as $contract) {
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

                // Evaluate sub-variables in execution order
                foreach ($formula->dependentVariables as $var) {
                    $value               = $el->evaluate($var->expression, $variables);
                    $variables[$var->name] = $value;

                    $steps[] = [
                        'type'       => 'computed',
                        'name'       => $var->name,
                        'expression' => $var->expression,
                        'value'      => $value,
                    ];
                }

                $commission = $el->evaluate($formula->expression, $variables);

                $steps[] = [
                    'type'       => 'result',
                    'expression' => $formula->expression,
                    'value'      => $commission,
                ];

                $rows[] = [
                    'contract_id'     => $contract->id,
                    'formula_id'      => $formula->id,
                    'formula_version' => $formula->version,
                    'commission'      => round($commission, 4),
                    'variables_json'  => json_encode($variables),
                    'steps_json'      => json_encode($steps),
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];

                $bar->advance();
            }

            // Bulk insert the whole chunk
            DB::table('commission_calculations')->insert($rows);
        });

        $bar->finish();
        $this->command->newLine();
        $this->command->info("Done — {$total} commission records created.");
    }
}
