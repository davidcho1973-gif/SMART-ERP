<?php

namespace Tests\Feature;

use App\Livewire\WorkforceApp;
use App\Models\Absence;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\Punch;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Workers self-report the exceptions the clock can't: 결근 (excused, immediate),
 * 휴가 (leave request → approval), 퇴사 (resignation notice → approval).
 */
class WorkerSelfReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['workforce.demo' => false]);
        $this->seed(WorkforceSeeder::class);
        $this->seed(UserSeeder::class);
    }

    private function carlos(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::actingAs(User::where('email', 'cmartinez@nahshon.io')->first())->test(WorkforceApp::class);
    }

    public function test_worker_reports_an_excused_absence_for_today(): void
    {
        $this->carlos()
            ->call('openStatusSheet', 'absent')
            ->assertSet('statusSheet', 'absent')
            ->set('absentReason', '몸살')
            ->call('reportAbsent')
            ->assertSet('statusSheet', '');

        $this->assertDatabaseHas('absences', [
            'employee_id' => 106, 'work_date' => now()->format('Y-m-d'),
            'kind' => 'excused', 'source' => 'worker', 'reason' => '몸살',
        ]);
    }

    public function test_absence_is_blocked_once_clocked_in(): void
    {
        Punch::create(['employee_id' => 106, 'work_date' => now()->format('Y-m-d'), 'in_min' => 360, 'source' => 'qr']);

        $this->carlos()->call('openStatusSheet', 'absent')->set('absentReason', 'x')->call('reportAbsent');

        $this->assertDatabaseMissing('absences', ['employee_id' => 106, 'work_date' => now()->format('Y-m-d')]);
    }

    public function test_worker_files_a_leave_request_pending_approval(): void
    {
        $this->carlos()
            ->call('openStatusSheet', 'leave')
            ->set('leaveStart', '2026-07-20')
            ->set('leaveEnd', '2026-07-22')
            ->set('leaveReason', '가족 행사')
            ->call('saveLeave')
            ->assertSet('statusSheet', '');

        $this->assertDatabaseHas('leaves', [
            'employee_id' => 106, 'start_date' => '2026-07-20', 'end_date' => '2026-07-22',
            'status' => 'pending', 'reason' => '가족 행사',
        ]);
    }

    public function test_leave_with_bad_dates_is_rejected(): void
    {
        $this->carlos()
            ->call('openStatusSheet', 'leave')
            ->set('leaveStart', '2026-07-22')
            ->set('leaveEnd', '2026-07-20')   // end before start
            ->call('saveLeave')
            ->assertSet('statusSheet', 'leave');   // stays open

        $this->assertSame(0, Leave::count());
    }

    public function test_worker_files_a_resignation_notice(): void
    {
        $this->carlos()
            ->call('openStatusSheet', 'resign')
            ->set('resignOn', '2026-07-31')
            ->set('resignReason', '개인 사정')
            ->call('saveResign')
            ->assertSet('statusSheet', '');

        $e = Employee::find(106);
        $this->assertSame('2026-07-31', $e->resign_on);
        $this->assertTrue($e->hasPendingResignation());
        $this->assertSame('active', $e->emp);   // still active until admin approves
    }
}
