<?php

namespace Tests\Feature;

use App\Livewire\EquipScan;
use App\Models\Equipment;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EquipScanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['workforce.demo' => false]);
        $this->seed(WorkforceSeeder::class);
        $this->seed(UserSeeder::class);
    }

    private function admin(): User
    {
        return User::where('access', 'admin')->first();
    }

    public function test_unknown_token_shows_not_found(): void
    {
        Livewire::actingAs($this->admin())
            ->test(EquipScan::class, ['token' => 'NOPE9999'])
            ->assertSee('Equipment not found');
    }

    public function test_owner_can_checkout_and_checkin_via_scan(): void
    {
        $site = Site::query()->value('id');
        $e = Equipment::create(['name' => 'Scan Lift', 'acquisition' => 'rented', 'qr_token' => 'SCANQR01', 'status' => 'available', 'site_id' => $site]);

        $c = Livewire::actingAs($this->admin())->test(EquipScan::class, ['token' => 'SCANQR01']);
        $c->assertSee('Scan Lift');

        $c->call('checkout');
        $this->assertSame('out', $e->fresh()->status);
        $this->assertSame(1, $e->events()->where('type', 'checkout')->where('note', 'QR')->count());

        $c->call('checkin');
        $this->assertSame('available', $e->fresh()->status);
        $this->assertNull($e->fresh()->holder_id);
        $this->assertSame(1, $e->events()->where('type', 'checkin')->where('note', 'QR')->count());
    }

    public function test_worker_can_view_but_not_move(): void
    {
        $e = Equipment::create(['name' => 'View Only', 'acquisition' => 'owned', 'qr_token' => 'SCANQR02', 'status' => 'available']);
        $worker = User::where('email', 'cmartinez@nahshon.io')->first();

        Livewire::actingAs($worker)->test(EquipScan::class, ['token' => 'SCANQR02'])
            ->call('checkout');

        // worker has no equipment.checkout (and is not a crew lead) → no state change
        $this->assertSame('available', $e->fresh()->status);
    }
}
