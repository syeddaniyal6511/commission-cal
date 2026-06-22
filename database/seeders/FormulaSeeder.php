<?php

namespace Database\Seeders;

use App\Models\Formula;
use Illuminate\Database\Seeder;

class FormulaSeeder extends Seeder
{
    public function run(): void
    {
        $formula = Formula::create([
            'version'    => '1',
            'expression' => 'BaseCommission + ContractBonus',
            'is_active'  => true,
        ]);

        $formula->dependentVariables()->createMany([
            [
                'name'            => 'BaseCommission',
                'expression'      => 'AnnualUsage * 0.05',
                'execution_order' => 0,
            ],
            [
                'name'            => 'ContractBonus',
                'expression'      => 'ContractLength * 100',
                'execution_order' => 1,
            ],
        ]);
    }
}
