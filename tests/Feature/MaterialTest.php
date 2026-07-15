<?php

namespace Tests\Feature;

use App\Livewire\WorkforceApp;
use App\Models\MaterialBatch;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MaterialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['workforce.demo' => true]);
        $this->seed(WorkforceSeeder::class);
    }

    private function siteId(): string
    {
        return Site::query()->value('id');
    }

    public function test_materials_tab_is_live_and_renders(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'accounting')
            ->call('setAcctTab', 'materials')
            ->assertSet('acctTab', 'materials')
            ->assertSee('Materials');
    }

    public function test_submitting_a_delivery_batch_creates_lines(): void
    {
        $site = $this->siteId();

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'accounting')
            ->call('setAcctTab', 'materials')
            ->call('openMatBatch', 'delivery')
            ->set('matSite', $site)
            ->set('matVendor', 'Ferguson')
            ->set('matDate', now()->format('Y-m-d'))
            ->set('matLines', [
                ['name' => '3/4in Copper Pipe', 'unit' => 'm', 'qty' => '20', 'unitPrice' => '8.5'],
                ['name' => 'Elbow 3/4in', 'unit' => 'ea', 'qty' => '15', 'unitPrice' => '1.2'],
            ])
            ->call('submitMaterials')
            ->assertSet('matModal', null);

        $b = MaterialBatch::with('lines')->first();
        $this->assertSame('delivery', $b->kind);
        $this->assertSame('pending', $b->status);
        $this->assertSame(2, $b->lines->count());
        // 20×8.5 + 15×1.2 = 170 + 18 = 188
        $this->assertEqualsWithDelta(188.0, $b->total(), 0.01);
    }

    public function test_blank_lines_are_dropped_and_empty_batch_is_rejected(): void
    {
        $site = $this->siteId();

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'accounting')
            ->call('openMatBatch', 'manual')
            ->set('matSite', $site)
            ->set('matLines', [['name' => '', 'unit' => 'ea', 'qty' => '0', 'unitPrice' => '0']])
            ->call('submitMaterials');

        $this->assertSame(0, MaterialBatch::count());
    }

    public function test_approved_delivery_feeds_material_pillar_but_opening_does_not(): void
    {
        $site = $this->siteId();
        $today = now()->format('Y-m-d');

        // approved delivery (counts) — $500
        $d = MaterialBatch::create(['site_id' => $site, 'kind' => 'delivery', 'status' => 'approved', 'spent_on' => $today]);
        $d->lines()->create(['name' => 'Wire', 'unit' => 'm', 'qty' => 100, 'unit_price' => 5, 'amount' => 500]);

        // approved opening (must NOT count) — quantity only
        $o = MaterialBatch::create(['site_id' => $site, 'kind' => 'opening', 'status' => 'approved', 'spent_on' => $today]);
        $o->lines()->create(['name' => 'Pipe', 'unit' => 'm', 'qty' => 300, 'unit_price' => 0, 'amount' => 0]);

        // pending delivery (must NOT count) — $999
        $p = MaterialBatch::create(['site_id' => $site, 'kind' => 'delivery', 'status' => 'pending', 'spent_on' => $today]);
        $p->lines()->create(['name' => 'Fittings', 'unit' => 'ea', 'qty' => 1, 'unit_price' => 999, 'amount' => 999]);

        $A = Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'accounting')
            ->viewData('accounting');

        $this->assertSame(500.0, $A['totalMaterial']);
        $pillar = collect($A['pillars'])->firstWhere('key', 'material');
        $this->assertTrue($pillar['live']);
        $this->assertSame(500.0, $pillar['amount']);
    }

    public function test_approve_and_reject_transitions(): void
    {
        $site = $this->siteId();
        $a = MaterialBatch::create(['site_id' => $site, 'kind' => 'delivery', 'status' => 'pending', 'spent_on' => now()->format('Y-m-d')]);
        $a->lines()->create(['name' => 'X', 'unit' => 'ea', 'qty' => 1, 'unit_price' => 10, 'amount' => 10]);
        $b = MaterialBatch::create(['site_id' => $site, 'kind' => 'manual', 'status' => 'pending', 'spent_on' => now()->format('Y-m-d')]);
        $b->lines()->create(['name' => 'Y', 'unit' => 'ea', 'qty' => 1, 'unit_price' => 10, 'amount' => 10]);

        $c = Livewire::test(WorkforceApp::class)->call('demo', 'admin')->call('go', 'accounting')->call('setAcctTab', 'materials');
        $c->call('approveMaterial', $a->id);
        $c->call('askRejectMaterial', $b->id)->set('matRejectNote', 'wrong site')->call('rejectMaterial', $b->id);

        $this->assertSame('approved', $a->fresh()->status);
        $this->assertSame('rejected', $b->fresh()->status);
        $this->assertSame('wrong site', $b->fresh()->reject_reason);
    }

    public function test_manager_cannot_decide_but_can_submit(): void
    {
        config(['workforce.demo' => false]);
        $this->seed(UserSeeder::class);
        $site = $this->siteId();
        $b = MaterialBatch::create(['site_id' => $site, 'kind' => 'delivery', 'status' => 'pending', 'spent_on' => now()->format('Y-m-d')]);
        $worker = User::where('email', 'cmartinez@nahshon.io')->first(); // worker: no materials.decide

        Livewire::actingAs($worker)->test(WorkforceApp::class)
            ->call('approveMaterial', $b->id);

        $this->assertSame('pending', $b->fresh()->status); // blocked
    }
}
