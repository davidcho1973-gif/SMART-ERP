<?php

namespace Tests\Feature;

use App\Livewire\WorkforceApp;
use App\Models\Employee;
use App\Models\Punch;
use App\Models\User;
use App\Support\Geo;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Hardening of the worker clock: auth gate, terminated block, coordinate sanitizing. */
class AttendanceSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WorkforceSeeder::class);
    }

    public function test_unauthenticated_visitor_cannot_clock_anyone(): void
    {
        config(['workforce.demo' => false]);   // real traffic, nobody signed in
        $today = now()->format('Y-m-d');

        Livewire::test(WorkforceApp::class)
            ->call('doClock', 33.7838, -112.15, 8.0);

        // the old 106-fallback would have punched Carlos — it must not now
        $this->assertNull(Punch::where('employee_id', 106)->where('work_date', $today)->first());
        $this->assertSame(0, Punch::whereDate('work_date', $today)->count());
    }

    public function test_terminated_employee_cannot_clock(): void
    {
        config(['workforce.demo' => false]);
        // employee 117 (Antonio) is seeded terminated; give them a linked account
        Employee::whereKey(117)->update(['emp' => 'terminated', 'email' => 'antonio@nahshon.io']);
        $u = User::create([
            'name' => 'Antonio', 'email' => 'antonio@nahshon.io',
            'password' => bcrypt('x'), 'access' => 'worker', 'employee_id' => 117,
        ]);

        Livewire::actingAs($u)->test(WorkforceApp::class)
            ->call('doClock', 33.7838, -112.15, 8.0);

        $this->assertNull(Punch::where('employee_id', 117)->where('work_date', now()->format('Y-m-d'))->first());
    }

    public function test_authenticated_worker_still_clocks_in(): void
    {
        config(['workforce.demo' => false]);
        $u = User::create([
            'name' => 'Carlos', 'email' => 'cmartinez@nahshon.io',
            'password' => bcrypt('x'), 'access' => 'worker', 'employee_id' => 106,
        ]);

        Livewire::actingAs($u)->test(WorkforceApp::class)
            ->call('doClock', 33.7838, -112.15, 8.0)
            ->assertSet('clock', 'in');

        $p = Punch::where('employee_id', 106)->where('work_date', now()->format('Y-m-d'))->first();
        $this->assertNotNull($p->in_min);
    }

    public function test_coordinate_sanitizer_rejects_garbage_and_keeps_valid(): void
    {
        $this->assertNull(Geo::coords(null, null, null));
        $this->assertNull(Geo::coords(999, 0, 5));       // out-of-range latitude
        $this->assertNull(Geo::coords(33.7, -999, 5));   // out-of-range longitude

        $ok = Geo::coords(33.7838, -112.15, 8.0);
        $this->assertSame(33.7838, $ok['lat']);
        $this->assertSame(8.0, $ok['acc']);

        $this->assertNull(Geo::coords(33.7, -112.1, -5)['acc']); // negative accuracy dropped
    }

    public function test_coarse_gps_is_not_trusted_as_on_site(): void
    {
        config(['workforce.demo' => false]);
        \App\Models\Site::whereKey('s1')->update(['lat' => 33.7838, 'lng' => -112.15, 'radius_m' => 150]);
        $u = User::create([
            'name' => 'Carlos', 'email' => 'cmartinez@nahshon.io',
            'password' => bcrypt('x'), 'access' => 'worker', 'employee_id' => 106,
        ]);

        // exact site coords but a 900m accuracy — inside the radius, yet unverifiable
        Livewire::actingAs($u)->test(WorkforceApp::class)
            ->call('doClock', 33.7838, -112.15, 900);

        $p = Punch::where('employee_id', 106)->where('work_date', now()->format('Y-m-d'))->first();
        $this->assertNull($p->in_geo_ok);   // not marked verified on such a coarse fix
    }
}
