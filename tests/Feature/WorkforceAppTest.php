<?php

namespace Tests\Feature;

use App\Livewire\WorkforceApp;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Site;
use App\Models\Team;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkforceAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WorkforceSeeder::class);
    }

    public function test_renders_login_by_default(): void
    {
        Livewire::test(WorkforceApp::class)
            ->assertSee('NAHSHON MEP')
            ->assertSet('screen', 'login');
    }

    public function test_enters_admin_dashboard_from_demo(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->assertSet('screen', 'dashboard')
            ->assertSet('role', 'admin');
    }

    public function test_creates_company_and_new_site_when_typed_name_is_new(): void
    {
        $sites = Site::count();
        Livewire::test(WorkforceApp::class)
            ->call('openCompanyModal')
            ->set('newCoName', 'Desert Sparky Co')
            ->set('newCoSite', 'Meta Data Center · Mesa, AZ')
            ->call('saveCompany')
            ->assertSet('companyModal', false);

        $this->assertTrue(Company::where('name', 'Desert Sparky Co')->exists());
        $this->assertSame($sites + 1, Site::count());
    }

    public function test_adds_a_crew_to_a_company(): void
    {
        $before = Team::count();
        Livewire::test(WorkforceApp::class)
            ->call('openTeamModal', 'c1')
            ->set('newTeamName', 'Electrical Crew B')
            ->call('saveTeam')
            ->assertSet('teamModal', null);

        $this->assertSame($before + 1, Team::count());
        $this->assertTrue(Team::where('name', 'Electrical Crew B')->where('company_id', 'c1')->exists());
    }

    public function test_changes_a_crew_lead(): void
    {
        Livewire::test(WorkforceApp::class)->call('changeLead', 't1', '103');
        $this->assertSame(103, Team::find('t1')->lead);
    }

    public function test_terminate_then_reactivate_preserves_record(): void
    {
        Livewire::test(WorkforceApp::class)->call('askTerm', 106)->call('confirmTerm');

        $e = Employee::find(106);
        $this->assertSame('terminated', $e->emp);
        $this->assertSame('worker', $e->access);
        $this->assertNotNull($e->term);

        Livewire::test(WorkforceApp::class)->call('reactivate', 106);
        $this->assertSame('active', Employee::find(106)->emp);
    }

    public function test_permanently_deletes_employee(): void
    {
        Livewire::test(WorkforceApp::class)->call('askDelete', 116)->call('confirmDelete');
        $this->assertNull(Employee::find(116));
    }

    public function test_saves_edits_from_detail_drawer(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('selectEmp', 106)
            ->set('editForm.role', 'Lead Electrician')
            ->set('editForm.rate', '40')
            ->set('editForm.access', 'manager')
            ->call('saveEmp')
            ->assertSet('selectedEmp', null);

        $e = Employee::find(106);
        $this->assertSame('Lead Electrician', $e->role);
        $this->assertSame(40.0, $e->rate);
        $this->assertSame('manager', $e->access);
    }

    public function test_badge_wizard_registers_employee_with_nfc_id(): void
    {
        $before = Employee::count();
        Livewire::test(WorkforceApp::class)
            ->call('addWorker')
            ->call('startScanF')->call('finishScanF')
            ->call('toBack')
            ->call('startScanB')->call('finishScanB')
            ->call('toAssign')
            ->set('regTeam', 't2')
            ->call('startScanN')->call('finishScanN')
            ->call('finishBadge')
            ->assertSet('screen', 'employees');

        $this->assertSame($before + 1, Employee::count());
        $new = Employee::latest('id')->first();
        $this->assertSame('N-C2F19B45E', $new->emp_id);
        $this->assertSame('t2', $new->team_id);
    }

    public function test_site_managers_have_no_payroll_nav(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'manager')
            ->assertSet('role', 'manager')
            ->assertDontSee('Payroll');
    }

    public function test_voucher_requires_check_number_before_print(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('openPayDetail', 106)
            ->call('openVoucher')
            ->call('printVoucher')
            ->assertSet('payVoucher', true);
    }

    public function test_manual_punch_persists_attendance_status(): void
    {
        // employee 112 is seeded 'absent'
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('manualPunch', 112, 'in');

        $e = Employee::find(112);
        $this->assertContains($e->status, ['present', 'late']);
        $this->assertNotSame('—', $e->in_t);

        Livewire::test(WorkforceApp::class)->call('manualPunch', 112, 'out');
        $this->assertSame('off', Employee::find(112)->status);
    }

    public function test_worker_clock_persists_status(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'worker')
            ->call('doClock')            // out -> in
            ->assertSet('clock', 'in');
        $this->assertSame('present', Employee::find(106)->status);

        Livewire::test(WorkforceApp::class)
            ->set('clock', 'in')
            ->call('doClock')            // in -> out
            ->assertSet('clock', 'out');
        $this->assertSame('off', Employee::find(106)->status);
    }
}
