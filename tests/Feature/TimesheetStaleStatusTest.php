<?php

namespace Tests\Feature;

use App\Livewire\WorkforceApp;
use App\Models\Employee;
use App\Models\Punch;
use App\Support\Timesheet;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A worker who clocks in but forgets to clock out leaves a denormalized live-status
 * cache (status/in_t) set to "present". That cache must never resurrect a stale
 * clock-in on a later day's timesheet — the punch table is the source of truth —
 * and voiding a past punch must clear it.
 */
class TimesheetStaleStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['workforce.demo' => true]);
        $this->seed(WorkforceSeeder::class);
    }

    private function clockedInYesterdayNeverOut(): Employee
    {
        $e = Employee::find(106);
        Punch::create([
            'employee_id' => 106, 'work_date' => now()->subDay()->format('Y-m-d'),
            'in_min' => 454, 'source' => 'worker',   // 7:34 AM in, no out
        ]);
        $e->update(['status' => 'present', 'in_t' => '7:34 AM', 'out_t' => '—']);

        return $e;
    }

    public function test_stale_live_status_is_not_shown_as_present_on_todays_timesheet(): void
    {
        $this->clockedInYesterdayNeverOut();

        $ts = Timesheet::forDate(now()->format('Y-m-d'), 'all', 'ko');
        $row = collect($ts['rows'])->firstWhere('id', 106);

        $this->assertNotNull($row);
        $this->assertSame('—', $row['actIn']);   // no punch today → not clocked in
        $this->assertFalse($row['onDuty']);       // and not shown as 근무중
    }

    public function test_voiding_a_past_punch_clears_the_stale_live_status(): void
    {
        $e = $this->clockedInYesterdayNeverOut();
        $yesterday = now()->subDay()->format('Y-m-d');

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->set('attDate', $yesterday)
            ->call('askVoidPunch', 106)
            ->call('confirmVoidPunch');

        $e->refresh();
        $this->assertSame('off', $e->status);   // cache reset even though a PAST date was voided
        $this->assertSame('—', $e->in_t);
        $this->assertDatabaseMissing('punches', ['employee_id' => 106, 'work_date' => $yesterday]);
    }
}
