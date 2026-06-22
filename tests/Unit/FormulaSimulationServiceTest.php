<?php

namespace Tests\Unit;

use App\Models\CommissionCalculation;
use App\Models\Contract;
use App\Models\Formula;
use App\Services\FormulaSimulationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormulaSimulationServiceTest extends TestCase
{
    use RefreshDatabase;

    private FormulaSimulationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FormulaSimulationService();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeFormula(string $expression = 'AnnualUsage * 0.10', bool $active = false): Formula
    {
        static $version = 0;
        $version++;

        return Formula::create([
            'version'    => (string) $version,
            'expression' => $expression,
            'is_active'  => $active,
        ]);
    }

    private function makeContract(array $overrides = []): Contract
    {
        return Contract::factory()->create($overrides);
    }

    private function storeCalculation(Contract $contract, Formula $formula, float $commission): void
    {
        CommissionCalculation::create([
            'contract_id'     => $contract->id,
            'formula_id'      => $formula->id,
            'formula_version' => $formula->version,
            'commission'      => $commission,
            'variables_json'  => [],
            'steps_json'      => [],
        ]);
    }

    // ── Contract counts ───────────────────────────────────────────────────────

    public function test_simulate_counts_total_contracts(): void
    {
        Contract::factory()->count(4)->create();
        $formula = $this->makeFormula();

        $result = $this->service->simulate($formula);

        $this->assertEquals(4, $result['total_contracts']);
    }

    public function test_simulate_returns_zero_totals_when_no_contracts(): void
    {
        $formula = $this->makeFormula();

        $result = $this->service->simulate($formula);

        $this->assertEquals(0, $result['total_contracts']);
        $this->assertEquals(0, $result['affected_contracts']);
        $this->assertEquals(0.0, $result['current_total']);
        $this->assertEquals(0.0, $result['simulated_total']);
        $this->assertEquals(0.0, $result['difference']);
    }

    // ── affected_contracts ────────────────────────────────────────────────────

    public function test_affected_contracts_counts_only_contracts_with_changed_commission(): void
    {
        $active  = $this->makeFormula('AnnualUsage * 0.05', true);
        $newForm = $this->makeFormula('AnnualUsage * 0.10');

        // c1: current = 500 (AnnualUsage 10000 * 0.05), new = 1000 → CHANGES
        $c1 = $this->makeContract(['annual_usage' => 10000]);
        $this->storeCalculation($c1, $active, 500.0);

        // c2: current = 1000 (AnnualUsage 10000 * 0.10), new = 1000 → SAME
        $c2 = $this->makeContract(['annual_usage' => 10000]);
        $this->storeCalculation($c2, $active, 1000.0);

        $result = $this->service->simulate($newForm);

        $this->assertEquals(1, $result['affected_contracts']);
        $this->assertEquals(2, $result['total_contracts']);
    }

    public function test_affected_contracts_is_zero_when_formula_produces_same_values(): void
    {
        $active  = $this->makeFormula('AnnualUsage * 0.05', true);
        $newForm = $this->makeFormula('AnnualUsage * 0.05');

        $c = $this->makeContract(['annual_usage' => 10000]);
        $this->storeCalculation($c, $active, 500.0); // 10000 * 0.05 = 500 = current

        $result = $this->service->simulate($newForm);

        $this->assertEquals(0, $result['affected_contracts']);
    }

    public function test_contract_with_no_prior_commission_is_affected_when_formula_yields_nonzero(): void
    {
        $newForm = $this->makeFormula('AnnualUsage * 0.10');
        $this->makeContract(['annual_usage' => 10000]); // no stored commission → current = 0

        $result = $this->service->simulate($newForm);

        // new = 1000, current = 0 → changed
        $this->assertEquals(1, $result['affected_contracts']);
    }

    // ── Commission totals ─────────────────────────────────────────────────────

    public function test_simulate_computes_current_and_simulated_totals(): void
    {
        $active  = $this->makeFormula('AnnualUsage * 0.05', true);
        $newForm = $this->makeFormula('AnnualUsage * 0.10');

        $contract = $this->makeContract(['annual_usage' => 10000]);
        $this->storeCalculation($contract, $active, 500.0);

        $result = $this->service->simulate($newForm);

        $this->assertEquals(500.0,  $result['current_total']);
        $this->assertEquals(1000.0, $result['simulated_total']);
        $this->assertEquals(500.0,  $result['difference']);
    }

    public function test_simulate_sums_across_multiple_contracts(): void
    {
        $active  = $this->makeFormula('AnnualUsage * 0.05', true);
        $newForm = $this->makeFormula('AnnualUsage * 0.10');

        $c1 = $this->makeContract(['annual_usage' => 10000]);
        $c2 = $this->makeContract(['annual_usage' => 20000]);

        $this->storeCalculation($c1, $active, 500.0);
        $this->storeCalculation($c2, $active, 1000.0);

        $result = $this->service->simulate($newForm);

        $this->assertEquals(1500.0, $result['current_total']);
        $this->assertEquals(3000.0, $result['simulated_total']);
        $this->assertEquals(1500.0, $result['difference']);
    }

    public function test_simulate_difference_is_negative_when_new_formula_yields_less(): void
    {
        $active  = $this->makeFormula('AnnualUsage * 0.10', true);
        $newForm = $this->makeFormula('AnnualUsage * 0.01');

        $contract = $this->makeContract(['annual_usage' => 10000]);
        $this->storeCalculation($contract, $active, 1000.0);

        $result = $this->service->simulate($newForm);

        $this->assertEquals(1000.0,  $result['current_total']);
        $this->assertEquals(100.0,   $result['simulated_total']);
        $this->assertEquals(-900.0,  $result['difference']);
    }

    // ── difference_percent ────────────────────────────────────────────────────

    public function test_simulate_calculates_difference_percent(): void
    {
        $active  = $this->makeFormula('AnnualUsage * 0.05', true);
        $newForm = $this->makeFormula('AnnualUsage * 0.10');

        $contract = $this->makeContract(['annual_usage' => 10000]);
        $this->storeCalculation($contract, $active, 500.0);

        $result = $this->service->simulate($newForm);

        $this->assertEquals(100.0, $result['difference_percent']);
    }

    public function test_simulate_difference_percent_is_zero_when_no_prior_commissions(): void
    {
        $newForm = $this->makeFormula('AnnualUsage * 0.10');
        $this->makeContract(['annual_usage' => 10000]);

        $result = $this->service->simulate($newForm);

        $this->assertEquals(0, $result['difference_percent']);
    }

    // ── Sub-variable support ──────────────────────────────────────────────────

    public function test_simulate_evaluates_sub_variables_in_execution_order(): void
    {
        $active  = $this->makeFormula('AnnualUsage * 0.05', true);
        $formula = $this->makeFormula('Base + Bonus');
        $formula->dependentVariables()->createMany([
            ['name' => 'Base',  'expression' => 'AnnualUsage * 0.08',   'execution_order' => 0],
            ['name' => 'Bonus', 'expression' => 'ContractLength * 100',  'execution_order' => 1],
        ]);
        $formula->load('dependentVariables');

        $contract = $this->makeContract([
            'annual_usage'    => 10000,
            'contract_length' => 24,
        ]);
        $this->storeCalculation($contract, $active, 500.0);

        // Base = 800, Bonus = 2400, total = 3200
        $result = $this->service->simulate($formula);

        $this->assertEquals(3200.0, $result['simulated_total']);
    }

    public function test_simulate_uses_all_four_base_variables(): void
    {
        $newForm = $this->makeFormula('AnnualUsage + ContractValue + ContractLength + RiskScore');
        $this->makeContract([
            'annual_usage'    => 1000,
            'contract_value'  => 2000,
            'contract_length' => 12,
            'risk_score'      => 5,
        ]);

        $result = $this->service->simulate($newForm);

        // 1000 + 2000 + 12 + 5 = 3017
        $this->assertEquals(3017.0, $result['simulated_total']);
    }

    // ── Latest commission per contract ────────────────────────────────────────

    public function test_simulate_uses_only_latest_commission_per_contract(): void
    {
        $active  = $this->makeFormula('AnnualUsage * 0.05', true);
        $newForm = $this->makeFormula('AnnualUsage * 0.10');

        $contract = $this->makeContract(['annual_usage' => 10000]);

        // Two calculations — only the latest (800) should count
        $base = now();
        $this->travelTo($base->copy()->subSeconds(10));
        $this->storeCalculation($contract, $active, 500.0); // older

        $this->travelTo($base->copy());
        $this->storeCalculation($contract, $active, 800.0); // latest

        $result = $this->service->simulate($newForm);

        $this->assertEquals(800.0, $result['current_total']);
    }

    public function test_simulate_treats_missing_commission_as_zero(): void
    {
        $active  = $this->makeFormula('AnnualUsage * 0.05', true);
        $newForm = $this->makeFormula('AnnualUsage * 0.10');

        $c1 = $this->makeContract(['annual_usage' => 10000]);
        $c2 = $this->makeContract(['annual_usage' => 5000]); // no commission stored

        $this->storeCalculation($c1, $active, 500.0);

        $result = $this->service->simulate($newForm);

        // current: 500 + 0 = 500
        // simulated: 1000 + 500 = 1500
        $this->assertEquals(500.0,  $result['current_total']);
        $this->assertEquals(1500.0, $result['simulated_total']);
    }

    // ── No writes ─────────────────────────────────────────────────────────────

    public function test_simulate_does_not_create_commission_records(): void
    {
        $active  = $this->makeFormula('AnnualUsage * 0.05', true);
        $newForm = $this->makeFormula('AnnualUsage * 0.10');

        $contract = $this->makeContract(['annual_usage' => 10000]);
        $this->storeCalculation($contract, $active, 500.0);

        $countBefore = CommissionCalculation::count();

        $this->service->simulate($newForm);

        $this->assertEquals($countBefore, CommissionCalculation::count());
    }

    public function test_simulate_does_not_change_any_formula_active_flag(): void
    {
        $active  = $this->makeFormula('AnnualUsage * 0.05', true);
        $newForm = $this->makeFormula('AnnualUsage * 0.10', false);

        $this->service->simulate($newForm);

        $this->assertTrue(Formula::find($active->id)->is_active);
        $this->assertFalse(Formula::find($newForm->id)->is_active);
    }
}
