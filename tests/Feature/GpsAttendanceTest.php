<?php

namespace Tests\Feature;

use App\Livewire\ScanClock;
use App\Livewire\WorkforceApp;
use App\Models\Employee;
use App\Models\Punch;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GpsAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['workforce.demo' => true]);
        $this->seed(WorkforceSeeder::class);
        // ensure the seeded site s1 has a geofence regardless of seeder history
        Site::whereKey('s1')->update(['lat' => 33.7838, 'lng' => -112.15, 'radius_m' => 150]);
    }

    public function test_worker_clock_in_within_radius_is_marked_geo_ok(): void
    {
        $today = now()->format('Y-m-d');

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'worker')
            ->call('doClock', 33.7838, -112.15, 8.0); // on site

        $p = Punch::where('employee_id', 106)->where('work_date', $today)->first();
        $this->assertNotNull($p);
        $this->assertTrue($p->in_geo_ok);
        $this->assertSame(33.7838, (float) $p->in_lat);
        $this->assertSame(-112.15, (float) $p->in_lng);
        $this->assertSame(8.0, (float) $p->in_acc);
    }

    public function test_worker_clock_in_outside_radius_is_recorded_but_flagged(): void
    {
        $today = now()->format('Y-m-d');

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'worker')
            ->call('doClock', 33.7963, -112.15, 12.0); // ~1.4km away

        $p = Punch::where('employee_id', 106)->where('work_date', $today)->first();
        $this->assertNotNull($p->in_min);       // still recorded, not blocked
        $this->assertFalse($p->in_geo_ok);
        $this->assertSame('present', Employee::find(106)->status);
    }

    public function test_worker_clock_without_coords_still_works_and_leaves_geo_null(): void
    {
        $today = now()->format('Y-m-d');

        // permission denied → coords arrive as null; attendance must still be recorded
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'worker')
            ->call('doClock', null, null, null);

        $p = Punch::where('employee_id', 106)->where('work_date', $today)->first();
        $this->assertNotNull($p->in_min);
        $this->assertNull($p->in_geo_ok);
        $this->assertNull($p->in_lat);
    }

    public function test_worker_clock_out_records_geo_on_the_out_leg(): void
    {
        $today = now()->format('Y-m-d');

        Livewire::test(WorkforceApp::class)->call('demo', 'worker')->call('doClock', 33.7838, -112.15, 8.0);
        Livewire::test(WorkforceApp::class)->set('clock', 'in')->call('doClock', 33.7963, -112.15, 9.0); // out, off-site

        $p = Punch::where('employee_id', 106)->where('work_date', $today)->first();
        $this->assertTrue($p->in_geo_ok);
        $this->assertFalse($p->out_geo_ok);
        $this->assertSame(33.7963, (float) $p->out_lat);
    }

    public function test_scan_clock_records_geo_verification(): void
    {
        $this->seed(UserSeeder::class);
        $worker = User::where('email', 'cmartinez@nahshon.io')->first();

        Livewire::actingAs($worker)
            ->test(ScanClock::class, ['team' => 't1'])
            ->call('doClock', 33.7838, -112.15, 6.0)
            ->assertSet('clock', 'in');

        $p = Punch::where('employee_id', 106)->where('work_date', now()->format('Y-m-d'))->first();
        $this->assertSame('qr', $p->source);
        $this->assertTrue($p->in_geo_ok);
        $this->assertSame(6.0, (float) $p->in_acc);
    }

    public function test_scan_clock_without_geolocation_still_clocks_in(): void
    {
        $this->seed(UserSeeder::class);
        $worker = User::where('email', 'cmartinez@nahshon.io')->first();

        Livewire::actingAs($worker)
            ->test(ScanClock::class, ['team' => 't1'])
            ->call('doClock') // no coords passed at all
            ->assertSet('clock', 'in');

        $p = Punch::where('employee_id', 106)->where('work_date', now()->format('Y-m-d'))->first();
        $this->assertNotNull($p->in_min);
        $this->assertNull($p->in_geo_ok);
    }

    public function test_admin_can_set_site_location_and_radius(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('openSiteModal', 's2')
            ->assertSet('siteModal', 's2')
            ->call('setSiteCurrentLocation', 33.3000, -111.9000)
            ->assertSet('siteLat', '33.3')
            ->assertSet('siteLng', '-111.9')
            ->set('siteRadius', '200')
            ->call('saveSiteGeo')
            ->assertSet('siteModal', null);

        $s = Site::find('s2');
        $this->assertSame(33.3, (float) $s->lat);
        $this->assertSame(-111.9, (float) $s->lng);
        $this->assertSame(200, $s->radius_m);
    }

    public function test_open_site_modal_defaults_radius_when_unset(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('openSiteModal', 's3')          // s3 has no geofence
            ->assertSet('siteLat', '')
            ->assertSet('siteRadius', '150');       // Geo::DEFAULT_RADIUS_M
    }

    public function test_attendance_records_show_offsite_badge(): void
    {
        Punch::create([
            'employee_id' => 106, 'work_date' => '2026-06-25',
            'in_min' => 365, 'out_min' => 900, 'no_lunch' => false, 'source' => 'qr',
            'in_lat' => 33.7963, 'in_lng' => -112.15, 'in_acc' => 15, 'in_geo_ok' => false,
            'out_lat' => 33.7838, 'out_lng' => -112.15, 'out_acc' => 10, 'out_geo_ok' => true,
        ]);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'attendance')
            ->set('attView', 'records')
            ->set('attDate', '2026-06-25')
            ->assertSee('Carlos Martínez')
            ->assertSee('Off-site');   // geo_ok=false badge
    }

    public function test_site_managers_can_reach_site_geofence_editor(): void
    {
        // the projects screen (which hosts the geofence editor) is available to managers
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'manager')
            ->call('go', 'projects')
            ->assertSee('TSMC Fab 21');
    }
}
