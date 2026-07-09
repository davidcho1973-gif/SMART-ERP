<?php

namespace Tests\Feature;

use App\Livewire\WorkforceApp;
use App\Models\Employee;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 파견 (out-of-state dispatch) on the employee record, and admins filing their own
 * status (휴가/퇴사/결근) from the desktop like field leads and workers.
 */
class DispatchAndAdminStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['workforce.demo' => true]);
        $this->seed(WorkforceSeeder::class);
    }

    public function test_dispatch_is_saved_and_cleared_via_the_edit_drawer(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('selectEmp', 106)
            ->set('editForm.dispatch_to', 'Texas · Samsung Taylor')
            ->set('editForm.dispatch_from', '2026-07-01')
            ->set('editForm.dispatch_until', '2026-09-30')
            ->set('editForm.dispatch_note', 'piping support')
            ->call('saveEmp');

        $e = Employee::find(106);
        $this->assertSame('Texas · Samsung Taylor', $e->dispatch_to);
        $this->assertSame('2026-07-01', $e->dispatch_from);
        $this->assertSame('2026-09-30', $e->dispatch_until);
        $this->assertTrue($e->isDispatched());

        // clearing the destination clears the whole dispatch
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('selectEmp', 106)
            ->set('editForm.dispatch_to', '')
            ->call('saveEmp');

        $e->refresh();
        $this->assertNull($e->dispatch_to);
        $this->assertNull($e->dispatch_from);
        $this->assertFalse($e->isDispatched());
    }

    public function test_bad_dispatch_dates_are_dropped_not_stored(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('selectEmp', 106)
            ->set('editForm.dispatch_to', 'Nevada')
            ->set('editForm.dispatch_from', 'not-a-date')
            ->call('saveEmp');

        $e = Employee::find(106);
        $this->assertSame('Nevada', $e->dispatch_to);
        $this->assertNull($e->dispatch_from);   // invalid date ignored
    }

    public function test_registering_an_invite_with_dispatch(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->set('invFirst', 'Roving')->set('invLast', 'Welder')
            ->set('invEmail', 'roving@nahshon.io')
            ->set('invDispatchTo', 'Ohio · Intel')
            ->call('saveEmpInvite');

        $e = Employee::where('email', 'roving@nahshon.io')->first();
        $this->assertNotNull($e);
        $this->assertSame('Ohio · Intel', $e->dispatch_to);
        $this->assertTrue($e->isDispatched());
    }

    public function test_admin_files_a_leave_from_the_desktop(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('openStatusSheet', 'leave')
            ->assertSet('statusSheet', 'leave')
            ->set('leaveStart', '2026-08-01')
            ->set('leaveEnd', '2026-08-03')
            ->set('leaveReason', 'family')
            ->call('saveLeave')
            ->assertSet('statusSheet', '');   // closed on success

        // a pending leave now exists for the admin's own employee record
        $this->assertDatabaseHas('leaves', [
            'start_date' => '2026-08-01', 'end_date' => '2026-08-03', 'status' => 'pending',
        ]);
    }
}
