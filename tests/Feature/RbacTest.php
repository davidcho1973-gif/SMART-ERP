<?php

namespace Tests\Feature;

use App\Livewire\WorkforceApp;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Payment;
use App\Models\Punch;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use App\Support\Access;
use App\Support\Corrections;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Role ladder + capability gate + hard site scope (design doc "권한 계층 재설계").
 * Personas: demo admin ⇒ owner · demo manager ⇒ site_manager (employee 101, site s1)
 * · demo worker ⇒ worker (employee 106). Legacy access values map via Access::canonical.
 */
class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['workforce.demo' => true]);
        $this->seed(WorkforceSeeder::class);
    }

    // ---------- Access policy unit behavior ----------

    public function test_legacy_access_values_map_to_canonical_roles(): void
    {
        $this->assertSame('owner', Access::canonical('admin'));
        $this->assertSame('site_manager', Access::canonical('manager'));
        $this->assertSame('worker', Access::canonical('worker'));
        $this->assertSame('hr_admin', Access::canonical('hr_admin'));
        $this->assertSame('worker', Access::canonical(null));
    }

    public function test_capability_map_matches_the_matrix(): void
    {
        $this->assertTrue(Access::allows('owner', 'sites.delete'));
        $this->assertFalse(Access::allows('hr_admin', 'sites.delete'));   // people+money, no destructive org ops
        $this->assertTrue(Access::allows('owner', 'payroll.process'));
        $this->assertFalse(Access::allows('hr_admin', 'payroll.process')); // payroll is head-office (owner) only
        $this->assertFalse(Access::allows('site_manager', 'payroll.view')); // payroll is a real permission
        $this->assertTrue(Access::allows('site_manager', 'punch.manual'));
        $this->assertFalse(Access::allows('site_manager', 'employees.terminate'));
        $this->assertTrue(Access::allows('company_admin', 'employees.terminate')); // defined for phase D-2
        $this->assertTrue(Access::allows('crew_lead', 'corrections.decide'));
        $this->assertFalse(Access::allows('worker', 'punch.manual'));
    }

    public function test_company_admin_is_defined_but_not_yet_assignable(): void
    {
        // D-2: the role exists in the policy, but nobody can grant it from the UI yet
        $this->assertNotContains('company_admin', Access::assignable('owner'));
        $this->assertNotContains('company_admin', Access::assignable('hr_admin'));
        $this->assertSame(['worker'], Access::assignable('site_manager')); // a site lead may invite workers
        $this->assertSame([], Access::assignable('worker'));
    }

    // ---------- Phase 1 · capability gates ----------

    public function test_site_manager_cannot_delete_a_company(): void
    {
        $co = Company::first();

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'manager')
            ->call('askDeleteCompany', $co->id)
            ->call('confirmDeleteCompany');

        $this->assertNotNull(Company::find($co->id));   // still there
    }

    public function test_site_manager_cannot_delete_a_site(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'manager')
            ->call('askDeleteSite', 's1')
            ->call('confirmDeleteSite');

        $this->assertNotNull(Site::find('s1'));
    }

    public function test_site_manager_cannot_terminate_an_employee(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'manager')
            ->call('askTerm', 106)
            ->call('confirmTerm');

        $this->assertSame('active', Employee::find(106)->emp);
    }

    public function test_site_manager_cannot_reach_or_use_payroll(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'manager')
            ->call('go', 'payroll')
            ->assertSet('screen', 'dashboard')      // permission bounce, not a hidden menu
            ->call('openPayDetail', 106)
            ->assertSet('payDetail', null)
            ->set('checkNo', '555')
            ->call('printVoucher');

        $this->assertSame(0, Payment::count());
    }

    public function test_worker_persona_has_no_management_powers(): void
    {
        $before = Company::count();

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'worker')
            ->call('openCompanyModal')
            ->set('newCoName', 'Sneaky LLC')
            ->set('newCoSite', 'Somewhere')
            ->call('saveCompany')
            ->call('manualPunch', 107, 'in');

        $this->assertSame($before, Company::count());
        $this->assertNull(Punch::where('employee_id', 107)->where('work_date', now()->format('Y-m-d'))->first());
    }

    public function test_hr_admin_account_gets_people_but_not_payroll_or_org_destruction(): void
    {
        config(['workforce.demo' => false]);
        $hr = User::create([
            'name' => 'HR Person', 'email' => 'hr@nahshon.io',
            'password' => bcrypt('x'), 'access' => 'hr_admin',
        ]);
        $co = Company::first();

        Livewire::actingAs($hr)->test(WorkforceApp::class)
            ->assertSet('access', 'admin')            // admin VIEW ceiling (UI), caps still hr_admin
            ->assertSet('role', 'admin')
            ->call('go', 'payroll')
            ->assertSet('screen', 'dashboard')        // payroll is owner-only — navigation refused
            ->call('askDeleteCompany', $co->id)
            ->call('confirmDeleteCompany');

        $this->assertNotNull(Company::find($co->id)); // destructive org op still blocked

        // people ops allowed org-wide
        Livewire::actingAs($hr)->test(WorkforceApp::class)
            ->call('askTerm', 116)->call('confirmTerm');
        $this->assertSame('terminated', Employee::find(116)->emp);
    }

    // ---------- Phase 2 · hard site scope (D-3) ----------

    public function test_site_manager_site_selector_is_pinned_to_their_site(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'manager')
            ->assertSet('site', 's1')       // pinned on persona switch
            ->set('site', 's2')
            ->assertSet('site', 's1');      // clamp snaps it back
    }

    public function test_site_manager_cannot_punch_a_worker_on_another_site(): void
    {
        Employee::where('id', 107)->update(['site_id' => 's2']);
        $today = now()->format('Y-m-d');

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'manager')
            ->call('manualPunch', 107, 'in');   // out of scope → no-op
        $this->assertNull(Punch::where('employee_id', 107)->where('work_date', $today)->first());

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'manager')
            ->call('manualPunch', 106, 'in');   // own site → works
        $this->assertNotNull(Punch::where('employee_id', 106)->where('work_date', $today)->first());
    }

    public function test_site_manager_cannot_edit_an_employee_on_another_site(): void
    {
        Employee::where('id', 108)->update(['site_id' => 's2']);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'manager')
            ->call('selectEmp', 108)
            ->set('editForm.role', 'Hacked Role')
            ->call('saveEmp');

        $this->assertNotSame('Hacked Role', Employee::find(108)->role);
    }

    public function test_owner_keeps_the_all_sites_view(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->assertSet('site', 'all')
            ->set('site', 's2')
            ->assertSet('site', 's2');   // unrestricted
    }

    // ---------- Phase 3 · crew_lead as a formal (derived) role ----------

    public function test_a_worker_who_leads_a_crew_may_punch_their_own_crew_only(): void
    {
        // promote worker 106 to lead of their crew t1 (derived crew_lead overlay)
        Team::where('id', 't1')->update(['lead' => 106]);
        Employee::where('id', 107)->update(['team_id' => 't1']);
        Employee::where('id', 108)->update(['team_id' => 't2']);
        $today = now()->format('Y-m-d');

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'worker')                 // employee 106 + crew_lead overlay
            ->call('manualPunch', 107, 'in');        // own crew → allowed
        $this->assertNotNull(Punch::where('employee_id', 107)->where('work_date', $today)->first());

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'worker')
            ->call('manualPunch', 108, 'in');        // other crew → blocked
        $this->assertNull(Punch::where('employee_id', 108)->where('work_date', $today)->first());
    }

    // ---------- role granting guard ----------

    public function test_site_manager_cannot_grant_roles(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'manager')
            ->call('selectEmp', 106)
            ->set('editForm.access', 'admin')
            ->call('saveEmp');

        $this->assertSame('worker', Employee::find(106)->access);   // unchanged
    }

    public function test_owner_can_grant_hr_admin(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('selectEmp', 106)
            ->set('editForm.access', 'hr_admin')
            ->call('saveEmp');

        $this->assertSame('hr_admin', Employee::find(106)->access);
    }

    // ---------- Phase 4 · audit trail ----------

    public function test_role_grants_and_terminations_leave_audit_rows(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('selectEmp', 106)
            ->set('editForm.access', 'hr_admin')
            ->call('saveEmp');
        $grant = AuditLog::where('action', 'role.grant')->first();
        $this->assertNotNull($grant);
        $this->assertStringContainsString('worker → hr_admin', $grant->detail);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('askTerm', 116)->call('confirmTerm');
        $this->assertSame(1, AuditLog::where('action', 'employee.terminate')->count());

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('manualPunch', 112, 'in');
        $this->assertSame(1, AuditLog::where('action', 'punch.manual')->count());
    }

    public function test_correction_decisions_join_the_audit_stream(): void
    {
        $c = Corrections::submit(Employee::find(106), now()->subDay()->format('Y-m-d'), 'set', 390, 930, 'fix');

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('approveCorrection', $c->id);

        $row = AuditLog::where('action', 'correction.approve')->first();
        $this->assertNotNull($row);
        $this->assertStringContainsString('Carlos', $row->target);
    }

    public function test_audit_trail_renders_for_owner_but_not_site_manager(): void
    {
        AuditLog::create(['actor_name' => 'Tester', 'action' => 'role.grant', 'target' => 'X', 'detail' => 'a → b']);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'employees')
            ->assertSee('Audit trail');

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'manager')
            ->call('go', 'employees')
            ->assertDontSee('Audit trail');
    }

    public function test_badge_registration_by_a_site_manager_always_creates_a_worker(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'manager')
            ->call('addWorker')
            ->set('regFirst', 'New')->set('regLast', 'Hand')
            ->set('regTeam', 't1')                         // crew on site s1 (in scope)
            ->set('regAccess', 'admin')                    // sneaky privilege attempt
            ->set('nfcUidManual', 'AA:BB:CC:DD:EE:FF:11')
            ->call('finishBadge');

        $e = Employee::where('first', 'New')->where('last', 'Hand')->first();
        $this->assertNotNull($e);
        $this->assertSame('worker', $e->access);           // clamped to grantable = worker
    }
}
