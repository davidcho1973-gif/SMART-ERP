<?php

namespace Tests\Feature;

use App\Livewire\ScanClock;
use App\Livewire\WorkforceApp;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Punch;
use App\Models\Team;
use App\Models\User;
use App\Support\Timesheet;
use Database\Seeders\UserSeeder;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Day-by-day crew assignment: the punch snapshots the crew at clock time, and
 * scanning another crew's QR reassigns today's crew with no admin work.
 * Seeded facts: worker 106 (Carlos) is on crew t1.
 */
class TeamAssignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['workforce.demo' => true]);
        $this->seed(WorkforceSeeder::class);
    }

    public function test_clock_in_stamps_crew_company_site_on_the_punch(): void
    {
        Livewire::test(WorkforceApp::class)->call('demo', 'worker')->call('doClock');

        $p = Punch::where('employee_id', 106)->where('work_date', now()->format('Y-m-d'))->first();
        $e = Employee::find(106);
        $this->assertSame($e->team_id, $p->team_id);
        $this->assertSame($e->company_id, $p->company_id);
        $this->assertSame($e->site_id, $p->site_id);
    }

    public function test_scanning_another_crews_qr_reassigns_todays_crew(): void
    {
        config(['workforce.demo' => false]);
        $this->seed(UserSeeder::class);
        $worker = User::where('email', 'cmartinez@nahshon.io')->first();
        $this->assertSame('t1', Employee::find(106)->team_id);   // home crew

        // Carlos scans crew t2's posted QR and clocks in there
        Livewire::actingAs($worker)
            ->test(ScanClock::class, ['team' => 't2'])
            ->call('doClock')
            ->assertSet('clock', 'in');

        $e = Employee::find(106)->fresh();
        $this->assertSame('t2', $e->team_id);                                   // today's crew updated
        $this->assertSame(Team::find('t2')->company_id, $e->company_id);        // company follows the crew

        $p = Punch::where('employee_id', 106)->where('work_date', now()->format('Y-m-d'))->first();
        $this->assertSame('t2', $p->team_id);                                   // stamped on the day
    }

    public function test_worker_home_qr_assign_moves_crew_and_clocks_in(): void
    {
        // in-app scanner returns the QR text (a /scan/{team} URL)
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'worker')
            ->call('assignTeamByQr', url('/scan/t2'), 33.7838, -112.15, 8.0)
            ->assertSet('clock', 'in');

        $e = Employee::find(106);
        $this->assertSame('t2', $e->team_id);
        $p = Punch::where('employee_id', 106)->where('work_date', now()->format('Y-m-d'))->first();
        $this->assertNotNull($p->in_min);      // one scan = assign + clock in
        $this->assertSame('t2', $p->team_id);
    }

    public function test_bad_qr_changes_nothing(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'worker')
            ->call('assignTeamByQr', 'https://evil.example.com/whatever');

        $this->assertSame('t1', Employee::find(106)->team_id);
        $this->assertNull(Punch::where('employee_id', 106)->where('work_date', now()->format('Y-m-d'))->first());
    }

    public function test_timesheet_shows_the_crew_at_clock_time_not_the_current_one(): void
    {
        $today = now()->format('Y-m-d');
        // clocked in while on t1 (snapshot t1)
        Livewire::test(WorkforceApp::class)->call('demo', 'worker')->call('doClock');
        // later someone moves Carlos to t5
        Employee::whereKey(106)->update(['team_id' => 't5', 'company_id' => Team::find('t5')->company_id]);

        $ts = Timesheet::forDate($today, 'all', 'en');
        $row = collect($ts['rows'])->firstWhere('id', 106);
        $this->assertSame(Team::find('t1')->name, $row['team']);   // the day still reads t1
        $this->assertSame(Company::find(Team::find('t1')->company_id)->name, $row['company']);
    }
}
