<?php

namespace Tests\Feature;

use App\Livewire\WorkforceApp;
use App\Models\Absence;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Lead/admin decisions: mark a no-show absent, approve/reject leave, approve a
 * resignation (which runs the terminate flow).
 */
class AttendanceDecisionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['workforce.demo' => true]);
        $this->seed(WorkforceSeeder::class);
    }

    public function test_lead_marks_a_crew_member_unexcused(): void
    {
        Team::whereKey('t1')->update(['lead' => 106]);
        $member = Employee::where('team_id', 't1')->where('id', '!=', 106)->first();

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')     // owner has attendance.adjust globally
            ->call('markAbsent', $member->id, 'unexcused', '연락 두절');

        $this->assertDatabaseHas('absences', [
            'employee_id' => $member->id, 'work_date' => now()->format('Y-m-d'),
            'kind' => 'unexcused', 'source' => 'lead',
        ]);
    }

    public function test_admin_approves_and_rejects_leave(): void
    {
        $e = Employee::find(106);
        $l1 = Leave::create(['employee_id' => $e->id, 'start_date' => '2026-07-20', 'end_date' => '2026-07-22', 'status' => 'pending']);
        $l2 = Leave::create(['employee_id' => $e->id, 'start_date' => '2026-08-01', 'end_date' => '2026-08-02', 'status' => 'pending']);

        $c = Livewire::test(WorkforceApp::class)->call('demo', 'admin');
        $c->call('approveLeave', $l1->id);
        $c->call('rejectLeave', $l2->id);

        $this->assertSame('approved', $l1->fresh()->status);
        $this->assertSame('rejected', $l2->fresh()->status);
    }

    public function test_admin_approves_resignation_which_terminates(): void
    {
        $e = Employee::find(106);
        $e->update(['resign_on' => '2026-07-31', 'resign_reason' => '개인']);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('approveResign', $e->id);

        $e->refresh();
        $this->assertSame('terminated', $e->emp);
        $this->assertSame('2026-07-31', $e->term);
        $this->assertNull($e->resign_on);
    }

    public function test_worker_persona_cannot_approve_leave(): void
    {
        $l = Leave::create(['employee_id' => 106, 'start_date' => '2026-07-20', 'end_date' => '2026-07-22', 'status' => 'pending']);

        // seed a real worker with no decide rights
        $this->seed(UserSeeder::class);
        Livewire::actingAs(User::where('email', 'cmartinez@nahshon.io')->first())
            ->test(WorkforceApp::class)
            ->call('approveLeave', $l->id);

        $this->assertSame('pending', $l->fresh()->status);   // refused
    }
}
