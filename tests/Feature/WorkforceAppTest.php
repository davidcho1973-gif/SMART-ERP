<?php

namespace Tests\Feature;

use App\Livewire\ScanClock;
use App\Livewire\WorkforceApp;
use App\Models\Assignment;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Payment;
use App\Models\Punch;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Database\Seeders\WorkforceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class WorkforceAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['workforce.demo' => true]); // tests drive the app through demo mode
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

    public function test_badge_register_derives_company_from_crew(): void
    {
        $team = Team::find('t3');   // seeded crew, belongs to a company

        Livewire::test(WorkforceApp::class)
            ->call('addWorker')
            ->set('regFirst', 'Nueva')
            ->set('regLast', 'Persona')
            ->set('regTeam', $team->id)
            ->set('nfcUidManual', 'AA:BB:CC:11:22:33')
            ->call('finishBadge');

        $e = Employee::where('first', 'Nueva')->where('last', 'Persona')->first();
        $this->assertNotNull($e);
        $this->assertSame($team->id, $e->team_id);
        $this->assertSame($team->company_id, $e->company_id);          // company follows the crew
        $this->assertNotNull(Company::find($e->company_id)); // and it's a real company
    }

    public function test_registration_stores_the_selected_app_language(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('addWorker')
            ->set('regFirst', 'Ana')
            ->set('regLast', 'Lingua')
            ->set('regTeam', 't1')
            ->set('nfcUidManual', 'AA:BB:CC:44:55:66')
            ->call('setRegType', 'worker_local')   // suggests es…
            ->set('regLang', 'en')                 // …but the picker wins
            ->call('finishBadge');

        $this->assertSame('en', Employee::where('first', 'Ana')->first()->lang);
    }

    public function test_set_reg_type_suggests_a_language_default(): void
    {
        $c = Livewire::test(WorkforceApp::class);
        $c->call('setRegType', 'worker_ko')->assertSet('regLang', 'ko');
        $c->call('setRegType', 'worker_local')->assertSet('regLang', 'es');
        $c->call('setRegType', 'manager_ko')->assertSet('regLang', 'ko');
        $c->call('setRegType', 'manager_local')->assertSet('regLang', 'es');
    }

    public function test_edit_drawer_saves_the_selected_language(): void
    {
        // Carlos (106) is seeded es; switch him to English via the drawer
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('selectEmp', 106)
            ->assertSet('editForm.lang', 'es')
            ->set('editForm.lang', 'en')
            ->call('saveEmp');

        $this->assertSame('en', Employee::find(106)->lang);
    }

    public function test_login_lands_in_the_employee_registered_language(): void
    {
        $this->seed(UserSeeder::class);
        // Minjun (101) is a site manager whose role-based default would be ko;
        // his registered language must win over that guess
        Employee::whereKey(101)->update(['lang' => 'es']);
        $manager = User::where('email', 'mkim@nahshon.io')->first();

        Livewire::actingAs($manager)
            ->test(WorkforceApp::class)
            ->assertSet('lang', 'es');
    }

    public function test_employee_with_stale_crew_is_repaired_on_edit(): void
    {
        $emp = Employee::create([
            'emp_id' => 'N-STALE01', 'first' => 'Stale', 'last' => 'Record',
            'type' => 'manager', 'access' => 'manager', 'rate' => 0,
            'company_id' => 'cGONE', 'team_id' => 'tGONE', 'emp' => 'active',
        ]);
        $firstTeam = Team::first();

        Livewire::test(WorkforceApp::class)
            ->call('selectEmp', $emp->id)
            ->assertSet('editForm.team', $firstTeam->id)            // invalid crew coerced to a real one
            ->assertSet('editForm.company', $firstTeam->company_id) // company derived
            ->call('saveEmp');

        $emp->refresh();
        $this->assertNotNull(Team::find($emp->team_id));
        $this->assertNotNull(Company::find($emp->company_id));
        $this->assertSame($firstTeam->company_id, $emp->company_id);
    }

    public function test_add_and_remove_company_involvement(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('selectEmp', 106)
            ->set('newAssignCompany', 'c1')
            ->set('newAssignTeam', 't1')
            ->set('newAssignRelation', '파견')
            ->call('addAssignment');

        $a = Assignment::where('employee_id', 106)->first();
        $this->assertNotNull($a);
        $this->assertSame('c1', $a->company_id);
        $this->assertSame('t1', $a->team_id);
        $this->assertSame('파견', $a->relation);

        // a custom "기타" relation is accepted as free text
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('selectEmp', 106)
            ->set('newAssignCompany', 'c2')
            ->set('newAssignRelation', '기타: 감리 지원')
            ->call('addAssignment');
        $this->assertSame('기타: 감리 지원', Assignment::where('employee_id', 106)->where('company_id', 'c2')->first()->relation);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('selectEmp', 106)
            ->call('removeAssignment', $a->id);
        $this->assertNull(Assignment::find($a->id));
    }

    public function test_changes_a_crew_lead(): void
    {
        Livewire::test(WorkforceApp::class)->call('changeLead', 't1', '103');
        $this->assertSame(103, Team::find('t1')->lead);
    }

    public function test_edits_a_company_name(): void
    {
        $co = Company::first();
        Livewire::test(WorkforceApp::class)
            ->call('openEditCompany', $co->id)
            ->assertSet('newCoName', $co->name)
            ->set('newCoName', 'Renamed Company')
            ->call('saveCompany');

        $this->assertSame('Renamed Company', $co->fresh()->name);
    }

    public function test_deletes_a_company_drops_crews_and_unassigns_members(): void
    {
        $co = Company::first();
        $member = Employee::where('company_id', $co->id)->first();

        Livewire::test(WorkforceApp::class)
            ->call('askDeleteCompany', $co->id)
            ->call('confirmDeleteCompany');

        $this->assertNull(Company::find($co->id));
        $this->assertSame(0, Team::where('company_id', $co->id)->count());
        if ($member) {
            $this->assertNull($member->fresh()->company_id);   // record kept, unassigned
            $this->assertNotNull(Employee::find($member->id));
        }
    }

    public function test_edits_a_crew_name(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('openEditTeam', 't1')
            ->set('newTeamName', 'Renamed Crew')
            ->call('saveTeam');

        $this->assertSame('Renamed Crew', Team::find('t1')->name);
    }

    public function test_deletes_a_crew_and_unassigns_members(): void
    {
        $member = Employee::where('team_id', 't1')->first();

        Livewire::test(WorkforceApp::class)
            ->call('askDeleteTeam', 't1')
            ->call('confirmDeleteTeam');

        $this->assertNull(Team::find('t1'));
        if ($member) {
            $this->assertNull($member->fresh()->team_id);
        }
    }

    public function test_app_renders_with_empty_roster_after_clear(): void
    {
        Punch::query()->delete();
        Employee::query()->delete();
        Team::query()->delete();
        Company::query()->delete();
        Site::query()->delete();

        // login screen and admin dashboard must still render (no null-employee crash)
        Livewire::test(WorkforceApp::class)->assertSet('screen', 'login');
        Livewire::test(WorkforceApp::class)->call('demo', 'admin')->assertSet('screen', 'dashboard');
    }

    public function test_clear_demo_command_wipes_data_but_keeps_admin(): void
    {
        $this->seed(UserSeeder::class);

        $this->artisan('app:clear-demo')->assertExitCode(0);

        $this->assertSame(0, Company::count());
        $this->assertSame(0, Team::count());
        $this->assertSame(0, Employee::count());
        $this->assertSame(0, Punch::count());
        $this->assertNotNull(User::where('email', 'davidcho1973@gmail.com')->first());  // admin kept
        $this->assertNull(User::where('email', 'mkim@nahshon.io')->first());            // demo login removed
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

    /** Advance the mocked clock past the immediate clock-out guard window. */
    private function passClockGuard(): void
    {
        \Illuminate\Support\Carbon::setTestNow(now()->addMinutes(\App\Support\Shift::MIN_OUT_GAP_MIN + 1));
    }

    public function test_worker_clock_persists_status(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'worker')
            ->call('doClock')            // out -> in
            ->assertSet('clock', 'in');
        $this->assertSame('present', Employee::find(106)->status);

        $this->passClockGuard();
        Livewire::test(WorkforceApp::class)
            ->set('clock', 'in')
            ->call('doClock')            // in -> done (clocked out, day locked)
            ->assertSet('clock', 'done');
        $this->assertSame('off', Employee::find(106)->status);
    }

    public function test_worker_cannot_clock_in_again_after_clocking_out(): void
    {
        $today = now()->format('Y-m-d');

        // clock in then out — the day is now complete
        Livewire::test(WorkforceApp::class)->call('demo', 'worker')->call('doClock');
        $this->passClockGuard();
        Livewire::test(WorkforceApp::class)->set('clock', 'in')->call('doClock');

        $p = Punch::where('employee_id', 106)->where('work_date', $today)->first();
        $this->assertNotNull($p->in_min);
        $this->assertNotNull($p->out_min);
        $in = $p->in_min;
        $out = $p->out_min;

        // a stale UI ('out') must NOT reopen the record — punch-based lock wins
        Livewire::test(WorkforceApp::class)
            ->set('clock', 'out')
            ->call('doClock')
            ->assertSet('clock', 'done');

        $p->refresh();
        $this->assertSame($in, $p->in_min);   // clock-in time preserved
        $this->assertSame($out, $p->out_min); // clock-out NOT cleared/overwritten
        $this->assertSame('off', Employee::find(106)->status);
    }

    public function test_qr_scan_locks_after_clock_out(): void
    {
        $this->seed(UserSeeder::class);
        $worker = User::where('email', 'cmartinez@nahshon.io')->first();
        $today = now()->format('Y-m-d');

        $c = Livewire::actingAs($worker)->test(ScanClock::class, ['team' => 't1']);
        $c->call('doClock')->assertSet('clock', 'in');          // in
        $this->passClockGuard();
        $c->call('doClock')->assertSet('clock', 'done');        // out -> done

        $p = Punch::where('employee_id', 106)->where('work_date', $today)->first();
        $in = $p->in_min;
        $out = $p->out_min;
        $this->assertNotNull($in);
        $this->assertNotNull($out);

        // a further scan/tap is a no-op — no reopen, no overwrite
        Livewire::actingAs($worker)->test(ScanClock::class, ['team' => 't1'])
            ->assertSet('clock', 'done')           // mount restores the locked state
            ->call('doClock')
            ->assertSet('clock', 'done');

        $p->refresh();
        $this->assertSame($in, $p->in_min);
        $this->assertSame($out, $p->out_min);
    }

    public function test_desk_clock_locks_after_clock_out(): void
    {
        $today = now()->format('Y-m-d');

        Livewire::test(WorkforceApp::class)->call('demo', 'admin')->call('doDeskClock'); // in
        Livewire::test(WorkforceApp::class)->call('demo', 'admin')->call('doDeskClock'); // out

        $p = Punch::where('employee_id', 103)->where('work_date', $today)->first();
        $in = $p->in_min;
        $out = $p->out_min;
        $this->assertNotNull($in);
        $this->assertNotNull($out);

        // third tap must not reopen the day
        Livewire::test(WorkforceApp::class)->call('demo', 'admin')->call('doDeskClock');

        $p->refresh();
        $this->assertSame($in, $p->in_min);
        $this->assertSame($out, $p->out_min);
        $this->assertSame('off', Employee::find(103)->status);
    }

    public function test_admin_manual_punch_can_correct_a_locked_day(): void
    {
        $today = now()->format('Y-m-d');

        // worker completes the day (locked for the worker/QR/desk paths)
        Livewire::test(WorkforceApp::class)->call('demo', 'worker')->call('doClock');
        $this->passClockGuard();
        Livewire::test(WorkforceApp::class)->set('clock', 'in')->call('doClock');

        $p = Punch::where('employee_id', 106)->where('work_date', $today)->first();
        $this->assertNotNull($p->out_min);

        // admin correction reopens/adjusts the record via manualPunch
        Livewire::test(WorkforceApp::class)->call('demo', 'admin')->call('manualPunch', 106, 'in');

        $p->refresh();
        $this->assertNull($p->out_min);                    // reopened by the admin tool
        $this->assertSame('present', Employee::find(106)->status);
    }

    public function test_admin_can_void_a_punch_so_the_worker_can_clock_in_again(): void
    {
        $today = now()->format('Y-m-d');

        // worker completes the day — record is locked
        Livewire::test(WorkforceApp::class)->call('demo', 'worker')->call('doClock');
        $this->passClockGuard();
        Livewire::test(WorkforceApp::class)->set('clock', 'in')->call('doClock');
        $this->assertNotNull(Punch::where('employee_id', 106)->where('work_date', $today)->first()->out_min);

        // admin voids the mistaken record
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('askVoidPunch', 106)
            ->assertSet('voidPunchId', 106)
            ->call('confirmVoidPunch')
            ->assertSet('voidPunchId', null);

        $this->assertNull(Punch::where('employee_id', 106)->where('work_date', $today)->first());
        $this->assertSame('off', Employee::find(106)->status);

        // the worker can now clock in fresh — the day is no longer locked
        Livewire::test(WorkforceApp::class)->call('demo', 'worker')->call('doClock')->assertSet('clock', 'in');
        $p = Punch::where('employee_id', 106)->where('work_date', $today)->first();
        $this->assertNotNull($p->in_min);
        $this->assertNull($p->out_min);
        $this->assertSame('present', Employee::find(106)->status);
    }

    public function test_worker_cannot_void_a_punch(): void
    {
        $today = now()->format('Y-m-d');
        Livewire::test(WorkforceApp::class)->call('demo', 'worker')->call('doClock');

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'worker')
            ->call('askVoidPunch', 106)
            ->assertSet('voidPunchId', null)   // gate refused
            ->set('voidPunchId', 106)          // even a forged id is re-checked on confirm
            ->call('confirmVoidPunch');

        $this->assertNotNull(Punch::where('employee_id', 106)->where('work_date', $today)->first());
    }

    public function test_early_leave_does_not_reopen_a_locked_day(): void
    {
        $today = now()->format('Y-m-d');

        Livewire::test(WorkforceApp::class)->call('demo', 'worker')->call('doClock'); // in
        $this->passClockGuard();
        Livewire::test(WorkforceApp::class)->set('clock', 'in')->call('doClock');     // out -> locked

        $p = Punch::where('employee_id', 106)->where('work_date', $today)->first();
        $out = $p->out_min;

        Livewire::test(WorkforceApp::class)
            ->set('clock', 'done')
            ->call('submitEarly')
            ->assertSet('clock', 'done');

        $p->refresh();
        $this->assertSame($out, $p->out_min);   // clock-out time untouched
    }

    public function test_password_login_enters_role_view(): void
    {
        $this->seed(UserSeeder::class);
        config(['workforce.demo' => false]);

        Livewire::test(WorkforceApp::class)
            ->set('loginEmail', 'davidcho1973@gmail.com')
            ->set('loginPassword', 'Nahshon!2026')
            ->call('login')
            ->assertSet('role', 'admin')
            ->assertSet('screen', 'dashboard');

        $this->assertAuthenticated();
    }

    public function test_password_login_rejects_bad_credentials(): void
    {
        $this->seed(UserSeeder::class);
        config(['workforce.demo' => false]);

        Livewire::test(WorkforceApp::class)
            ->set('loginEmail', 'davidcho1973@gmail.com')
            ->set('loginPassword', 'wrong-password')
            ->call('login')
            ->assertSet('screen', 'login');

        $this->assertGuest();
    }

    public function test_demo_bypass_is_blocked_outside_demo_mode(): void
    {
        config(['workforce.demo' => false]);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->assertSet('screen', 'login');
    }

    public function test_admin_can_view_all_roles(): void
    {
        $this->seed(UserSeeder::class);
        config(['workforce.demo' => false]);

        Livewire::test(WorkforceApp::class)
            ->set('loginEmail', 'davidcho1973@gmail.com')
            ->set('loginPassword', 'Nahshon!2026')
            ->call('login')
            ->assertSet('access', 'admin')
            // the middle rung is now the field-lead mobile preview (was desktop "manager")
            ->call('viewAs', 'lead')->assertSet('role', 'worker')->assertSet('screen', 'worker')
            ->call('viewAs', 'worker')->assertSet('role', 'worker')->assertSet('screen', 'worker')
            ->call('viewAs', 'admin')->assertSet('role', 'admin')->assertSet('screen', 'dashboard');
    }

    public function test_manager_cannot_view_as_admin(): void
    {
        $this->seed(UserSeeder::class);
        config(['workforce.demo' => false]);

        Livewire::test(WorkforceApp::class)
            ->set('loginEmail', 'mkim@nahshon.io')
            ->set('loginPassword', 'Nahshon!2026')
            ->call('login')
            ->assertSet('access', 'manager')
            ->assertSet('role', 'manager')
            ->call('viewAs', 'worker')->assertSet('role', 'worker')
            ->call('viewAs', 'manager')->assertSet('role', 'manager')
            ->call('viewAs', 'admin')->assertSet('role', 'manager'); // blocked — stays manager
    }

    public function test_worker_can_only_view_worker(): void
    {
        $this->seed(UserSeeder::class);
        config(['workforce.demo' => false]);

        Livewire::test(WorkforceApp::class)
            ->set('loginEmail', 'cmartinez@nahshon.io')
            ->set('loginPassword', 'Nahshon!2026')
            ->call('login')
            ->assertSet('access', 'worker')
            ->assertSet('role', 'worker')
            ->call('viewAs', 'admin')->assertSet('role', 'worker')    // blocked
            ->call('viewAs', 'manager')->assertSet('role', 'worker'); // blocked
    }

    public function test_worker_clock_creates_punch_record(): void
    {
        $today = now()->format('Y-m-d');
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'worker')
            ->call('doClock');   // in

        $p = Punch::where('employee_id', 106)->where('work_date', $today)->first();
        $this->assertNotNull($p);
        $this->assertNotNull($p->in_min);
        $this->assertNull($p->out_min);

        $this->passClockGuard();
        Livewire::test(WorkforceApp::class)
            ->set('clock', 'in')
            ->call('doClock');   // out

        $this->assertNotNull($p->fresh()->out_min);
    }

    public function test_manual_punch_creates_punch_record(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('manualPunch', 112, 'in');

        $p = Punch::where('employee_id', 112)->where('work_date', now()->format('Y-m-d'))->first();
        $this->assertNotNull($p);
        $this->assertNotNull($p->in_min);
    }

    public function test_admin_desk_clock_records_own_punch(): void
    {
        $today = now()->format('Y-m-d');
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('doDeskClock');   // in

        $p = Punch::where('employee_id', 103)->where('work_date', $today)->first();
        $this->assertNotNull($p);
        $this->assertNotNull($p->in_min);
        $this->assertNull($p->out_min);
        $this->assertSame('self', $p->source);
        $this->assertSame('present', Employee::find(103)->status);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('doDeskClock');   // out

        $this->assertNotNull($p->fresh()->out_min);
        $this->assertSame('off', Employee::find(103)->status);
    }

    public function test_daily_timesheet_shows_actual_paid_and_reg(): void
    {
        // completed shift: 6:05 AM in (snaps to 6:00), 3:00 PM out → 8h paid
        Punch::create([
            'employee_id' => 106, 'work_date' => '2026-06-25',
            'in_min' => 365, 'out_min' => 900, 'no_lunch' => false, 'source' => 'seed',
        ]);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'attendance')
            ->set('attView', 'records')
            ->set('attDate', '2026-06-25')
            ->assertSee('Carlos Martínez')
            ->assertSee('6:05 AM')   // actual in
            ->assertSee('6:00 AM')   // paid in (grace-snapped)
            ->assertSee('8.0h');     // regular hours
    }

    public function test_admin_without_linked_employee_can_desk_clock(): void
    {
        $this->seed(UserSeeder::class);
        config(['workforce.demo' => false]);

        $admin = User::where('email', 'davidcho1973@gmail.com')->first();
        $this->assertNull($admin->employee_id);

        Livewire::actingAs($admin)->test(WorkforceApp::class)->call('doDeskClock');

        $admin->refresh();
        $this->assertNotNull($admin->employee_id);            // employee auto-provisioned
        $this->assertSame('present', Employee::find($admin->employee_id)->status);
        $this->assertDatabaseHas('punches', [
            'employee_id' => $admin->employee_id, 'source' => 'self',
        ]);
    }

    public function test_timesheet_export_returns_valid_xlsx(): void
    {
        Punch::create([
            'employee_id' => 106, 'work_date' => '2026-06-25',
            'in_min' => 365, 'out_min' => 900, 'no_lunch' => false, 'source' => 'seed',
        ]);

        $res = $this->get('/export/timesheet?date=2026-06-25&site=all&lang=en');

        $res->assertOk();
        $res->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringStartsWith('PK', $res->getContent()); // zip magic
    }

    public function test_timesheet_includes_managers_who_clock_in(): void
    {
        $mgr = Employee::create([
            'emp_id' => 'N-MGR01', 'first' => 'Jane', 'last' => 'Boss',
            'type' => 'manager', 'access' => 'manager', 'emp' => 'active',
            'company_id' => 'c1', 'team_id' => 't1', 'site_id' => 's1', 'rate' => 50,
        ]);
        Punch::create([
            'employee_id' => $mgr->id, 'work_date' => '2026-06-25',
            'in_min' => 360, 'out_min' => 900, 'no_lunch' => false, 'source' => 'self',
        ]);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'attendance')
            ->set('attView', 'records')
            ->set('attDate', '2026-06-25')
            ->assertSee('Jane Boss');   // manager now appears on the attendance sheet
    }

    public function test_worker_has_no_desk_clock(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'worker')
            ->call('doDeskClock');   // no-op: workers have no self-clock desk control

        $this->assertSame(0, Punch::where('source', 'self')->count());
    }

    public function test_print_voucher_records_payment(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('openPayDetail', 106)
            ->call('openVoucher')
            ->set('checkNo', '100482')
            ->call('printVoucher');

        $pay = Payment::where('employee_id', 106)->first();
        $this->assertNotNull($pay);
        $this->assertSame('100482', $pay->check_no);
        $this->assertGreaterThan(0, $pay->amount);
    }

    public function test_payroll_badge_lookup_by_employee_id_opens_history(): void
    {
        // employee 106 (Carlos) is seeded with emp_id HOF-AZ-100402
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->set('badgeLookup', 'hof-az-100402')   // case-insensitive
            ->call('findByBadge')
            ->assertSet('payDetail', 106)            // attendance-history drawer opens
            ->assertSet('badgeLookup', '');          // box clears
    }

    public function test_payroll_badge_lookup_by_qr_number_opens_history(): void
    {
        Employee::where('id', 106)->update(['badge_qr' => 'CS-00102810']);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->set('badgeLookup', 'CS-00102810')
            ->call('findByBadge')
            ->assertSet('payDetail', 106);
    }

    public function test_payroll_badge_lookup_by_raw_nfc_uid_opens_history(): void
    {
        // register a worker via badge (emp_id derived from the NFC UID)
        Livewire::test(WorkforceApp::class)
            ->call('addWorker')
            ->set('regFirst', 'Nfc')->set('regLast', 'Tag')
            ->set('regTeam', 't2')
            ->set('nfcUidManual', 'AB:12:CD:34:EF:56:78')
            ->call('finishBadge');
        $e = Employee::where('first', 'Nfc')->where('last', 'Tag')->first();
        $this->assertSame('N-D34EF5678', $e->emp_id);

        // scanning the raw UID resolves to the same employee id
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->set('badgeLookup', 'AB:12:CD:34:EF:56:78')
            ->call('findByBadge')
            ->assertSet('payDetail', $e->id);
    }

    public function test_payroll_badge_lookup_unknown_is_a_noop(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->set('badgeLookup', 'NOPE-999')
            ->call('findByBadge')
            ->assertSet('payDetail', null);
    }

    public function test_badge_wizard_uses_typed_values_and_manual_uid(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('addWorker')
            ->set('regFirst', 'Luis')
            ->set('regLast', 'Ramírez')
            ->set('regRoleTitle', 'Welder')
            ->set('regRate', '29.50')
            ->set('regTeam', 't3')
            ->set('nfcUidManual', 'AB:12:CD:34:EF:56:78')
            ->call('finishBadge')
            ->assertSet('screen', 'employees');

        $e = Employee::where('first', 'Luis')->where('last', 'Ramírez')->first();
        $this->assertNotNull($e);
        $this->assertSame('N-D34EF5678', $e->emp_id);
        $this->assertSame(29.5, $e->rate);
    }

    public function test_badge_wizard_requires_uid(): void
    {
        $before = Employee::count();
        Livewire::test(WorkforceApp::class)
            ->call('addWorker')
            ->set('regFirst', 'Ana')
            ->set('regLast', 'Lopez')
            ->call('finishBadge')
            ->assertSet('screen', 'badge'); // stays in the wizard

        $this->assertSame($before, Employee::count());
    }

    public function test_badge_photo_analysis_fills_fields_from_gemini(): void
    {
        config(['services.gemini.key' => 'test-key']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'company' => 'AUTORICA LLC', 'last' => 'LEE', 'first' => 'JAEWOO',
                            'role' => 'SUPERVISOR', 'issued' => '03/04/2026',
                        ]),
                    ]]],
                ]],
            ]),
        ]);

        Livewire::test(WorkforceApp::class)
            ->call('addWorker')
            ->set('badgePhoto', UploadedFile::fake()->image('badge.jpg', 800, 1100))
            ->call('analyzeBadge')
            ->assertSet('scanF', 'done')
            ->assertSet('regCoName', 'AUTORICA LLC')
            ->assertSet('regLast', 'LEE')
            ->assertSet('regFirst', 'JAEWOO')
            ->assertSet('regRoleTitle', 'SUPERVISOR')
            ->assertSet('regIssued', '03/04/2026');
    }

    public function test_badge_analysis_shows_whole_badge_photo(): void
    {
        config(['services.gemini.key' => 'test-key']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'company' => 'AUTORICA LLC', 'last' => 'LEE', 'first' => 'JAEWOO',
                            'role' => 'SUPERVISOR', 'issued' => '03/04/2026',
                        ]),
                    ]]],
                ]],
            ]),
        ]);

        Livewire::test(WorkforceApp::class)
            ->call('addWorker')
            ->set('badgePhoto', UploadedFile::fake()->image('badge.jpg', 800, 1100))
            ->call('analyzeBadge')
            ->assertSet('scanF', 'done')
            // downscaled photo captured as a data URI, shown whole (contain)
            ->assertSeeHtml('data:image/jpeg;base64,')
            ->assertSeeHtml('background-size:contain');
    }

    public function test_badge_register_saves_photo_and_drawer_shows_it(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('addWorker')
            ->set('regFirst', 'Foto')
            ->set('regLast', 'Persona')
            ->set('regTeam', 't3')
            ->set('facePhotoData', 'data:image/jpeg;base64,ZZZZ')
            ->set('nfcUidManual', '11:22:33:44:55:66')
            ->call('finishBadge');

        $e = Employee::where('first', 'Foto')->where('last', 'Persona')->first();
        $this->assertNotNull($e);
        $this->assertSame('data:image/jpeg;base64,ZZZZ', $e->badge_photo);

        // the detail drawer renders the stored photo
        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('go', 'employees')
            ->call('selectEmp', $e->id)
            ->assertSeeHtml('data:image/jpeg;base64,ZZZZ');
    }

    public function test_rotate_badge_photo_turns_it_90_degrees(): void
    {
        // a 400x100 landscape image stored as the badge photo
        $im = imagecreatetruecolor(400, 100);
        ob_start();
        imagejpeg($im);
        $bytes = (string) ob_get_clean();
        imagedestroy($im);

        $emp = Employee::create([
            'emp_id' => 'N-ROT01', 'first' => 'Rot', 'last' => 'Test',
            'type' => 'manager', 'access' => 'manager', 'emp' => 'active', 'rate' => 0,
            'badge_photo' => 'data:image/jpeg;base64,'.base64_encode($bytes),
        ]);

        Livewire::test(WorkforceApp::class)
            ->call('demo', 'admin')
            ->call('selectEmp', $emp->id)
            ->call('rotateBadgePhoto');

        preg_match('#base64,(.+)$#', $emp->fresh()->badge_photo, $m);
        $rot = imagecreatefromstring(base64_decode($m[1]));
        $this->assertSame(100, imagesx($rot));  // width and height swapped → now portrait
        $this->assertSame(400, imagesy($rot));
    }

    public function test_badge_analysis_without_photo_falls_back_to_simulation(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('addWorker')
            ->call('analyzeBadge')
            ->assertSet('scanF', 'scanning');
    }

    public function test_back_qr_capture_saves_badge_qr_on_new_employee(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('addWorker')
            ->set('regFirst', 'Marco')
            ->set('regLast', 'Reyes')
            ->set('regTeam', 't2')
            ->set('nfcUidManual', '01:02:03:04:05:06:07')
            ->call('captureBackQr', 'CS-00102810')
            ->assertSet('scanB', 'done')
            ->assertSet('backQrValue', 'CS-00102810')
            ->call('finishBadge')
            ->assertSet('screen', 'employees');

        $e = Employee::where('first', 'Marco')->where('last', 'Reyes')->first();
        $this->assertNotNull($e);
        $this->assertSame('CS-00102810', $e->badge_qr);
    }

    public function test_empty_back_qr_is_ignored(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('addWorker')
            ->call('captureBackQr', '   ')
            ->assertSet('scanB', 'idle');
    }

    public function test_back_qr_ai_fallback_reads_printed_code(): void
    {
        config(['services.gemini.key' => 'test-key']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode(['code' => '00102810'])]]],
                ]],
            ]),
        ]);

        Livewire::test(WorkforceApp::class)
            ->call('addWorker')
            ->call('toBack')
            ->set('backQrPhoto', UploadedFile::fake()->image('back.jpg', 800, 1100))
            ->call('analyzeBackQr')
            ->assertSet('scanB', 'done')
            ->assertSet('backQrValue', '00102810');
    }

    public function test_back_qr_ai_fallback_without_photo_toasts(): void
    {
        Livewire::test(WorkforceApp::class)
            ->call('addWorker')
            ->call('toBack')
            ->call('analyzeBackQr')
            ->assertSet('scanB', 'idle');
    }

    public function test_scan_route_redirects_guests_to_login(): void
    {
        $this->get('/scan/t1')->assertRedirect('/');
    }

    public function test_scanning_crew_qr_lets_worker_clock_in(): void
    {
        $this->seed(UserSeeder::class);
        $worker = User::where('email', 'cmartinez@nahshon.io')->first();

        Livewire::actingAs($worker)
            ->test(ScanClock::class, ['team' => 't1'])
            ->assertSet('clock', 'out')
            ->call('doClock')
            ->assertSet('clock', 'in');

        $p = Punch::where('employee_id', 106)->where('work_date', now()->format('Y-m-d'))->first();
        $this->assertNotNull($p);
        $this->assertSame('qr', $p->source);
        $this->assertNotNull($p->in_min);
    }

    public function test_login_returns_to_intended_scan_url(): void
    {
        $this->seed(UserSeeder::class);
        config(['workforce.demo' => false]);
        // guest hits a scan link -> stores intended, lands on login
        $this->get('/scan/t2')->assertRedirect('/');

        Livewire::withQueryParams([])
            ->test(WorkforceApp::class)
            ->set('loginEmail', 'cmartinez@nahshon.io')
            ->set('loginPassword', 'Nahshon!2026')
            ->call('login')
            ->assertRedirect(url('/scan/t2'));
    }
}
