<?php

namespace Tests\Feature;

use App\Models\CommissionCalculation;
use App\Models\Contract;
use App\Models\Formula;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormulaSimulationTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function auth(): static
    {
        return $this->actingAs(User::factory()->create(), 'sanctum');
    }

    private function makeActiveFormula(): Formula
    {
        return Formula::create([
            'version'    => '1',
            'expression' => 'AnnualUsage * 0.05',
            'is_active'  => true,
        ]);
    }

    private function makeInactiveFormula(string $expression = 'AnnualUsage * 0.10'): Formula
    {
        return Formula::create([
            'version'    => '2',
            'expression' => $expression,
            'is_active'  => false,
        ]);
    }

    private function makeInactiveFormulaWithSubVars(): Formula
    {
        $formula = Formula::create([
            'version'    => '3',
            'expression' => 'BaseCommission + Bonus',
            'is_active'  => false,
        ]);

        $formula->dependentVariables()->createMany([
            ['name' => 'BaseCommission', 'expression' => 'AnnualUsage * 0.08',   'execution_order' => 0],
            ['name' => 'Bonus',          'expression' => 'ContractLength * 100',  'execution_order' => 1],
        ]);

        return $formula;
    }

    private function makeContract(array $overrides = []): Contract
    {
        return Contract::factory()->create($overrides);
    }

    private function storeCalculation(Contract $contract, Formula $formula, float $commission): CommissionCalculation
    {
        return CommissionCalculation::create([
            'contract_id'     => $contract->id,
            'formula_id'      => $formula->id,
            'formula_version' => $formula->version,
            'commission'      => $commission,
            'variables_json'  => ['AnnualUsage' => 10000, 'ContractValue' => 50000, 'ContractLength' => 24, 'RiskScore' => 50],
            'steps_json'      => [],
        ]);
    }

    private function simulateUrl(Formula $formula): string
    {
        return "/api/formulas/{$formula->id}/simulate";
    }

    // =========================================================================
    // GROUP 1 — Authentication
    // =========================================================================

    public function test_simulate_requires_auth(): void
    {
        $formula = $this->makeInactiveFormula();

        $this->getJson($this->simulateUrl($formula))->assertStatus(401);
    }

    public function test_simulate_returns_404_for_non_existent_formula(): void
    {
        $this->auth()
             ->getJson('/api/formulas/999999/simulate')
             ->assertStatus(404);
    }

    // =========================================================================
    // GROUP 2 — Response structure
    // =========================================================================

    public function test_simulate_returns_required_fields(): void
    {
        $this->makeContract();
        $formula = $this->makeInactiveFormula();

        $this->auth()
             ->getJson($this->simulateUrl($formula))
             ->assertStatus(200)
             ->assertJsonStructure([
                 'total_contracts',
                 'affected_contracts',
                 'current_total',
                 'simulated_total',
                 'difference',
                 'difference_percent',
             ]);
    }

    public function test_simulate_works_on_active_formula_too(): void
    {
        $formula = $this->makeActiveFormula();

        $this->auth()
             ->getJson($this->simulateUrl($formula))
             ->assertStatus(200);
    }

    // =========================================================================
    // GROUP 3 — Contract count
    // =========================================================================

    public function test_simulate_counts_all_contracts(): void
    {
        Contract::factory()->count(5)->create();
        $formula = $this->makeInactiveFormula();

        $response = $this->auth()->getJson($this->simulateUrl($formula));

        $this->assertEquals(5, $response->json('total_contracts'));
    }

    public function test_simulate_returns_zero_contracts_when_none_exist(): void
    {
        $formula = $this->makeInactiveFormula();

        $response = $this->auth()->getJson($this->simulateUrl($formula));

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('total_contracts'));
        $this->assertEquals(0, $response->json('current_total'));
        $this->assertEquals(0, $response->json('simulated_total'));
        $this->assertEquals(0, $response->json('difference'));
    }

    // =========================================================================
    // GROUP 4 — Commission maths
    // =========================================================================

    public function test_simulate_calculates_simulated_total_correctly(): void
    {
        $active  = $this->makeActiveFormula();

        // Contract: AnnualUsage = 10000
        // New formula: AnnualUsage * 0.10 → 1000
        $contract = $this->makeContract(['annual_usage' => 10000]);
        $this->storeCalculation($contract, $active, 500.00); // current = 500

        $newFormula = $this->makeInactiveFormula('AnnualUsage * 0.10');

        $response = $this->auth()->getJson($this->simulateUrl($newFormula));

        $this->assertEquals(500.00,  $response->json('current_total'));
        $this->assertEquals(1000.00, $response->json('simulated_total'));
        $this->assertEquals(500.00,  $response->json('difference'));
        $this->assertEquals(100.00,  $response->json('difference_percent'));
    }

    public function test_simulate_sums_all_contracts(): void
    {
        $active = $this->makeActiveFormula();

        // Two contracts, each AnnualUsage = 10000, current = 500 each
        $c1 = $this->makeContract(['annual_usage' => 10000]);
        $c2 = $this->makeContract(['annual_usage' => 20000]);
        $this->storeCalculation($c1, $active, 500.00);
        $this->storeCalculation($c2, $active, 1000.00);

        // New formula: AnnualUsage * 0.10 → c1=1000, c2=2000, total=3000
        $newFormula = $this->makeInactiveFormula('AnnualUsage * 0.10');

        $response = $this->auth()->getJson($this->simulateUrl($newFormula));

        $this->assertEquals(1500.00, $response->json('current_total'));
        $this->assertEquals(3000.00, $response->json('simulated_total'));
        $this->assertEquals(1500.00, $response->json('difference'));
    }

    public function test_simulate_difference_is_negative_when_new_formula_gives_less(): void
    {
        $active   = $this->makeActiveFormula();
        $contract = $this->makeContract(['annual_usage' => 10000]);
        $this->storeCalculation($contract, $active, 500.00); // current high

        // New formula gives less: 10000 * 0.01 = 100
        $newFormula = $this->makeInactiveFormula('AnnualUsage * 0.01');

        $response = $this->auth()->getJson($this->simulateUrl($newFormula));

        $this->assertEquals(500.00, $response->json('current_total'));
        $this->assertEquals(100.00, $response->json('simulated_total'));
        $this->assertEquals(-400.00, $response->json('difference'));
        $this->assertEquals(-80.00, $response->json('difference_percent'));
    }

    public function test_simulate_difference_percent_is_zero_when_no_current_commissions(): void
    {
        // Contract exists but no stored commission
        $this->makeContract(['annual_usage' => 10000]);
        $formula = $this->makeInactiveFormula('AnnualUsage * 0.10');

        $response = $this->auth()->getJson($this->simulateUrl($formula));

        // current_total = 0, so percent = 0 (avoids division by zero)
        $this->assertEquals(0, $response->json('difference_percent'));
        $this->assertEquals(1000.00, $response->json('simulated_total'));
    }

    // =========================================================================
    // GROUP 5 — Sub-variable formulas
    // =========================================================================

    public function test_simulate_evaluates_sub_variables_in_order(): void
    {
        $active   = $this->makeActiveFormula();
        $contract = $this->makeContract([
            'annual_usage'    => 10000,
            'contract_length' => 24,
        ]);
        $this->storeCalculation($contract, $active, 500.00);

        // BaseCommission = 10000 * 0.08 = 800
        // Bonus = 24 * 100 = 2400
        // Total = 800 + 2400 = 3200
        $newFormula = $this->makeInactiveFormulaWithSubVars();

        $response = $this->auth()->getJson($this->simulateUrl($newFormula));

        $this->assertEquals(3200.00, $response->json('simulated_total'));
        $this->assertEquals(2700.00, $response->json('difference')); // 3200 - 500
    }

    public function test_simulate_uses_all_four_base_variables(): void
    {
        $active   = $this->makeActiveFormula();
        $contract = $this->makeContract([
            'annual_usage'    => 1000,
            'contract_value'  => 2000,
            'contract_length' => 12,
            'risk_score'      => 5,
        ]);
        $this->storeCalculation($contract, $active, 50.00);

        // 1000 + 2000 + 12 + 5 = 3017
        $formula = $this->makeInactiveFormula('AnnualUsage + ContractValue + ContractLength + RiskScore');

        $response = $this->auth()->getJson($this->simulateUrl($formula));

        $this->assertEquals(3017.00, $response->json('simulated_total'));
    }

    // =========================================================================
    // GROUP 6 — Current commission uses only the LATEST record per contract
    // =========================================================================

    public function test_simulate_uses_latest_commission_per_contract(): void
    {
        $active   = $this->makeActiveFormula();
        $contract = $this->makeContract(['annual_usage' => 10000]);

        // Two calculations — only latest (800) should count as current
        $base = now();
        $this->travelTo($base->copy()->subSeconds(10));
        $this->storeCalculation($contract, $active, 500.00); // older

        $this->travelTo($base->copy());
        $this->storeCalculation($contract, $active, 800.00); // latest

        $newFormula = $this->makeInactiveFormula('AnnualUsage * 0.10');

        $response = $this->auth()->getJson($this->simulateUrl($newFormula));

        // current_total must be 800 (latest), not 1300 (both)
        $this->assertEquals(800.00, $response->json('current_total'));
    }

    public function test_simulate_handles_contract_with_no_prior_calculation(): void
    {
        $active = $this->makeActiveFormula();

        $c1 = $this->makeContract(['annual_usage' => 10000]);
        $c2 = $this->makeContract(['annual_usage' => 5000]);  // no calculation stored

        $this->storeCalculation($c1, $active, 500.00);

        $newFormula = $this->makeInactiveFormula('AnnualUsage * 0.10');

        $response = $this->auth()->getJson($this->simulateUrl($newFormula));

        // c2 contributes 0 to current, 500 to simulated
        $this->assertEquals(500.00,  $response->json('current_total'));
        $this->assertEquals(1500.00, $response->json('simulated_total')); // 1000 + 500
        $this->assertEquals(1000.00, $response->json('difference'));
    }

    // =========================================================================
    // GROUP 7 — Simulation does NOT write to DB
    // =========================================================================

    public function test_simulate_does_not_create_commission_records(): void
    {
        $active   = $this->makeActiveFormula();
        $contract = $this->makeContract(['annual_usage' => 10000]);
        $this->storeCalculation($contract, $active, 500.00);

        $countBefore = CommissionCalculation::count();

        $formula = $this->makeInactiveFormula('AnnualUsage * 0.10');
        $this->auth()->getJson($this->simulateUrl($formula));

        $this->assertEquals($countBefore, CommissionCalculation::count());
    }

    public function test_simulate_does_not_change_active_formula(): void
    {
        $active      = $this->makeActiveFormula();
        $newFormula  = $this->makeInactiveFormula();

        $this->auth()->getJson($this->simulateUrl($newFormula));

        $this->assertTrue(Formula::find($active->id)->is_active);
        $this->assertFalse(Formula::find($newFormula->id)->is_active);
    }
}
