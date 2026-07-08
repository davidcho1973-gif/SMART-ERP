<?php

namespace Tests\Feature;

use App\Livewire\WorkforceApp;
use App\Models\Employee;
use App\Models\Punch;
use App\Models\Team;
use App\Support\Attendance;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The paid-time math for the team-lead configurable shift (design "팀 시프트").
 * A 5:00–2:00 crew guarantees 8h (after lunch) for punches within ±30 min of the
 * shift; OT past the end is only paid when the lead adjusts; early-leave pays
 * actual; teams without a shift keep the legacy guess behavior.
 */
class TeamShiftTest extends TestCase
{
    use RefreshDatabase;

    private const WEEKDAY = '2026-06-24'; // Wednesday
    private const SATURDAY = '2026-06-27';

    private function shiftTeam(): Team
    {
        // 5:00 AM (300) – 2:00 PM (840) weekday, 7:00 (420) – 2:00 (840) Saturday
        return Team::create([
            'id' => 'tShift', 'name' => 'Shift Crew', 'company_id' => 'c1', 'color' => '#1F9D6B',
            'shift_in' => 300, 'shift_out' => 840, 'sat_in' => 420, 'sat_out' => 840,
        ]);
    }

    private function punch(array $attrs): Punch
    {
        return new Punch(array_merge([
            'employee_id' => 999, 'work_date' => self::WEEKDAY, 'no_lunch' => false,
        ], $attrs));
    }

    public function test_early_in_late_out_within_grace_pays_the_full_shift(): void
    {
        $this->shiftTeam();
        // clocked 4:40 (280) in, 2:30 (870) out — both within 30 min of the shift
        $p = $this->punch(['team_id' => 'tShift', 'in_min' => 280, 'out_min' => 870]);
        $s = Attendance::settle($p);

        $this->assertSame(300, $s['paidIn']);   // snapped to 5:00
        $this->assertSame(840, $s['paidOut']);  // snapped to 2:00 (no auto OT)
        $this->assertEqualsWithDelta(8.0, $s['paid'], 0.001); // 9h span − 1h lunch
    }

    public function test_staying_late_does_not_pay_overtime_without_lead_approval(): void
    {
        $this->shiftTeam();
        // stayed until 3:00 PM (900) — an hour past the shift end
        $p = $this->punch(['team_id' => 'tShift', 'in_min' => 300, 'out_min' => 900]);
        $s = Attendance::settle($p);

        $this->assertSame(840, $s['paidOut']);  // capped at shift end
        $this->assertEqualsWithDelta(8.0, $s['paid'], 0.001);
    }

    public function test_lead_adjustment_on_the_out_leg_pays_overtime(): void
    {
        $this->shiftTeam();
        // lead approves 30 extra minutes → paid out 2:30 PM (870)
        $p = $this->punch(['team_id' => 'tShift', 'in_min' => 300, 'out_min' => 900, 'adj_out_min' => 870]);
        $s = Attendance::settle($p);

        $this->assertTrue($s['adjusted']);
        $this->assertSame(870, $s['paidOut']);
        $this->assertEqualsWithDelta(8.5, $s['paid'], 0.001); // 9.5h span − 1h lunch
    }

    public function test_leaving_more_than_30_min_early_pays_actual_time(): void
    {
        $this->shiftTeam();
        // went home at noon (720) — 2h before the 2:00 shift end
        $p = $this->punch(['team_id' => 'tShift', 'in_min' => 300, 'out_min' => 720]);
        $s = Attendance::settle($p);

        $this->assertSame(720, $s['paidOut']);  // actual, not the shift end
        $this->assertEqualsWithDelta(6.0, $s['paid'], 0.001); // 7h span − 1h lunch
    }

    public function test_lead_can_restore_an_early_leave(): void
    {
        $this->shiftTeam();
        // left at noon but the lead credits the full shift end (approved absence)
        $p = $this->punch(['team_id' => 'tShift', 'in_min' => 300, 'out_min' => 720, 'adj_out_min' => 840]);
        $s = Attendance::settle($p);

        $this->assertSame(840, $s['paidOut']);
        $this->assertEqualsWithDelta(8.0, $s['paid'], 0.001);
    }

    public function test_saturday_uses_the_saturday_shift(): void
    {
        $this->shiftTeam();
        // Saturday shift is 7:00 (420) – 2:00 (840); arrive 6:45 (405), leave 2:10 (850)
        $p = $this->punch(['team_id' => 'tShift', 'work_date' => self::SATURDAY, 'in_min' => 405, 'out_min' => 850]);
        $s = Attendance::settle($p);

        $this->assertSame(420, $s['paidIn']);   // snapped to the Saturday start, not 5:00
        $this->assertSame(840, $s['paidOut']);
        $this->assertEqualsWithDelta(6.0, $s['paid'], 0.001); // 7h span − 1h lunch
    }

