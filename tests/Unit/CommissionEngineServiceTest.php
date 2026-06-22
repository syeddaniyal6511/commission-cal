<?php

namespace Tests\Unit;

use App\Models\Contract;
use App\Models\Formula;
use App\Services\CommissionEngineService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionEngineServiceTest extends TestCase
{
    use RefreshDatabase;

    private CommissionEngineService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CommissionEngineService();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeActiveFormula(string $expression = 'AnnualUsage * 0.05'): Formula
    {
        return Formula::create([
            'version'    => '1.0',
            'expression' => $expression,
            'is_active'  => true,
        ]);
    }

    private function makeContract(array $overrides = []): Contract
    {
        return Contract::factory()->create($overrides);
    }

    // ── calculate ─────────────────────────────────────────────────────────────

    public function test_calculate_returns_correct_commission(): void
    {
        $this->makeActiveFormula('AnnualUsage * 0.05');
        $contract = $this->makeContract(['annual_usage' => 10000]);

        $result = $this->service->calculate($contract);

        $this->assertEquals(500.0, $result['commission']);
    }

    public function test_calculate_returns_formula_id_and_version(): void
    {
        $formula  = $this->makeActiveFormula();
        $contract = $this->makeContract();

        $result = $this->service->calculate($contract);

        $this->assertEquals($formula->id,      $result['formula_id']);
        $this->assertEquals($formula->version, $result['formula_version']);
    }

    public function test_calculate_includes_all_four_base_variables_as_input_steps(): void
    {
        $this->makeActiveFormula('AnnualUsage * 0.05');
        $contract = $this->makeContract([
            'annual_usage'    => 1000,
            'contract_value'  => 2000,
            'contract_length' => 12,
            'risk_score'      => 5,
        ]);

        $result = $this->service->calculate($contract);

        $inputNames = collect($result['steps'])
            ->where('type', 'input')
            ->pluck('name')
            ->all();

        $this->assertContains('AnnualUsage',    $inputNames);
        $this->assertContains('ContractValue',  $inputNames);
        $this->assertContains('ContractLength', $inputNames);
        $this->assertContains('RiskScore',      $inputNames);
    }

    public function test_calculate_includes_result_step(): void
    {
        $this->makeActiveFormula('AnnualUsage * 0.05');
        $contract = $this->makeContract(['annual_usage' => 10000]);

        $result = $this->service->calculate($contract);

        $resultStep = collect($result['steps'])->firstWhere('type', 'result');

        $this->assertNotNull($resultStep);
        $this->assertEquals(500.0, $resultStep['value']);
    }

    public function test_calculate_evaluates_sub_variables_in_order(): void
    {
        $formula = Formula::create([
            'version'    => '1.0',
            'expression' => 'Base + Bonus',
            'is_active'  => true,
        ]);

        $formula->dependentVariables()->createMany([
            ['name' => 'Base',  'expression' => 'AnnualUsage * 0.08',  'execution_order' => 0],
            ['name' => 'Bonus', 'expression' => 'ContractLength * 100', 'execution_order' => 1],
        ]);

        $contract = $this->makeContract([
            'annual_usage'    => 10000,
            'contract_length' => 24,
        ]);

        // Base = 10000 * 0.08 = 800, Bonus = 24 * 100 = 2400 → total = 3200
        $result = $this->service->calculate($contract);

        $this->assertEquals(3200.0, $result['commission']);
    }

    public function test_calculate_includes_computed_steps_for_sub_variables(): void
    {
        $formula = Formula::create([
            'version'    => '1.0',
            'expression' => 'Base',
            'is_active'  => true,
        ]);

        $formula->dependentVariables()->create([
            'name'            => 'Base',
            'expression'      => 'AnnualUsage * 0.05',
            'execution_order' => 0,
        ]);

        $contract = $this->makeContract(['annual_usage' => 2000]);

        $result = $this->service->calculate($contract);

        $computedStep = collect($result['steps'])->firstWhere('type', 'computed');

        $this->assertNotNull($computedStep);
        $this->assertEquals('Base', $computedStep['name']);
        $this->assertEquals(100.0, $computedStep['value']);
    }

    public function test_calculate_uses_all_four_base_variables_in_expression(): void
    {
        $this->makeActiveFormula('AnnualUsage + ContractValue + ContractLength + RiskScore');
        $contract = $this->makeContract([
            'annual_usage'    => 1000,
            'contract_value'  => 2000,
            'contract_length' => 12,
            'risk_score'      => 5,
        ]);

        $result = $this->service->calculate($contract);

        // 1000 + 2000 + 12 + 5 = 3017
        $this->assertEquals(3017.0, $result['commission']);
    }

    public function test_calculate_exposes_variables_map(): void
    {
        $this->makeActiveFormula('AnnualUsage * 0.05');
        $contract = $this->makeContract([
            'annual_usage'    => 5000,
            'contract_value'  => 10000,
            'contract_length' => 24,
            'risk_score'      => 10,
        ]);

        $result = $this->service->calculate($contract);

        $this->assertEquals(5000.0,  $result['variables']['AnnualUsage']);
        $this->assertEquals(10000.0, $result['variables']['ContractValue']);
        $this->assertEquals(24,      $result['variables']['ContractLength']);
        $this->assertEquals(10.0,    $result['variables']['RiskScore']);
    }

    public function test_calculate_throws_when_no_active_formula(): void
    {
        Formula::create([
            'version'    => '1.0',
            'expression' => 'AnnualUsage * 0.05',
            'is_active'  => false,
        ]);

        $contract = $this->makeContract();

        $this->expectException(ModelNotFoundException::class);

        $this->service->calculate($contract);
    }
}
