<?php

namespace Tests\Feature;

use App\Livewire\WorkforceApp;
use App\Models\Equipment;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class EquipmentTest extends TestCase
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

    public function test_equipment_section_renders(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'accounting')
            ->call('setAcctTab', 'materials')
            ->call('setMatSection', 'equipment')
            ->assertSet('matSection', 'equipment')
            ->assertSee('Register equipment');
    }

    public function test_registering_a_rented_unit_creates_a_row_with_qr_token(): void
    {
        $site = $this->siteId();

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'accounting')
            ->call('setMatSection', 'equipment')
            ->call('openEquipRegister')
            ->set('eqAcq', 'rented')
            ->set('eqName', 'Genie S-45 Boom Lift')
            ->set('eqType', 'Boom lift')
            ->set('eqSite', $site)
            ->set('eqVendor', 'United Rentals')
            ->set('eqRate', '320')
            ->set('eqRateUnit', 'week')
            ->set('eqStart', now()->format('Y-m-d'))
            ->set('eqEnd', now()->addDays(10)->format('Y-m-d'))
            ->call('submitEquip')
            ->assertSet('equipModal', null);

        $e = Equipment::first();
        $this->assertSame('Genie S-45 Boom Lift', $e->name);
        $this->assertSame('rented', $e->acquisition);
        $this->assertSame('available', $e->status);
        $this->assertSame(320.0, $e->rental_rate);
        $this->assertNotEmpty($e->qr_token);
    }

    public function test_registering_owned_asset_computes_book_value(): void
    {
        $site = $this->siteId();

        // bought 12 months ago for 24,000; 48-month life; 0 salvage → 1/4 depreciated
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'accounting')
            ->call('setMatSection', 'equipment')
            ->call('openEquipRegister')
            ->set('eqAcq', 'owned')
            ->set('eqName', 'Miller Welder')
            ->set('eqSite', $site)
            ->set('eqPurchaseDate', now()->subMonths(12)->format('Y-m-d'))
            ->set('eqPurchaseCost', '24000')
            ->set('eqLife', '48')
            ->set('eqSalvage', '0')
            ->call('submitEquip');

        $e = Equipment::first();
        $this->assertSame('owned', $e->acquisition);
        // 24000 − (24000/48 × 12) = 24000 − 6000 = 18000
        $this->assertEqualsWithDelta(18000.0, $e->bookValue(), 1.0);
        $this->assertEqualsWithDelta(500.0, $e->monthlyDepreciation(), 0.01);
    }

    public function test_checkout_and_checkin_transitions_log_events(): void
    {
        $site = $this->siteId();
        $e = Equipment::create(['name' => 'Compactor', 'acquisition' => 'rented', 'qr_token' => 'TESTQR01', 'status' => 'available']);

        $c = Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'accounting')
            ->call('setMatSection', 'equipment');

        $c->call('askCheckout', $e->id)
            ->set('coSite', $site)
            ->call('doCheckout', $e->id);

        $this->assertSame('out', $e->fresh()->status);
        $this->assertSame($site, $e->fresh()->site_id);
        $this->assertSame(1, $e->events()->where('type', 'checkout')->count());

        $c->call('equipStatus', $e->id, 'available');
        $this->assertSame('available', $e->fresh()->status);
        $this->assertNull($e->fresh()->holder_id);
        $this->assertSame(1, $e->events()->where('type', 'checkin')->count());
    }

    public function test_rented_return_countdown_flags_overdue(): void
    {
        $overdue = Equipment::create(['name' => 'Overdue Lift', 'acquisition' => 'rented', 'qr_token' => 'OD00001', 'status' => 'out',
            'rental_end' => Carbon::today()->subDays(2)]);
        $ok = Equipment::create(['name' => 'Future Lift', 'acquisition' => 'rented', 'qr_token' => 'OK00001', 'status' => 'out',
            'rental_end' => Carbon::today()->addDays(30)]);

        $this->assertSame(-2, $overdue->daysToReturn());
        $this->assertSame(30, $ok->daysToReturn());

        $A = Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'accounting')
            ->viewData('accounting');

        // both are within-or-past → only the overdue one is ≤7 days out
        $this->assertSame(1, $A['equipment']['dueSoon']);
    }

    public function test_worker_cannot_manage_but_can_checkout(): void
    {
        config(['workforce.demo' => false]);
        $this->seed(UserSeeder::class);
        $e = Equipment::create(['name' => 'Drill', 'acquisition' => 'owned', 'qr_token' => 'WRK0001', 'status' => 'available']);
        $worker = User::where('email', 'cmartinez@nahshon.io')->first();

        // worker has no equipment.manage → register is a no-op
        Livewire::actingAs($worker)->test(WorkforceApp::class)
            ->call('openEquipRegister')
            ->set('eqName', 'Sneaky')
            ->call('submitEquip');
        $this->assertSame(1, Equipment::count()); // nothing added
    }

    public function test_rent_accrual_is_per_day_over_the_month_overlap(): void
    {
        $e = new Equipment(['acquisition' => 'rented', 'rental_rate' => 100, 'rate_unit' => 'day', 'status' => 'out']);
        $e->rental_start = Carbon::parse('2026-06-01');
        $e->rental_end = Carbon::parse('2026-06-30');

        // full June (30 inclusive days) × $100/day = $3,000
        $this->assertSame(3000.0, $e->rentAccrual(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'), null));

        // weekly rate 700/wk → 100/day; 10 days into the month with asOf cap
        $w = new Equipment(['acquisition' => 'rented', 'rental_rate' => 700, 'rate_unit' => 'week', 'status' => 'out']);
        $w->rental_start = Carbon::parse('2026-06-01');
        $w->rental_end = Carbon::parse('2026-07-31');
        // asOf = Jun 10 → Jun 1..10 = 10 days × 100 = 1000
        $this->assertEqualsWithDelta(1000.0, $w->rentAccrual(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'), Carbon::parse('2026-06-10')), 0.01);
    }

    public function test_rented_equipment_feeds_the_equipment_pillar(): void
    {
        $site = $this->siteId();
        // active rental spanning the whole current month
        Equipment::create(['name' => 'Boom Lift', 'acquisition' => 'rented', 'qr_token' => 'PILLAR1', 'status' => 'out',
            'site_id' => $site, 'rental_rate' => 50, 'rate_unit' => 'day',
            'rental_start' => now()->startOfMonth()->subMonth()->format('Y-m-d'),
            'rental_end' => now()->endOfMonth()->addMonth()->format('Y-m-d')]);

        $A = Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'accounting')
            ->viewData('accounting');

        $pillar = collect($A['pillars'])->firstWhere('key', 'equipment');
        $this->assertTrue($pillar['live']);
        $this->assertGreaterThan(0, $A['totalEquipment']);
        $this->assertSame($A['totalEquipment'], $pillar['amount']);
    }

    public function test_idle_rental_surfaces_savings(): void
    {
        $site = $this->siteId();
        // rented, available (not deployed), still inside rental window → idle
        Equipment::create(['name' => 'Idle Generator', 'acquisition' => 'rented', 'qr_token' => 'IDLE001', 'status' => 'available',
            'site_id' => $site, 'rental_rate' => 90, 'rate_unit' => 'day',
            'rental_start' => now()->subDays(5)->format('Y-m-d'),
            'rental_end' => now()->addDays(20)->format('Y-m-d')]);

        $A = Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'accounting')
            ->viewData('accounting');

        $this->assertSame(1, $A['equipment']['idle']['count']);
        // 20 days left × $90/day = $1,800 projected saving
        $this->assertEqualsWithDelta(1800.0, $A['equipment']['idle']['saving'], 0.01);
    }

    public function test_invoice_integrates_progress_and_cost_into_margin(): void
    {
        $site = $this->siteId();
        // contract 100k, this month is the first progress snapshot at 10% → $10k billing
        \App\Models\Contract::create(['site_id' => $site, 'amount' => 100000]);
        \App\Models\ProgressSnapshot::create(['site_id' => $site, 'ym' => now()->format('Y-m'), 'pct' => 10]);
        // some approved cost this month: a $2k material batch
        $b = \App\Models\MaterialBatch::create(['site_id' => $site, 'kind' => 'delivery', 'status' => 'approved', 'spent_on' => now()->format('Y-m-d')]);
        $b->lines()->create(['name' => 'Pipe', 'unit' => 'm', 'qty' => 100, 'unit_price' => 20, 'amount' => 2000]);

        $A = Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'accounting')
            ->call('setAcctTab', 'invoice')
            ->viewData('accounting');

        $inv = $A['invoice'];
        $this->assertTrue($inv['hasAny']);
        $this->assertSame(10000.0, $inv['totThis']);         // 100k × 10%
        // margin = 10,000 billing − (labor + 2,000 material + …)
        $this->assertSame($inv['totThis'] - array_sum(array_map(fn ($r) => $r['monthCost'], $inv['rows'])), $inv['totMargin']);
    }
}
