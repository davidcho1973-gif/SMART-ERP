<?php

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\Punch;
use App\Models\Team;
use App\Support\WorkerStatus;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The single status resolver behind the dashboard, crew panel and worker app —
 * plus the end-of-day close that turns a scheduled no-show into 무단결근.
 */
class WorkerStatusTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;
    private Employee $e;

    protected function setUp(): void
    {
        parent::setUp();
        config(['workforce.demo' => true]);
        $this->seed(WorkforceSeeder::class);
        $this->team = Team::create(['id' => 'tt', 'name' => 'T', 'company_id' => 'c1', 'color' => '#3B72E0', 'shift_in' => 300, 'shift_out' => 840]);
        $this->e = Employee::find(106);
        $this->e->update(['team_id' => 'tt', 'emp' => 'active']);
    }

    private function st(string $ymd, Carbon $now, array $o = []): array
    {
        return WorkerStatus::resolve(
            $this->e->fresh(), $this->team,
            $o['punch'] ?? null, $o['leave'] ?? null, $o['absence'] ?? null, $ymd, $now
        );
    }

    public function test_working_done_early(): void
    {
        $day = '2026-07-08'; $now = Carbon::parse('2026-07-08 10:00');
        $in = new Punch(['work_date' => $day, 'in_min' => 300]);
        $this->assertSame('working', $this->st($day, $now, ['punch' => $in])['key']);

        $done = new Punch(['work_date' => $day, 'in_min' => 300, 'out_min' => 840]);
        $this->assertSame('done', $this->st($day, $now, ['punch' => $done])['key']);

        $early = new Punch(['work_date' => $day, 'in_min' => 300, 'out_min' => 720, 'early_reason' => '병원']);
        $s = $this->st($day, $now, ['punch' => $early]);
        $this->assertSame('early', $s['key']);
        $this->assertStringContainsString('병원', $s['detail']);
    }

    public function test_before_cutoff_pending_after_cutoff_missing(): void
    {
        $day = '2026-07-08';   // Wednesday, shift 5:00 → cutoff 5:30
        $this->assertSame('pending', $this->st($day, Carbon::parse('2026-07-08 05:00'))['key']);
        $this->assertSame('missing', $this->st($day, Carbon::parse('2026-07-08 06:00'))['key']);
    }

    public function test_past_scheduled_day_with_no_punch_reads_unexcused(): void
    {
        // yesterday, no punch, no record → derived 무단결근
        $s = $this->st('2026-07-07', Carbon::parse('2026-07-08 09:00'));
        $this->assertSame('unexcused', $s['key']);
    }

    public function test_sunday_is_off_not_missing(): void
    {
        $sun = '2026-07-12';
        $this->assertSame('off', $this->st($sun, Carbon::parse('2026-07-12 10:00'))['key']);
    }

    public function test_approved_leave_and_absence_and_terminated(): void
    {
        $day = '2026-07-08'; $now = Carbon::parse('2026-07-08 09:00');

        $leave = new Leave(['start_date' => '2026-07-06', 'end_date' => '2026-07-10', 'reason' => '개인', 'status' => 'approved']);
        $s = $this->st($day, $now, ['leave' => $leave]);
        $this->assertSame('leave', $s['key']);
        $this->assertStringContainsString('07/06', $s['detail']);

        $unex = new Absence(['work_date' => $day, 'kind' => 'unexcused']);
        $this->assertSame('unexcused', $this->st($day, $now, ['absence' => $unex])['key']);

        $exc = new Absence(['work_date' => $day, 'kind' => 'excused', 'reason' => '병가']);
        $this->assertSame('absent', $this->st($day, $now, ['absence' => $exc])['key']);

        $this->e->update(['emp' => 'terminated', 'term' => '07/02/2026']);
        $this->assertSame('terminated', $this->st($day, $now)['key']);
    }

    public function test_close_day_records_unexcused_for_scheduled_no_shows(): void
    {
        // member with no punch on a weekday → close-day marks unexcused
        $member = Employee::where('id', '!=', 106)->where('emp', 'active')->first();
        $member->update(['team_id' => 'tt']);

        // one worker DID punch → must be skipped
        Punch::create(['employee_id' => 106, 'work_date' => '2026-07-08', 'in_min' => 300, 'out_min' => 840, 'source' => 'qr']);

        $this->artisan('attendance:close-day', ['date' => '2026-07-08'])->assertOk();

        $this->assertDatabaseHas('absences', ['employee_id' => $member->id, 'work_date' => '2026-07-08', 'kind' => 'unexcused', 'source' => 'auto']);
        $this->assertDatabaseMissing('absences', ['employee_id' => 106, 'work_date' => '2026-07-08']);
        $this->assertSame(1, WorkerStatus::unexcusedCount($member->id, Carbon::parse('2026-07-08 12:00')));
    }

    public function test_close_day_skips_leave_and_sunday(): void
    {
        $member = Employee::where('id', '!=', 106)->where('emp', 'active')->first();
        $member->update(['team_id' => 'tt']);
        Leave::create(['employee_id' => $member->id, 'start_date' => '2026-07-08', 'end_date' => '2026-07-08', 'status' => 'approved']);

        $this->artisan('attendance:close-day', ['date' => '2026-07-08'])->assertOk();
        $this->assertDatabaseMissing('absences', ['employee_id' => $member->id, 'work_date' => '2026-07-08']);

        // Sunday → nobody marked
        $this->artisan('attendance:close-day', ['date' => '2026-07-12'])->assertOk();
        $this->assertSame(0, Absence::where('work_date', '2026-07-12')->count());
    }

    public function test_close_day_auto_closes_a_missing_clockout_to_scheduled_end(): void
    {
        // clocked in at 5:00 (300), never clocked out; snapshot end = 14:00 (840)
        $p = Punch::create(['employee_id' => 106, 'work_date' => '2026-07-08', 'in_min' => 300,
            'source' => 'qr', 'shift_in_snap' => 300, 'shift_out_snap' => 840]);
        $this->assertNull($p->out_min);

        $this->artisan('attendance:close-day', ['date' => '2026-07-08'])->assertOk();

        $p->refresh();
        $this->assertSame(840, $p->out_min);   // filled to the scheduled end
        $this->assertTrue($p->out_auto);       // flagged for review
    }

    public function test_auto_close_falls_back_to_crew_shift_when_no_snapshot(): void
    {
        // no snapshot on the punch → recompute from the crew's shift (300 → 840)
        Punch::create(['employee_id' => 106, 'work_date' => '2026-07-08', 'in_min' => 300, 'team_id' => 'tt', 'source' => 'qr']);

        $this->artisan('attendance:close-day', ['date' => '2026-07-08'])->assertOk();

        $p = Punch::where('employee_id', 106)->where('work_date', '2026-07-08')->first();
        $this->assertSame(840, $p->out_min);
        $this->assertTrue($p->out_auto);
    }

    public function test_auto_close_leaves_a_completed_punch_untouched(): void
    {
        Punch::create(['employee_id' => 106, 'work_date' => '2026-07-08', 'in_min' => 300, 'out_min' => 800,
            'source' => 'qr', 'shift_out_snap' => 840]);

        $this->artisan('attendance:close-day', ['date' => '2026-07-08'])->assertOk();

        $p = Punch::where('employee_id', 106)->where('work_date', '2026-07-08')->first();
        $this->assertSame(800, $p->out_min);   // real out kept, not overwritten to 840
        $this->assertFalse((bool) $p->out_auto);
    }

    public function test_auto_close_skips_when_scheduled_end_precedes_clock_in(): void
    {
        // clocked in AFTER the scheduled end (e.g. bad data / night shift) → left for manual review
        Punch::create(['employee_id' => 106, 'work_date' => '2026-07-08', 'in_min' => 900,
            'source' => 'qr', 'shift_out_snap' => 840]);

        $this->artisan('attendance:close-day', ['date' => '2026-07-08'])->assertOk();

        $p = Punch::where('employee_id', 106)->where('work_date', '2026-07-08')->first();
        $this->assertNull($p->out_min);        // not force-closed backwards
        $this->assertFalse((bool) $p->out_auto);
    }

    public function test_worker_status_flags_an_auto_closed_day_for_review(): void
    {
        $day = '2026-07-08';
        $now = Carbon::parse('2026-07-09 09:00');   // reviewing the day after
        $auto = new Punch(['work_date' => $day, 'in_min' => 300, 'out_min' => 840]);
        $auto->out_auto = true;

        $s = $this->st($day, $now, ['punch' => $auto]);
        $this->assertSame('done', $s['key']);
        $this->assertStringContainsString('자동 마감', $s['detail']);
    }

    public function test_auto_closed_day_now_counts_toward_pay(): void
    {
        // an open punch is invisible to payroll (no out) → the worker would be unpaid
        Punch::create(['employee_id' => 106, 'work_date' => '2026-07-08', 'in_min' => 300, 'team_id' => 'tt',
            'source' => 'qr', 'shift_in_snap' => 300, 'shift_out_snap' => 840]);
        $this->assertNull(\App\Support\Payroll::periodHoursFromPunches(106, '2026-07-06', '2026-07-10'));

        $this->artisan('attendance:close-day', ['date' => '2026-07-08'])->assertOk();

        // after auto-close the day is a complete punch and produces paid hours
        $hours = \App\Support\Payroll::periodHoursFromPunches(106, '2026-07-06', '2026-07-10');
        $this->assertNotNull($hours);
        $this->assertGreaterThan(0, $hours);
    }
}
