<?php

namespace Tests\Feature;

use App\Livewire\WorkforceApp;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Payment;
use App\Models\Punch;
use App\Livewire\ScanClock;
use App\Models\Site;
use App\Models\Team;
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
        $worker = \App\Models\User::where('email', 'cmartinez@nahshon.io')->first();

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