    public function test_team_without_a_shift_keeps_legacy_guess_behavior(): void
    {
        Team::create(['id' => 'tNone', 'name' => 'No Shift', 'company_id' => 'c1', 'color' => '#3B72E0']);
        // guessed 6–3 schedule; 5:53 (353) in snaps to 6:00 (360), 2:48 (888) out to 3:00 (900)
        $p = $this->punch(['team_id' => 'tNone', 'in_min' => 353, 'out_min' => 888]);
        $s = Attendance::settle($p);

        $this->assertSame('guess', $s['source']);
        $this->assertSame(360, $s['paidIn']);
        $this->assertSame(900, $s['paidOut']);
        $this->assertEqualsWithDelta(8.0, $s['paid'], 0.001);
    }

    public function test_no_shift_team_still_snaps_symmetrically_late_out_gets_overtime(): void
    {
        Team::create(['id' => 'tNone2', 'name' => 'No Shift 2', 'company_id' => 'c1', 'color' => '#3B72E0']);
        // legacy: 6:00 (360) in, 4:00 PM (960) out — an hour past 3:00, so no snap → real OT
        $p = $this->punch(['team_id' => 'tNone2', 'in_min' => 360, 'out_min' => 960]);
        $s = Attendance::settle($p);

        $this->assertSame(960, $s['paidOut']);   // legacy has no OT cap
        $this->assertEqualsWithDelta(9.0, $s['paid'], 0.001); // 10h − 1h lunch
    }

    // ---- Livewire flows (persist a shift, adjust a punch) ----

    public function test_manager_can_save_a_team_shift_through_the_modal(): void
    {
        config(['workforce.demo' => true]);
        $this->seed(WorkforceSeeder::class);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('openTeamModal', 'c1')
            ->set('newTeamName', 'Shift Crew A')
            ->set('teamShiftIn', '05:00')
            ->set('teamShiftOut', '14:00')
            ->set('teamSatIn', '07:00')
            ->set('teamSatOut', '14:00')
            ->call('saveTeam')
            ->assertSet('teamModal', null);

        $t = Team::where('name', 'Shift Crew A')->first();
        $this->assertNotNull($t);
        $this->assertSame(300, $t->shift_in);
        $this->assertSame(840, $t->shift_out);
        $this->assertSame(420, $t->sat_in);
        $this->assertSame(840, $t->sat_out);
    }

    public function test_a_half_set_shift_is_rejected_with_an_error(): void
    {
        config(['workforce.demo' => true]);
        $this->seed(WorkforceSeeder::class);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('openTeamModal', 'c1')
            ->set('newTeamName', 'Half Crew')
            ->set('teamShiftIn', '05:00')   // no end time → explicit error, modal stays open
            ->call('saveTeam')
            ->assertSet('teamModal', 'c1');

        $this->assertNull(Team::where('name', 'Half Crew')->first());
    }

    public function test_manager_can_adjust_a_punch_paid_time(): void
    {
        config(['workforce.demo' => true]);
        $this->seed(WorkforceSeeder::class);
        Team::whereKey('t1')->update(['shift_in' => 300, 'shift_out' => 840]);

        $emp = Employee::where('team_id', 't1')->first();
        $p = Punch::create([
            'employee_id' => $emp->id, 'work_date' => self::WEEKDAY, 'team_id' => 't1',
            'in_min' => 300, 'out_min' => 900, 'no_lunch' => false, 'source' => 'qr',
        ]);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('openAdjust', $p->id)
            ->assertSet('adjPunchId', $p->id)
            ->set('adjPaidOut', '14:30')
            ->set('adjPaidReason', '30 min overtime approved')
            ->call('saveAdjust')
            ->assertSet('adjPunchId', null);

        $p->refresh();
        $this->assertSame(870, $p->adj_out_min);
        $this->assertSame('30 min overtime approved', $p->adj_reason);
        $this->assertTrue($p->isAdjusted());
        $this->assertEqualsWithDelta(8.5, Attendance::paidHours($p), 0.001);
    }

    public function test_adjust_requires_a_reason(): void
    {
        config(['workforce.demo' => true]);
        $this->seed(WorkforceSeeder::class);
        $emp = Employee::where('team_id', 't1')->first();
        $p = Punch::create([
            'employee_id' => $emp->id, 'work_date' => self::WEEKDAY, 'team_id' => 't1',
            'in_min' => 300, 'out_min' => 900, 'no_lunch' => false, 'source' => 'qr',
        ]);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('openAdjust', $p->id)
            ->set('adjPaidOut', '14:30')
            ->set('adjPaidReason', '')       // missing reason
            ->call('saveAdjust')
            ->assertSet('adjPunchId', $p->id); // modal stays open

        $this->assertFalse($p->refresh()->isAdjusted());
    }

    public function test_lead_can_clear_an_adjustment(): void
    {
        config(['workforce.demo' => true]);
        $this->seed(WorkforceSeeder::class);
        $emp = Employee::where('team_id', 't1')->first();
        $p = Punch::create([
            'employee_id' => $emp->id, 'work_date' => self::WEEKDAY, 'team_id' => 't1',
            'in_min' => 300, 'out_min' => 900, 'no_lunch' => false, 'source' => 'qr',
            'adj_out_min' => 870, 'adj_reason' => 'OT', 'adj_by' => 1,
        ]);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('openAdjust', $p->id)
            ->call('clearAdjust')
            ->assertSet('adjPunchId', null);

        $p->refresh();
        $this->assertNull($p->adj_out_min);
        $this->assertFalse($p->isAdjusted());
    }
}
