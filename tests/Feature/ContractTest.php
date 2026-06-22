<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\CommissionCalculation;
use App\Models\Formula;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function user(): User
    {
        return User::factory()->create();
    }

    private function auth(): static
    {
        return $this->actingAs($this->user(), 'sanctum');
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'contract_no'     => 'CON-000001',
            'annual_usage'    => 10000.00,
            'contract_value'  => 50000.00,
            'contract_length' => 24,
            'risk_score'      => 50.00,
        ], $overrides);
    }

    private function makeContract(array $overrides = []): Contract
    {
        return Contract::factory()->create($overrides);
    }

    // =========================================================================
    // GROUP 1 — Authentication (all endpoints require auth)
    // =========================================================================

    public function test_index_requires_auth(): void
    {
        $this->getJson('/api/contracts')->assertStatus(401);
    }

    public function test_store_requires_auth(): void
    {
        $this->postJson('/api/contracts', $this->validPayload())->assertStatus(401);
    }

    public function test_show_requires_auth(): void
    {
        $contract = $this->makeContract();
        $this->getJson("/api/contracts/{$contract->id}")->assertStatus(401);
    }

    public function test_update_requires_auth(): void
    {
        $contract = $this->makeContract();
        $this->putJson("/api/contracts/{$contract->id}", $this->validPayload())->assertStatus(401);
    }

    public function test_destroy_requires_auth(): void
    {
        $contract = $this->makeContract();
        $this->deleteJson("/api/contracts/{$contract->id}")->assertStatus(401);
    }

    // =========================================================================
    // GROUP 2 — Index / list
    // =========================================================================

    public function test_index_returns_paginated_list(): void
    {
        $this->makeContract();
        $this->makeContract();

        $this->auth()
             ->getJson('/api/contracts')
             ->assertStatus(200)
             ->assertJsonStructure(['data', 'current_page', 'last_page', 'total', 'per_page']);
    }

    public function test_index_default_per_page_is_15(): void
    {
        Contract::factory()->count(20)->create();

        $response = $this->auth()->getJson('/api/contracts');

        $response->assertStatus(200);
        $this->assertCount(15, $response->json('data'));
    }

    public function test_index_respects_per_page_param(): void
    {
        Contract::factory()->count(10)->create();

        $response = $this->auth()->getJson('/api/contracts?per_page=5');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
    }

    public function test_index_search_by_contract_no(): void
    {
        $this->makeContract(['contract_no' => 'CON-ALPHA']);
        $this->makeContract(['contract_no' => 'CON-BETA']);

        $response = $this->auth()->getJson('/api/contracts?search=ALPHA');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('CON-ALPHA', $response->json('data.0.contract_no'));
    }

    public function test_index_search_partial_match(): void
    {
        $this->makeContract(['contract_no' => 'CON-000100']);
        $this->makeContract(['contract_no' => 'CON-001001']);
        $this->makeContract(['contract_no' => 'CON-999999']);

        $response = $this->auth()->getJson('/api/contracts?search=100');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_returns_latest_first(): void
    {
        $this->travelTo(now()->subSeconds(5));
        $this->makeContract(['contract_no' => 'CON-FIRST']);

        $this->travelBack();
        $this->makeContract(['contract_no' => 'CON-SECOND']);

        $response = $this->auth()->getJson('/api/contracts');

        $this->assertEquals('CON-SECOND', $response->json('data.0.contract_no'));
        $this->assertEquals('CON-FIRST', $response->json('data.1.contract_no'));
    }

    public function test_index_empty_returns_empty_data(): void
    {
        $response = $this->auth()->getJson('/api/contracts');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    // =========================================================================
    // GROUP 3 — Store / create
    // =========================================================================

    public function test_store_valid_contract_returns_201(): void
    {
        $this->auth()
             ->postJson('/api/contracts', $this->validPayload())
             ->assertStatus(201)
             ->assertJsonFragment(['contract_no' => 'CON-000001']);
    }

    public function test_store_saves_to_database(): void
    {
        $this->auth()->postJson('/api/contracts', $this->validPayload());

        $this->assertDatabaseHas('contracts', [
            'contract_no'     => 'CON-000001',
            'annual_usage'    => 10000.00,
            'contract_value'  => 50000.00,
            'contract_length' => 24,
            'risk_score'      => 50.00,
        ]);
    }

    public function test_store_contract_no_is_required(): void
    {
        $this->auth()
             ->postJson('/api/contracts', $this->validPayload(['contract_no' => '']))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['contract_no']);
    }

    public function test_store_contract_no_must_be_unique(): void
    {
        $this->makeContract(['contract_no' => 'CON-DUPE']);

        $this->auth()
             ->postJson('/api/contracts', $this->validPayload(['contract_no' => 'CON-DUPE']))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['contract_no']);
    }

    public function test_store_contract_no_max_50_characters(): void
    {
        $this->auth()
             ->postJson('/api/contracts', $this->validPayload(['contract_no' => str_repeat('A', 51)]))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['contract_no']);
    }

    public function test_store_annual_usage_is_required(): void
    {
        $this->auth()
             ->postJson('/api/contracts', $this->validPayload(['annual_usage' => '']))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['annual_usage']);
    }

    public function test_store_annual_usage_must_be_numeric(): void
    {
        $this->auth()
             ->postJson('/api/contracts', $this->validPayload(['annual_usage' => 'not-a-number']))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['annual_usage']);
    }

    public function test_store_annual_usage_cannot_be_negative(): void
    {
        $this->auth()
             ->postJson('/api/contracts', $this->validPayload(['annual_usage' => -1]))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['annual_usage']);
    }

    public function test_store_annual_usage_zero_is_allowed(): void
    {
        $this->auth()
             ->postJson('/api/contracts', $this->validPayload(['annual_usage' => 0]))
             ->assertStatus(201);
    }

    public function test_store_contract_value_is_required(): void
    {
        $this->auth()
             ->postJson('/api/contracts', $this->validPayload(['contract_value' => '']))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['contract_value']);
    }

    public function test_store_contract_value_cannot_be_negative(): void
    {
        $this->auth()
             ->postJson('/api/contracts', $this->validPayload(['contract_value' => -100]))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['contract_value']);
    }

    public function test_store_contract_length_is_required(): void
    {
        $this->auth()
             ->postJson('/api/contracts', $this->validPayload(['contract_length' => '']))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['contract_length']);
    }

    public function test_store_contract_length_must_be_integer(): void
    {
        $this->auth()
             ->postJson('/api/contracts', $this->validPayload(['contract_length' => 12.5]))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['contract_length']);
    }

    public function test_store_contract_length_min_is_1(): void
    {
        $this->auth()
             ->postJson('/api/contracts', $this->validPayload(['contract_length' => 0]))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['contract_length']);
    }

    public function test_store_contract_length_max_is_360(): void
    {
        $this->auth()
             ->postJson('/api/contracts', $this->validPayload(['contract_length' => 361]))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['contract_length']);
    }

    public function test_store_contract_length_boundary_values_pass(): void
    {
        $this->auth()
             ->postJson('/api/contracts', $this->validPayload(['contract_no' => 'CON-A', 'contract_length' => 1]))
             ->assertStatus(201);

        $this->auth()
             ->postJson('/api/contracts', $this->validPayload(['contract_no' => 'CON-B', 'contract_length' => 360]))
             ->assertStatus(201);
    }

    public function test_store_risk_score_is_required(): void
    {
        $this->auth()
             ->postJson('/api/contracts', $this->validPayload(['risk_score' => '']))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['risk_score']);
    }

    public function test_store_risk_score_min_is_0(): void
    {
        $this->auth()
             ->postJson('/api/contracts', $this->validPayload(['risk_score' => -0.01]))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['risk_score']);
    }

    public function test_store_risk_score_max_is_100(): void
    {
        $this->auth()
             ->postJson('/api/contracts', $this->validPayload(['risk_score' => 100.01]))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['risk_score']);
    }

    public function test_store_risk_score_boundary_values_pass(): void
    {
        $this->auth()
             ->postJson('/api/contracts', $this->validPayload(['contract_no' => 'CON-A', 'risk_score' => 0]))
             ->assertStatus(201);

        $this->auth()
             ->postJson('/api/contracts', $this->validPayload(['contract_no' => 'CON-B', 'risk_score' => 100]))
             ->assertStatus(201);
    }

    // =========================================================================
    // GROUP 4 — Show
    // =========================================================================

    public function test_show_returns_contract(): void
    {
        $contract = $this->makeContract();

        $this->auth()
             ->getJson("/api/contracts/{$contract->id}")
             ->assertStatus(200)
             ->assertJsonFragment(['contract_no' => $contract->contract_no]);
    }

    public function test_show_returns_404_for_non_existent_contract(): void
    {
        $this->auth()
             ->getJson('/api/contracts/999999')
             ->assertStatus(404);
    }

    public function test_show_response_contains_all_fields(): void
    {
        $contract = $this->makeContract();

        $this->auth()
             ->getJson("/api/contracts/{$contract->id}")
             ->assertStatus(200)
             ->assertJsonStructure(['id', 'contract_no', 'annual_usage', 'contract_value', 'contract_length', 'risk_score', 'created_at', 'updated_at']);
    }

    // =========================================================================
    // GROUP 5 — Update
    // =========================================================================

    public function test_update_valid_data_returns_200(): void
    {
        $contract = $this->makeContract();

        $this->auth()
             ->putJson("/api/contracts/{$contract->id}", $this->validPayload(['contract_no' => $contract->contract_no]))
             ->assertStatus(200);
    }

    public function test_update_persists_changes(): void
    {
        $contract = $this->makeContract();

        $this->auth()->putJson("/api/contracts/{$contract->id}", [
            'contract_no'     => $contract->contract_no,
            'annual_usage'    => 99999.00,
            'contract_value'  => 12345.00,
            'contract_length' => 36,
            'risk_score'      => 75.50,
        ]);

        $this->assertDatabaseHas('contracts', [
            'id'              => $contract->id,
            'annual_usage'    => 99999.00,
            'contract_length' => 36,
        ]);
    }

    public function test_update_can_keep_own_contract_no(): void
    {
        $contract = $this->makeContract(['contract_no' => 'CON-KEEP']);

        $this->auth()
             ->putJson("/api/contracts/{$contract->id}", $this->validPayload(['contract_no' => 'CON-KEEP']))
             ->assertStatus(200);
    }

    public function test_update_cannot_use_another_contracts_no(): void
    {
        $existing = $this->makeContract(['contract_no' => 'CON-TAKEN']);
        $target   = $this->makeContract(['contract_no' => 'CON-TARGET']);

        $this->auth()
             ->putJson("/api/contracts/{$target->id}", $this->validPayload(['contract_no' => 'CON-TAKEN']))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['contract_no']);
    }

    public function test_update_returns_404_for_non_existent_contract(): void
    {
        $this->auth()
             ->putJson('/api/contracts/999999', $this->validPayload())
             ->assertStatus(404);
    }

    public function test_update_risk_score_above_100_fails(): void
    {
        $contract = $this->makeContract();

        $this->auth()
             ->putJson("/api/contracts/{$contract->id}", $this->validPayload(['risk_score' => 101]))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['risk_score']);
    }

    // =========================================================================
    // GROUP 6 — Destroy
    // =========================================================================

    public function test_destroy_returns_204(): void
    {
        $contract = $this->makeContract();

        $this->auth()
             ->deleteJson("/api/contracts/{$contract->id}")
             ->assertStatus(204);
    }

    public function test_destroy_removes_from_database(): void
    {
        $contract = $this->makeContract();

        $this->auth()->deleteJson("/api/contracts/{$contract->id}");

        $this->assertDatabaseMissing('contracts', ['id' => $contract->id]);
    }

    public function test_destroy_cascades_to_commission_calculations(): void
    {
        $contract = $this->makeContract();
        $formula  = Formula::create(['version' => '1.0', 'expression' => 'AnnualUsage * 0.05', 'is_active' => true]);

        CommissionCalculation::create([
            'contract_id'     => $contract->id,
            'formula_id'      => $formula->id,
            'formula_version' => '1.0',
            'commission'      => 500.00,
            'variables_json'  => ['AnnualUsage' => 10000],
            'steps_json'      => [],
        ]);

        $this->auth()->deleteJson("/api/contracts/{$contract->id}");

        $this->assertDatabaseMissing('commission_calculations', ['contract_id' => $contract->id]);
    }

    public function test_destroy_returns_404_for_non_existent_contract(): void
    {
        $this->auth()
             ->deleteJson('/api/contracts/999999')
             ->assertStatus(404);
    }
}
