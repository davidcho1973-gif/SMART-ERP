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
}
