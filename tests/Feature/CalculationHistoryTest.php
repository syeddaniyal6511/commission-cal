<?php

namespace Tests\Feature;

use App\Models\CommissionCalculation;
use App\Models\Contract;
use App\Models\Formula;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculationHistoryTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function auth(): static
    {
        return $this->actingAs(User::factory()->create(), 'sanctum');
    }

    private function makeContract(): Contract
    {
        return Contract::factory()->create();
    }

    private function makeFormula(): Formula
    {
        return Formula::create([
            'version'    => '1.0',
            'expression' => 'AnnualUsage * 0.05',
            'is_active'  => true,
        ]);
    }

    private function makeCalculation(Contract $contract, Formula $formula, array $overrides = []): CommissionCalculation
    {
        return CommissionCalculation::create(array_merge([
            'contract_id'     => $contract->id,
            'formula_id'      => $formula->id,
            'formula_version' => $formula->version,
            'commission'      => 500.00,
            'variables_json'  => [
                'AnnualUsage'    => 10000,
                'ContractValue'  => 50000,
                'ContractLength' => 24,
                'RiskScore'      => 50,
            ],
            'steps_json' => [
                ['type' => 'input', 'name' => 'AnnualUsage',    'value' => 10000],
                ['type' => 'input', 'name' => 'ContractValue',  'value' => 50000],
                ['type' => 'input', 'name' => 'ContractLength', 'value' => 24],
                ['type' => 'input', 'name' => 'RiskScore',      'value' => 50],
                ['type' => 'result', 'expression' => 'AnnualUsage * 0.05', 'value' => 500],
            ],
        ], $overrides));
    }

    private function historyUrl(Contract $contract): string
    {
        return "/api/contracts/{$contract->id}/calculations";
    }

    // =========================================================================
    // GROUP 1 — Authentication
    // =========================================================================

    public function test_history_requires_auth(): void
    {
        $contract = $this->makeContract();

        $this->getJson($this->historyUrl($contract))->assertStatus(401);
    }

    // =========================================================================
    // GROUP 2 — Pagination
    // =========================================================================

    public function test_history_returns_paginated_response(): void
    {
        $contract = $this->makeContract();
        $formula  = $this->makeFormula();
        $this->makeCalculation($contract, $formula);

        $this->auth()
             ->getJson($this->historyUrl($contract))
             ->assertStatus(200)
             ->assertJsonStructure(['data', 'current_page', 'last_page', 'total', 'per_page', 'from', 'to']);
    }

    public function test_history_shows_10_per_page(): void
    {
        $contract = $this->makeContract();
        $formula  = $this->makeFormula();

        for ($i = 0; $i < 15; $i++) {
            $this->makeCalculation($contract, $formula);
        }

        $response = $this->auth()->getJson($this->historyUrl($contract));

        $response->assertStatus(200);
        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(10, $response->json('per_page'));
        $this->assertEquals(15, $response->json('total'));
    }

    public function test_history_second_page_has_remaining_results(): void
    {
        $contract = $this->makeContract();
        $formula  = $this->makeFormula();

        for ($i = 0; $i < 13; $i++) {
            $this->makeCalculation($contract, $formula);
        }

        $response = $this->auth()->getJson($this->historyUrl($contract) . '?page=2');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
        $this->assertEquals(2, $response->json('current_page'));
    }

    public function test_history_total_reflects_all_calculations(): void
    {
        $contract = $this->makeContract();
        $formula  = $this->makeFormula();

        for ($i = 0; $i < 7; $i++) {
            $this->makeCalculation($contract, $formula);
        }

        $response = $this->auth()->getJson($this->historyUrl($contract));

        $this->assertEquals(7, $response->json('total'));
    }

    // =========================================================================
    // GROUP 3 — Ordering (latest first = audit trail order)
    // =========================================================================

    public function test_history_returns_latest_calculation_first(): void
    {
        $contract = $this->makeContract();
        $formula  = $this->makeFormula();

        $base = now();

        $this->travelTo($base->copy()->subSeconds(10));
        $this->makeCalculation($contract, $formula, ['commission' => 100.00]);

        $this->travelTo($base->copy()->subSeconds(5));
        $this->makeCalculation($contract, $formula, ['commission' => 200.00]);

        $this->travelTo($base->copy());
        $this->makeCalculation($contract, $formula, ['commission' => 300.00]);

        $response = $this->auth()->getJson($this->historyUrl($contract));

        $data = $response->json('data');
        $this->assertEquals(300.00, $data[0]['commission']);
        $this->assertEquals(200.00, $data[1]['commission']);
        $this->assertEquals(100.00, $data[2]['commission']);
    }

    // =========================================================================
    // GROUP 4 — Isolation (each contract sees only its own calculations)
    // =========================================================================

    public function test_history_only_returns_calculations_for_requested_contract(): void
    {
        $contractA = $this->makeContract();
        $contractB = $this->makeContract();
        $formula   = $this->makeFormula();

        $this->makeCalculation($contractA, $formula, ['commission' => 111.00]);
        $this->makeCalculation($contractA, $formula, ['commission' => 222.00]);
        $this->makeCalculation($contractB, $formula, ['commission' => 999.00]);

        $response = $this->auth()->getJson($this->historyUrl($contractA));

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('total'));

        $commissions = array_column($response->json('data'), 'commission');
        $this->assertNotContains(999.00, $commissions);
    }

    public function test_history_returns_empty_data_when_no_calculations(): void
    {
        $contract = $this->makeContract();

        $response = $this->auth()->getJson($this->historyUrl($contract));

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
        $this->assertEquals(0, $response->json('total'));
    }

    public function test_history_returns_404_for_non_existent_contract(): void
    {
        $this->auth()
             ->getJson('/api/contracts/999999/calculations')
             ->assertStatus(404);
    }

    // =========================================================================
    // GROUP 5 — Response structure (audit trail fields)
    // =========================================================================

    public function test_history_item_contains_required_fields(): void
    {
        $contract = $this->makeContract();
        $formula  = $this->makeFormula();
        $this->makeCalculation($contract, $formula);

        $response = $this->auth()->getJson($this->historyUrl($contract));

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [[
                         'id',
                         'contract_id',
                         'formula_id',
                         'formula_version',
                         'commission',
                         'variables_json',
                         'steps_json',
                         'created_at',
                         'updated_at',
                     ]],
                 ]);
    }

    public function test_history_commission_value_is_correct(): void
    {
        $contract = $this->makeContract();
        $formula  = $this->makeFormula();
        $this->makeCalculation($contract, $formula, ['commission' => 1234.5678]);

        $response = $this->auth()->getJson($this->historyUrl($contract));

        $this->assertEquals(1234.5678, $response->json('data.0.commission'));
    }

    public function test_history_formula_version_is_stored(): void
    {
        $contract = $this->makeContract();
        $formula  = $this->makeFormula();
        $this->makeCalculation($contract, $formula, ['formula_version' => 'v2']);

        $response = $this->auth()->getJson($this->historyUrl($contract));

        $this->assertEquals('v2', $response->json('data.0.formula_version'));
    }

    public function test_history_variables_json_is_returned_as_object(): void
    {
        $contract = $this->makeContract();
        $formula  = $this->makeFormula();
        $this->makeCalculation($contract, $formula);

        $response = $this->auth()->getJson($this->historyUrl($contract));

        $vars = $response->json('data.0.variables_json');
        $this->assertIsArray($vars);
        $this->assertArrayHasKey('AnnualUsage', $vars);
        $this->assertArrayHasKey('ContractValue', $vars);
        $this->assertArrayHasKey('ContractLength', $vars);
        $this->assertArrayHasKey('RiskScore', $vars);
    }

    public function test_history_steps_json_contains_input_and_result_steps(): void
    {
        $contract = $this->makeContract();
        $formula  = $this->makeFormula();
        $this->makeCalculation($contract, $formula);

        $response = $this->auth()->getJson($this->historyUrl($contract));

        $steps = $response->json('data.0.steps_json');
        $types = array_column($steps, 'type');

        $this->assertContains('input', $types);
        $this->assertContains('result', $types);
    }

    public function test_history_steps_json_has_four_input_steps(): void
    {
        $contract = $this->makeContract();
        $formula  = $this->makeFormula();
        $this->makeCalculation($contract, $formula);

        $response = $this->auth()->getJson($this->historyUrl($contract));

        $steps      = $response->json('data.0.steps_json');
        $inputSteps = array_filter($steps, fn ($s) => $s['type'] === 'input');

        $this->assertCount(4, $inputSteps);
    }

    public function test_history_steps_json_with_computed_step(): void
    {
        $contract = $this->makeContract();
        $formula  = $this->makeFormula();

        $this->makeCalculation($contract, $formula, [
            'steps_json' => [
                ['type' => 'input',    'name' => 'AnnualUsage', 'value' => 10000],
                ['type' => 'computed', 'name' => 'BaseCommission', 'expression' => 'AnnualUsage * 0.05', 'value' => 500],
                ['type' => 'result',   'expression' => 'BaseCommission', 'value' => 500],
            ],
        ]);

        $response = $this->auth()->getJson($this->historyUrl($contract));

        $steps         = $response->json('data.0.steps_json');
        $computedSteps = array_filter($steps, fn ($s) => $s['type'] === 'computed');

        $this->assertCount(1, $computedSteps);
        $this->assertEquals('BaseCommission', array_values($computedSteps)[0]['name']);
    }

    // =========================================================================
    // GROUP 6 — Cascade delete (audit trail integrity)
    // =========================================================================

    public function test_deleting_contract_removes_its_calculations(): void
    {
        $contract = $this->makeContract();
        $formula  = $this->makeFormula();

        $calc = $this->makeCalculation($contract, $formula);

        $this->auth()->deleteJson("/api/contracts/{$contract->id}");

        $this->assertDatabaseMissing('commission_calculations', ['id' => $calc->id]);
    }

    public function test_deleting_contract_does_not_affect_other_contracts_calculations(): void
    {
        $contractA = $this->makeContract();
        $contractB = $this->makeContract();
        $formula   = $this->makeFormula();

        $calcA = $this->makeCalculation($contractA, $formula);
        $calcB = $this->makeCalculation($contractB, $formula);

        $this->auth()->deleteJson("/api/contracts/{$contractA->id}");

        $this->assertDatabaseMissing('commission_calculations', ['id' => $calcA->id]);
        $this->assertDatabaseHas('commission_calculations',    ['id' => $calcB->id]);
    }

    public function test_nullifying_formula_does_not_delete_calculations(): void
    {
        $contract = $this->makeContract();
        $formula  = $this->makeFormula();

        $calc = $this->makeCalculation($contract, $formula);

        // Formula deleted → formula_id becomes null, calculation stays
        $formula->delete();

        $this->assertDatabaseHas('commission_calculations', [
            'id'         => $calc->id,
            'formula_id' => null,
        ]);
    }
}
