<?php

namespace Tests\Feature;

use App\Livewire\WorkforceApp;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\Punch;
use App\Models\Team;
use App\Models\User;
use App\Support\Attendance;
use App\Support\Corrections;
use Database\Seeders\UserSeeder;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * P2 integrity: frozen shift snapshots, corrections superseding lead
 * adjustments, night-shift validation, and the lead's mobile approval flow.
 */
class PayrollIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['workforce.demo' => true]);
        $this->seed(WorkforceSeeder::class);
    }

    public function test_editing_a_shift_later_does_not_rewrite_already_earned_pay(): void
    {
        Team::whereKey('t1')->update(['shift_in' => 300, 'shift_out' => 840]); // 5:00–2:00
        // punch stamped with TODAY's shift (as clock-in would do)
        $p = new Punch([
            'employee_id' => 106, 'work_date' => '2026-07-01', 'team_id' => 't1',
            'in_min' => 290, 'out_min' => 850, 'no_lunch' => false, 'source' => 'qr',
        ]);
        $p->stampShiftSnap();
        $p->save();
        $this->assertEqualsWithDelta(8.0, Attendance::paidHours($p), 0.001); // 5:00–2:00 −1h lunch

        // the lead changes the crew's shift afterwards — history must not move
        Team::whereKey('t1')->update(['shift_in' => 360, 'shift_out' => 900]); // now 6:00–3:00
        $this->assertEqualsWithDelta(8.0, Attendance::paidHours($p->fresh()), 0.001);
        $this->assertSame(300, $p->fresh()->shift_in_snap);   // frozen
    }

    public function test_approved_correction_clears_a_stale_lead_adjustment(): void
    {
        Team::whereKey('t1')->update(['shift_in' => 300, 'shift_out' => 840]);
        $p = Punch::create([
            'employee_id' => 106, 'work_date' => '2026-07-01', 'team_id' => 't1',
            'in_min' => 300, 'out_min' => 900, 'no_lunch' => false, 'source' => 'qr',
            'adj_out_min' => 1020, 'adj_reason' => 'OT approved', 'adj_by' => 101, // paid to 5PM
        ]);

        // worker corrects: actually left at 3:00 PM — approver applies it
        $c = AttendanceCorrection::create([
            'employee_id' => 106, 'work_date' => '2026-07-01', 'type' => 'set',
            'req_in_min' => 300, 'req_out_min' => 900, 'reason' => 'left at 3', 'status' => 'pending',
        ]);
        Corrections::approve($c, 103);

        $p->refresh();
        $this->assertNull($p->adj_out_min);   // stale lead OT no longer overrides
        $this->assertSame(900, $p->out_min);
        // paid time now settles from the corrected reality, not the old adjustment
        $this->assertLessThan(9.5, Attendance::paidHours($p));
    }

    public function test_night_shift_entry_shows_an_error_instead_of_silently_saving_nothing(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('openTeamModal', 'c1')
            ->set('newTeamName', 'Night Crew')
            ->set('teamShiftIn', '22:00')
            ->set('teamShiftOut', '06:00')   // crosses midnight — unsupported
            ->call('saveTeam')
            ->assertSet('teamModal', 'c1');   // modal STAYS OPEN (error, not fake success)

        $this->assertNull(Team::where('name', 'Night Crew')->first());
    }

    public function test_half_set_shift_shows_an_error(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('openTeamModal', 'c1')
            ->set('newTeamName', 'Half Crew')
            ->set('teamShiftIn', '05:00')    // no end time
            ->call('saveTeam')
            ->assertSet('teamModal', 'c1');   // stays open with an error toast

        $this->assertNull(Team::where('name', 'Half Crew')->first());
    }

    public function test_field_lead_can_approve_a_crew_correction_from_the_phone(): void
    {
        config(['workforce.demo' => false]);
        $this->seed(UserSeeder::class);
        Team::whereKey('t1')->update(['lead' => 106]);   // Carlos leads t1
        $member = Employee::where('team_id', 't1')->where('id', '!=', 106)->first();

        $c = AttendanceCorrection::create([
            'employee_id' => $member->id, 'work_date' => now()->subDay()->format('Y-m-d'),
            'type' => 'set', 'req_in_min' => 360, 'req_out_min' => 900,
            'reason' => 'forgot to clock', 'status' => 'pending', 'lead_id' => 106,
        ]);

        $carlos = User::where('email', 'cmartinez@nahshon.io')->first();
        Livewire::actingAs($carlos)
            ->test(WorkforceApp::class)
            ->assertViewHas('crew', fn ($crew) => $crew !== null
                && count($crew['corrections']) === 1
                && $crew['corrections'][0]['canDecide'] === true)
            ->call('approveCorrection', $c->id);

        $this->assertSame('approved', $c->fresh()->status);
        $p = Punch::where('employee_id', $member->id)->where('work_date', $c->work_date)->first();
        $this->assertNotNull($p);
        $this->assertSame(360, $p->in_min);
    }
}
