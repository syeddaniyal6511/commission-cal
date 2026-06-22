<?php

namespace Tests\Feature;

use App\Models\Formula;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormulaStoreTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function actingAsUser()
    {
        $user = User::factory()->create();
        return $this->actingAs($user, 'sanctum');
    }

    private function postFormula(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->actingAsUser()
                    ->postJson('/api/formulas', $payload);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'version'    => '1.0',
            'expression' => 'AnnualUsage * 0.05',
            'variables'  => [],
        ], $overrides);
    }

    // =========================================================================
    // GROUP 1 — Authentication
    // =========================================================================

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->postJson('/api/formulas', $this->validPayload())
             ->assertStatus(401);
    }

    // =========================================================================
    // GROUP 2 — StoreFormulaRequest field validation
    // =========================================================================

    public function test_version_is_required(): void
    {
        $this->postFormula($this->validPayload(['version' => '']))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['version']);
    }

    public function test_version_max_50_characters(): void
    {
        $this->postFormula($this->validPayload(['version' => str_repeat('a', 51)]))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['version']);
    }

    public function test_expression_is_required(): void
    {
        $this->postFormula($this->validPayload(['expression' => '']))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['expression']);
    }

    public function test_expression_max_2000_characters(): void
    {
        $this->postFormula($this->validPayload(['expression' => str_repeat('A', 2001)]))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['expression']);
    }

    public function test_variables_cannot_exceed_8(): void
    {
        $vars = array_map(fn ($i) => [
            'name'            => "Var{$i}",
            'expression'      => 'AnnualUsage * 0.01',
            'execution_order' => $i,
        ], range(0, 8)); // 9 items → over limit

        $this->postFormula($this->validPayload(['variables' => $vars]))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['variables']);
    }

    public function test_sub_variable_name_is_required(): void
    {
        $this->postFormula($this->validPayload([
            'variables' => [
                ['name' => '', 'expression' => 'AnnualUsage * 0.05', 'execution_order' => 0],
            ],
        ]))->assertStatus(422)
           ->assertJsonValidationErrors(['variables.0.name']);
    }

    public function test_sub_variable_expression_is_required(): void
    {
        $this->postFormula($this->validPayload([
            'variables' => [
                ['name' => 'BaseCommission', 'expression' => '', 'execution_order' => 0],
            ],
        ]))->assertStatus(422)
           ->assertJsonValidationErrors(['variables.0.expression']);
    }

    // =========================================================================
    // GROUP 3 — ExpressionLanguage: main expression syntax
    // =========================================================================

    public function test_main_expression_with_consecutive_operators_fails(): void
    {
        // +/ is invalid syntax — ExpressionLanguage throws SyntaxError
        $this->postFormula($this->validPayload(['expression' => 'AnnualUsage +/ ContractValue']))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['expression']);
    }

    public function test_main_expression_slash_star_fails(): void
    {
        // /* fails — * cannot be a unary operator in ExpressionLanguage
        $this->postFormula($this->validPayload(['expression' => 'AnnualUsage /* ContractValue']))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['expression']);
    }

    public function test_main_expression_plus_star_fails(): void
    {
        $this->postFormula($this->validPayload(['expression' => 'AnnualUsage +* ContractValue']))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['expression']);
    }

    public function test_main_expression_double_slash_fails(): void
    {
        $this->postFormula($this->validPayload(['expression' => 'AnnualUsage // ContractValue']))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['expression']);
    }

    public function test_main_expression_missing_operand_fails(): void
    {
        // Trailing operator with no right-hand side
        $this->postFormula($this->validPayload(['expression' => 'AnnualUsage *']))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['expression']);
    }

    public function test_main_expression_unknown_variable_fails(): void
    {
        // ExpressionLanguage::parse() rejects names not in the allowed list
        $this->postFormula($this->validPayload(['expression' => 'UnknownVar * 0.05']))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['expression']);
    }

    public function test_main_expression_uses_defined_sub_variable_passes(): void
    {
        // BaseCommission is defined in variables — main expression may reference it
        $this->postFormula($this->validPayload([
            'expression' => 'BaseCommission * RiskScore',
            'variables'  => [
                ['name' => 'BaseCommission', 'expression' => 'AnnualUsage * 0.05', 'execution_order' => 0],
            ],
        ]))->assertStatus(201);
    }

    public function test_main_expression_uses_all_four_base_variables_passes(): void
    {
        $this->postFormula($this->validPayload([
            'expression' => '(AnnualUsage * 0.05) + (ContractValue * 0.02) + ContractLength + RiskScore',
        ]))->assertStatus(201);
    }

    // =========================================================================
    // GROUP 4 — ExpressionLanguage: sub-variable expression syntax
    // =========================================================================

    public function test_sub_variable_consecutive_operators_fails(): void
    {
        $this->postFormula($this->validPayload([
            'expression' => 'BaseCommission',
            'variables'  => [
                ['name' => 'BaseCommission', 'expression' => 'AnnualUsage */ 0.05', 'execution_order' => 0],
            ],
        ]))->assertStatus(422)
           ->assertJsonValidationErrors(['variables']);
    }

    public function test_sub_variable_unknown_identifier_fails(): void
    {
        $this->postFormula($this->validPayload([
            'expression' => 'BaseCommission',
            'variables'  => [
                ['name' => 'BaseCommission', 'expression' => 'GhostVar * 0.05', 'execution_order' => 0],
            ],
        ]))->assertStatus(422)
           ->assertJsonValidationErrors(['variables']);
    }

    public function test_sub_variable_self_reference_fails(): void
    {
        // BaseCommission references itself — excluded from its own allowed names
        $this->postFormula($this->validPayload([
            'expression' => 'BaseCommission',
            'variables'  => [
                ['name' => 'BaseCommission', 'expression' => 'BaseCommission * 0.05', 'execution_order' => 0],
            ],
        ]))->assertStatus(422)
           ->assertJsonValidationErrors(['variables']);
    }

    public function test_sub_variable_missing_right_operand_fails(): void
    {
        $this->postFormula($this->validPayload([
            'expression' => 'BaseCommission',
            'variables'  => [
                ['name' => 'BaseCommission', 'expression' => 'AnnualUsage +', 'execution_order' => 0],
            ],
        ]))->assertStatus(422)
           ->assertJsonValidationErrors(['variables']);
    }

    public function test_sub_variable_can_reference_another_sub_variable_passes(): void
    {
        $this->postFormula($this->validPayload([
            'expression' => 'FinalAmount',
            'variables'  => [
                ['name' => 'BaseCommission', 'expression' => 'AnnualUsage * 0.05',      'execution_order' => 0],
                ['name' => 'FinalAmount',    'expression' => 'BaseCommission * RiskScore', 'execution_order' => 1],
            ],
        ]))->assertStatus(201);
    }

    // =========================================================================
    // GROUP 5 — Sort / execution order validation
    // =========================================================================

    public function test_sub_variable_used_before_defined_in_order_fails(): void
    {
        // FinalAmount at order 0 tries to use BaseCommission which is at order 1
        $this->postFormula($this->validPayload([
            'expression' => 'FinalAmount',
            'variables'  => [
                ['name' => 'FinalAmount',    'expression' => 'BaseCommission * RiskScore', 'execution_order' => 0],
                ['name' => 'BaseCommission', 'expression' => 'AnnualUsage * 0.05',         'execution_order' => 1],
            ],
        ]))->assertStatus(422)
           ->assertJsonValidationErrors(['variables']);
    }

    public function test_three_level_chain_wrong_order_fails(): void
    {
        // All three depend on each other but order is reversed
        $this->postFormula($this->validPayload([
            'expression' => 'Final',
            'variables'  => [
                ['name' => 'Final',  'expression' => 'Mid * 1.1',              'execution_order' => 0],
                ['name' => 'Mid',    'expression' => 'Base * 1.2',             'execution_order' => 1],
                ['name' => 'Base',   'expression' => 'AnnualUsage * 0.05',     'execution_order' => 2],
            ],
        ]))->assertStatus(422)
           ->assertJsonValidationErrors(['variables']);
    }

    public function test_three_level_chain_correct_order_passes(): void
    {
        $this->postFormula($this->validPayload([
            'expression' => 'Final',
            'variables'  => [
                ['name' => 'Base',  'expression' => 'AnnualUsage * 0.05', 'execution_order' => 0],
                ['name' => 'Mid',   'expression' => 'Base * 1.2',         'execution_order' => 1],
                ['name' => 'Final', 'expression' => 'Mid * 1.1',          'execution_order' => 2],
            ],
        ]))->assertStatus(201);
    }

    public function test_base_variable_usable_at_any_order_passes(): void
    {
        // Base vars are always available regardless of execution_order
        $this->postFormula($this->validPayload([
            'expression' => 'A + B',
            'variables'  => [
                ['name' => 'B', 'expression' => 'ContractValue * 0.02', 'execution_order' => 5],
                ['name' => 'A', 'expression' => 'AnnualUsage * 0.05',   'execution_order' => 10],
            ],
        ]))->assertStatus(201);
    }

    // =========================================================================
    // GROUP 6 — Circular reference
    // =========================================================================

    public function test_circular_reference_between_two_sub_variables_fails(): void
    {
        // A uses B, B uses A
        $this->postFormula($this->validPayload([
            'expression' => 'A',
            'variables'  => [
                ['name' => 'A', 'expression' => 'B * 2', 'execution_order' => 0],
                ['name' => 'B', 'expression' => 'A * 2', 'execution_order' => 1],
            ],
        ]))->assertStatus(422)
           ->assertJsonValidationErrors(['variables']);
    }

    public function test_circular_reference_three_variables_fails(): void
    {
        // A → B → C → A
        $this->postFormula($this->validPayload([
            'expression' => 'A',
            'variables'  => [
                ['name' => 'A', 'expression' => 'B * 1',             'execution_order' => 0],
                ['name' => 'B', 'expression' => 'C * 1',             'execution_order' => 1],
                ['name' => 'C', 'expression' => 'A * 1',             'execution_order' => 2],
            ],
        ]))->assertStatus(422)
           ->assertJsonValidationErrors(['variables']);
    }

    // =========================================================================
    // GROUP 7 — Successful saves
    // =========================================================================

    public function test_valid_formula_without_sub_variables_saves(): void
    {
        $response = $this->postFormula($this->validPayload([
            'version'    => '1.0',
            'expression' => 'AnnualUsage * 0.05',
        ]));

        $response->assertStatus(201)
                 ->assertJsonFragment(['version' => '1.0']);

        $this->assertDatabaseHas('formulas', ['version' => '1.0', 'expression' => 'AnnualUsage * 0.05']);
    }

    public function test_valid_formula_with_sub_variables_saves(): void
    {
        $response = $this->postFormula([
            'version'    => '2.0',
            'expression' => '(BaseCommission + Bonus) * RiskScore',
            'variables'  => [
                ['name' => 'BaseCommission', 'expression' => 'AnnualUsage * 0.05',   'execution_order' => 0],
                ['name' => 'Bonus',          'expression' => 'ContractValue * 0.02', 'execution_order' => 1],
            ],
        ]);

        $response->assertStatus(201)
                 ->assertJsonFragment(['version' => '2.0']);

        $this->assertDatabaseHas('formulas', ['version' => '2.0']);
        $this->assertDatabaseHas('dependent_variables', ['name' => 'BaseCommission']);
        $this->assertDatabaseHas('dependent_variables', ['name' => 'Bonus']);
    }

    public function test_saved_formula_is_inactive_by_default(): void
    {
        $this->postFormula($this->validPayload(['version' => '3.0']))
             ->assertStatus(201)
             ->assertJsonFragment(['is_active' => false]);
    }

    public function test_formula_response_contains_dependent_variables(): void
    {
        $response = $this->postFormula([
            'version'    => '4.0',
            'expression' => 'Base',
            'variables'  => [
                ['name' => 'Base', 'expression' => 'AnnualUsage * 0.1', 'execution_order' => 0],
            ],
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'id', 'version', 'expression', 'is_active',
                     'dependent_variables' => [['id', 'name', 'expression', 'execution_order']],
                 ]);
    }

    public function test_valid_ternary_expression_passes(): void
    {
        // ExpressionLanguage supports ternary
        $this->postFormula($this->validPayload([
            'expression' => 'RiskScore > 0.5 ? AnnualUsage * 0.1 : AnnualUsage * 0.05',
        ]))->assertStatus(201);
    }

    public function test_valid_nested_expression_passes(): void
    {
        // Nested arithmetic — no functions needed
        $this->postFormula($this->validPayload([
            'expression' => '((AnnualUsage * 0.05) + (ContractValue * 0.02)) / ContractLength',
        ]))->assertStatus(201);
    }

    public function test_expression_with_constant_only_passes(): void
    {
        $this->postFormula($this->validPayload(['expression' => '500']))
             ->assertStatus(201);
    }

    public function test_sub_variables_execution_order_defaults_to_index(): void
    {
        // When execution_order is omitted, it falls back to array index
        $this->postFormula([
            'version'    => '5.0',
            'expression' => 'Base',
            'variables'  => [
                ['name' => 'Base', 'expression' => 'AnnualUsage * 0.1'],
            ],
        ])->assertStatus(201);

        $this->assertDatabaseHas('dependent_variables', [
            'name'            => 'Base',
            'execution_order' => 0,
        ]);
    }
}
