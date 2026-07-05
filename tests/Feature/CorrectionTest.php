<?php

namespace Tests\Feature;

use App\Livewire\WorkforceApp;
use App\Models\AttendanceCorrection;
use App\Models\Channel;
use App\Models\Employee;
use App\Models\Payment;
use App\Models\Punch;
use App\Support\Corrections;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Attendance-correction request + approval flow.
 * Seeded facts used here: worker 106 (Carlos) is on crew t1, whose lead is manager 101.
 */
class CorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['workforce.demo' => true]);
        $this->seed(WorkforceSeeder::class);
    }

    private function yesterday(): string
    {
        return now()->subDay()->format('Y-m-d');
    }

    public function test_worker_submits_a_correction_and_it_snapshots_company_crew_lead(): void
    {
        $date = $this->yesterday();
        Punch::create(['employee_id' => 106, 'work_date' => $date, 'in_min' => 420, 'out_min' => 780, 'source' => 'worker']);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'worker')                 // acts as employee 106
            ->call('openCorrection', $date)
            ->assertSet('correctionOpen', true)
            ->set('correctionIn', '06:30')
            ->set('correctionOut', '15:30')
            ->set('correctionReason', 'Clocked out by accident at lunch')
            ->call('submitCorrection')
            ->assertSet('correctionOpen', false);

        $c = AttendanceCorrection::where('employee_id', 106)->where('work_date', $date)->first();
        $this->assertNotNull($c);
        $this->assertSame('pending', $c->status);
        $this->assertSame(390, $c->req_in_min);   // 06:30
        $this->assertSame(930, $c->req_out_min);  // 15:30
        $this->assertSame(420, $c->orig_in_min);  // snapshot of the original punch
        $this->assertSame(780, $c->orig_out_min);
        $this->assertSame('t1', $c->team_id);     // snapshot crew
        $this->assertSame('c2', $c->company_id);  // crew's company
        $this->assertSame(101, $c->lead_id);      // crew lead frozen on the request
    }

    public function test_submit_notifies_approvers_in_the_correction_room(): void
    {
        $date = $this->yesterday();

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'worker')
            ->call('openCorrection', $date)
            ->set('correctionIn', '06:30')
            ->set('correctionReason', 'Forgot to clock in')
            ->call('submitCorrection');

        $ch = Channel::where('type', 'correction')->first();
        $this->assertNotNull($ch);
        // the crew lead (101) is a member so their unread bell lights up
        $this->assertTrue($ch->members()->where('employee_id', 101)->exists());
        $this->assertSame(1, $ch->messages()->count());
    }

    public function test_lead_approval_applies_the_correction_to_the_punch(): void
    {
        $date = $this->yesterday();
        Punch::create(['employee_id' => 106, 'work_date' => $date, 'in_min' => 500, 'out_min' => 900, 'source' => 'worker']);
        $c = Corrections::submit(Employee::find(106), $date, 'set', 390, 930, 'fix');

        // manager 101 is the crew lead → may approve (they are not the requester)
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'manager')                 // acts as employee 101
            ->call('approveCorrection', $c->id);

        $c->refresh();
        $this->assertSame('approved', $c->status);
        $this->assertSame(101, $c->decided_by);

        $p = Punch::where('employee_id', 106)->where('work_date', $date)->first();
        $this->assertSame(390, $p->in_min);
        $this->assertSame(930, $p->out_min);
        $this->assertSame('manual', $p->source);
    }

    public function test_approval_creates_a_punch_when_the_day_had_none(): void
    {
        $date = $this->yesterday();
        $c = Corrections::submit(Employee::find(106), $date, 'set', 400, 880, 'forgot to clock');

        Livewire::test(WorkforceApp::class)->call('demo', 'admin')->call('approveCorrection', $c->id);

        $p = Punch::where('employee_id', 106)->where('work_date', $date)->first();
        $this->assertNotNull($p);
        $this->assertSame(400, $p->in_min);
        $this->assertSame(880, $p->out_min);
    }

    public function test_delete_request_removes_the_punch(): void
    {
        $date = $this->yesterday();
        Punch::create(['employee_id' => 106, 'work_date' => $date, 'in_min' => 420, 'out_min' => 780, 'source' => 'worker']);
        $c = Corrections::submit(Employee::find(106), $date, 'delete', null, null, 'wrong day');

        Livewire::test(WorkforceApp::class)->call('demo', 'admin')->call('approveCorrection', $c->id);

        $this->assertNull(Punch::where('employee_id', 106)->where('work_date', $date)->first());
        $this->assertSame('approved', $c->fresh()->status);
    }

    public function test_a_non_lead_manager_cannot_approve_another_crews_request(): void
    {
        $date = $this->yesterday();
        // request from worker 106 (crew t1, lead 101)
        $c = Corrections::submit(Employee::find(106), $date, 'set', 390, 930, 'fix');

        // demo 'manager' persona is employee 101 — but retarget to a different lead's request:
        // build a request whose lead is 102 (crew t2) so 101 is neither admin nor its lead
        $c->update(['lead_id' => 102, 'team_id' => 't2']);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'manager')          // employee 101
            ->call('approveCorrection', $c->id);

        $this->assertSame('pending', $c->fresh()->status);  // blocked
    }

    public function test_requester_cannot_approve_their_own_request(): void
    {
        $date = $this->yesterday();
        // a manager files their own correction, and is also their crew's lead
        $mgr = Employee::find(101); // lead of t1
        $c = Corrections::submit($mgr, $date, 'set', 390, 930, 'my own fix');
        $this->assertSame(101, $c->lead_id); // they are the lead on their own request

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'manager')          // employee 101 = the requester
            ->call('approveCorrection', $c->id);

        $this->assertSame('pending', $c->fresh()->status);  // self-approval blocked
    }

    public function test_rejection_records_a_note_and_closes_the_request(): void
    {
        $date = $this->yesterday();
        $c = Corrections::submit(Employee::find(106), $date, 'set', 390, 930, 'fix');

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('askRejectCorrection', $c->id)
            ->set('rejectNote', 'Times do not match the gate log')
            ->call('rejectCorrection', $c->id);

        $c->refresh();
        $this->assertSame('rejected', $c->status);
        $this->assertSame('Times do not match the gate log', $c->decision_note);
        // the punch is untouched on rejection
        $this->assertNull(Punch::where('employee_id', 106)->where('work_date', $date)->first());
    }

    public function test_double_approval_is_idempotent(): void
    {
        $date = $this->yesterday();
        $c = Corrections::submit(Employee::find(106), $date, 'set', 390, 930, 'fix');

        Livewire::test(WorkforceApp::class)->call('demo', 'admin')->call('approveCorrection', $c->id);
        // a second decision must not re-open or double-apply
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('askRejectCorrection', $c->id)
            ->set('rejectNote', 'changed my mind')
            ->call('rejectCorrection', $c->id);

        $this->assertSame('approved', $c->fresh()->status);   // stays approved
    }

    public function test_worker_cannot_file_for_an_already_paid_period(): void
    {
        $date = $this->yesterday();
        Payment::create([
            'employee_id' => 106, 'period_start' => now()->subDays(3)->format('Y-m-d'),
            'period_end' => now()->format('Y-m-d'), 'check_no' => '9001', 'pay_date' => 'Jul 1, 2026',
            'amount' => 100, 'reg_hours' => 8, 'ot_hours' => 0,
        ]);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'worker')
            ->call('openCorrection', $date)
            ->assertSet('correctionOpen', false);           // blocked before the form opens

        $this->assertSame(0, AttendanceCorrection::count());
    }

    public function test_only_one_pending_request_per_day(): void
    {
        $date = $this->yesterday();
        Corrections::submit(Employee::find(106), $date, 'set', 390, 930, 'first');

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'worker')
            ->call('openCorrection', $date)
            ->assertSet('correctionOpen', false);           // a pending request already exists

        $this->assertSame(1, AttendanceCorrection::where('employee_id', 106)->where('work_date', $date)->count());
    }

    public function test_approver_sees_the_pending_card_in_the_correction_room(): void
    {
        $date = $this->yesterday();
        Corrections::submit(Employee::find(106), $date, 'set', 390, 930, 'lunch punch mistake');
        $ch = Channel::where('type', 'correction')->first();

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'comms')
            ->call('selectChannel', $ch->id)
            ->assertSee('Carlos')                 // worker name on the request card
            ->assertSee('lunch punch mistake');   // the reason renders
    }

    public function test_future_dates_are_rejected(): void
    {
        $tomorrow = now()->addDay()->format('Y-m-d');

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'worker')
            ->call('openCorrection', $tomorrow)
            ->assertSet('correctionOpen', false);
    }

    public function test_clock_out_before_clock_in_is_rejected(): void
    {
        $date = $this->yesterday();

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'worker')
            ->call('openCorrection', $date)
            ->set('correctionIn', '15:00')
            ->set('correctionOut', '06:00')
            ->set('correctionReason', 'bad times')
            ->call('submitCorrection')
            ->assertSet('correctionOpen', true);            // stays open, not submitted

        $this->assertSame(0, AttendanceCorrection::count());
    }
}
