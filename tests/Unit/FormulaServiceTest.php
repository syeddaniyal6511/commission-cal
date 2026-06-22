<?php

namespace Tests\Unit;

use App\Models\Formula;
use App\Services\FormulaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FormulaServiceTest extends TestCase
{
    use RefreshDatabase;

    private FormulaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FormulaService();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeFormula(array $overrides = []): Formula
    {
        return Formula::create(array_merge([
            'version'    => '1.0',
            'expression' => 'AnnualUsage * 0.05',
            'is_active'  => false,
        ], $overrides));
    }

    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'version'    => '1.0',
            'expression' => 'AnnualUsage * 0.05',
            'variables'  => [],
        ], $overrides);
    }

    // ── index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_all_formulas(): void
    {
        $this->makeFormula(['version' => '1.0']);
        $this->makeFormula(['version' => '2.0']);

        $result = $this->service->index();

        $this->assertCount(2, $result);
    }

    public function test_index_orders_active_formula_first(): void
    {
        $this->makeFormula(['version' => '1.0', 'is_active' => false]);
        $this->makeFormula(['version' => '2.0', 'is_active' => true]);

        $result = $this->service->index();

        $this->assertEquals('2.0', $result->first()->version);
    }

    public function test_index_loads_dependent_variables(): void
    {
        $formula = $this->makeFormula(['expression' => 'Base * 2']);
        $formula->dependentVariables()->create([
            'name'            => 'Base',
            'expression'      => 'AnnualUsage * 0.05',
            'execution_order' => 0,
        ]);

        $result = $this->service->index();

        $this->assertTrue($result->first()->relationLoaded('dependentVariables'));
        $this->assertCount(1, $result->first()->dependentVariables);
    }

    public function test_index_returns_empty_collection_when_no_formulas(): void
    {
        $result = $this->service->index();

        $this->assertCount(0, $result);
    }

    // ── activate ──────────────────────────────────────────────────────────────

    public function test_activate_sets_formula_as_active(): void
    {
        $formula = $this->makeFormula(['is_active' => false]);

        $this->service->activate($formula);

        $this->assertTrue(Formula::find($formula->id)->is_active);
    }

    public function test_activate_deactivates_all_other_formulas(): void
    {
        $a = $this->makeFormula(['version' => '1.0', 'is_active' => true]);
        $b = $this->makeFormula(['version' => '2.0', 'is_active' => false]);

        $this->service->activate($b);

        $this->assertFalse(Formula::find($a->id)->is_active);
        $this->assertTrue(Formula::find($b->id)->is_active);
    }

    public function test_activate_returns_formula_with_dependent_variables(): void
    {
        $formula = $this->makeFormula();
        $formula->dependentVariables()->create([
            'name'            => 'Base',
            'expression'      => 'AnnualUsage * 0.05',
            'execution_order' => 0,
        ]);

        $result = $this->service->activate($formula);

        $this->assertTrue($result->relationLoaded('dependentVariables'));
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_formula_without_variables(): void
    {
        $formula = $this->service->store($this->storePayload([
            'version'    => '1.0',
            'expression' => 'AnnualUsage * 0.05',
        ]));

        $this->assertDatabaseHas('formulas', [
            'version'    => '1.0',
            'expression' => 'AnnualUsage * 0.05',
        ]);
        $this->assertFalse($formula->is_active);
    }

    public function test_store_creates_dependent_variables_in_order(): void
    {
        $formula = $this->service->store($this->storePayload([
            'version'    => '2.0',
            'expression' => 'Base + Bonus',
            'variables'  => [
                ['name' => 'Base',  'expression' => 'AnnualUsage * 0.05',   'execution_order' => 0],
                ['name' => 'Bonus', 'expression' => 'ContractLength * 100',  'execution_order' => 1],
            ],
        ]));

        $this->assertCount(2, $formula->dependentVariables);
        $this->assertEquals('Base',  $formula->dependentVariables[0]->name);
        $this->assertEquals('Bonus', $formula->dependentVariables[1]->name);
    }

    public function test_store_throws_when_sub_variable_references_undefined_name(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->store($this->storePayload([
            'variables' => [
                ['name' => 'X', 'expression' => 'Undefined * 2', 'execution_order' => 0],
            ],
        ]));
    }

    public function test_store_throws_on_circular_dependency(): void
    {
        $this->expectException(ValidationException::class);

        // A references B which is defined after it — execution_order ensures this is caught
        // Circular: A = B * 2, B = A * 2
        $this->service->store($this->storePayload([
            'expression' => 'A + B',
            'variables'  => [
                ['name' => 'A', 'expression' => 'B * 2', 'execution_order' => 0],
                ['name' => 'B', 'expression' => 'A * 2', 'execution_order' => 1],
            ],
        ]));
    }

    public function test_store_throws_on_invalid_expression_syntax(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->store($this->storePayload([
            'expression' => 'AnnualUsage ** / 0.05',
        ]));
    }

    public function test_store_throws_when_main_expression_uses_unknown_variable(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->store($this->storePayload([
            'expression' => 'NonExistentVar * 0.05',
        ]));
    }

    public function test_store_does_not_persist_on_validation_failure(): void
    {
        try {
            $this->service->store($this->storePayload([
                'expression' => 'BadVar * 0.05',
            ]));
        } catch (ValidationException) {
        }

        $this->assertDatabaseCount('formulas', 0);
    }

    public function test_store_sub_variable_can_reference_earlier_sub_variable(): void
    {
        $formula = $this->service->store($this->storePayload([
            'expression' => 'Final',
            'variables'  => [
                ['name' => 'Base',  'expression' => 'AnnualUsage * 0.05',  'execution_order' => 0],
                ['name' => 'Final', 'expression' => 'Base + ContractValue', 'execution_order' => 1],
            ],
        ]));

        $this->assertCount(2, $formula->dependentVariables);
    }
}
