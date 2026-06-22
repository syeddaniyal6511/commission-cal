<?php

namespace Tests\Unit;

use App\Models\Contract;
use App\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractServiceTest extends TestCase
{
    use RefreshDatabase;

    private ContractService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ContractService();
    }

    // ── paginate ──────────────────────────────────────────────────────────────

    public function test_paginate_returns_all_contracts_without_search(): void
    {
        Contract::factory()->count(3)->create();

        $result = $this->service->paginate();

        $this->assertEquals(3, $result->total());
    }

    public function test_paginate_filters_by_contract_no(): void
    {
        Contract::factory()->create(['contract_no' => 'CON-000001']);
        Contract::factory()->create(['contract_no' => 'CON-000002']);

        $result = $this->service->paginate('000001');

        $this->assertEquals(1, $result->total());
        $this->assertEquals('CON-000001', $result->items()[0]->contract_no);
    }

    public function test_paginate_returns_empty_when_no_match(): void
    {
        Contract::factory()->count(3)->create();

        $result = $this->service->paginate('NOMATCH');

        $this->assertEquals(0, $result->total());
    }

    public function test_paginate_respects_per_page_parameter(): void
    {
        Contract::factory()->count(10)->create();

        $result = $this->service->paginate('', 3);

        $this->assertCount(3, $result->items());
        $this->assertEquals(10, $result->total());
    }

    public function test_paginate_returns_latest_first(): void
    {
        $this->travelTo(now()->subSeconds(5));
        Contract::factory()->create(['contract_no' => 'CON-FIRST']);

        $this->travelBack();
        $second = Contract::factory()->create(['contract_no' => 'CON-SECOND']);

        $result = $this->service->paginate();

        $this->assertEquals($second->id, $result->items()[0]->id);
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_and_returns_contract(): void
    {
        $contract = $this->service->store([
            'contract_no'     => 'CON-999',
            'annual_usage'    => 12000.0,
            'contract_value'  => 50000.0,
            'contract_length' => 24,
            'risk_score'      => 30.0,
        ]);

        $this->assertInstanceOf(Contract::class, $contract);
        $this->assertDatabaseHas('contracts', ['contract_no' => 'CON-999']);
    }

    public function test_store_persists_all_fields(): void
    {
        $this->service->store([
            'contract_no'     => 'CON-001',
            'annual_usage'    => 5000.0,
            'contract_value'  => 20000.0,
            'contract_length' => 12,
            'risk_score'      => 15.5,
        ]);

        $this->assertDatabaseHas('contracts', [
            'contract_no'     => 'CON-001',
            'annual_usage'    => 5000.0,
            'contract_value'  => 20000.0,
            'contract_length' => 12,
            'risk_score'      => 15.5,
        ]);
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function test_update_modifies_contract_fields(): void
    {
        $contract = Contract::factory()->create(['annual_usage' => 1000.0]);

        $updated = $this->service->update($contract, ['annual_usage' => 9999.0]);

        $this->assertEquals(9999.0, $updated->annual_usage);
        $this->assertDatabaseHas('contracts', ['id' => $contract->id, 'annual_usage' => 9999.0]);
    }

    public function test_update_returns_refreshed_contract(): void
    {
        $contract = Contract::factory()->create(['risk_score' => 10.0]);

        $result = $this->service->update($contract, ['risk_score' => 55.0]);

        $this->assertInstanceOf(Contract::class, $result);
        $this->assertEquals(55.0, $result->risk_score);
    }

    public function test_update_does_not_affect_other_contracts(): void
    {
        $a = Contract::factory()->create(['contract_no' => 'CON-A', 'annual_usage' => 1000.0]);
        $b = Contract::factory()->create(['contract_no' => 'CON-B', 'annual_usage' => 2000.0]);

        $this->service->update($a, ['annual_usage' => 5000.0]);

        $this->assertEquals(2000.0, Contract::find($b->id)->annual_usage);
    }

    // ── destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_deletes_contract(): void
    {
        $contract = Contract::factory()->create();

        $this->service->destroy($contract);

        $this->assertDatabaseMissing('contracts', ['id' => $contract->id]);
    }

    public function test_destroy_only_removes_targeted_contract(): void
    {
        $a = Contract::factory()->create();
        $b = Contract::factory()->create();

        $this->service->destroy($a);

        $this->assertDatabaseHas('contracts', ['id' => $b->id]);
    }
}
