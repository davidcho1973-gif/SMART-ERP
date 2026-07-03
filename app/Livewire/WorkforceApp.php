<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Payment;
use App\Models\Punch;
use App\Models\Site;
use App\Models\Team;
use App\Support\Money;
use App\Support\Payroll;
use App\Support\Qr;
use App\Support\Shift;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class WorkforceApp extends Component
{
    use WithFileUploads;

    // ---- primary navigation / UI state ----
    public string $screen = 'login';
    public string $role = 'admin';
    /** the account's access ceiling — the highest view it may switch to */
    public string $access = 'admin';
    public string $lang = 'en';
    public string $dashLayout = 'A';
    public string $site = 'all';

    // ---- employees ----
    public ?int $selectedEmp = null;
    public string $empFilter = 'active';
    public string $teamFilter = 'all';
    public string $search = '';
    public ?int $deleteId = null;
    public ?int $terminateId = null;
    /** working copy of the selected employee's editable fields */
    public array $editForm = [];

    // ---- login form ----
    public string $loginEmail = '';
    public string $loginPassword = '';

    // ---- badge wizard ----
    public string $bstep = 'front';
    public string $scanF = 'idle';
    public string $scanB = 'idle';
    public string $scanN = 'idle';
    public string $regTeam = 't1';
    public string $regType = 'worker_local';
    public string $regAccess = 'worker';
    // real registration inputs (prefilled by the demo OCR, always editable)
    public string $regFirst = '';
    public string $regLast = '';
    public string $regCoName = '';
    public string $regRoleTitle = '';
    public string $regIssued = '';
    public string $regRate = '';
    public string $regPhone = '';
    public string $regEmail = '';
    /** NFC UID captured by Web NFC or typed manually */
    public string $nfcUidManual = '';
    /** uploaded badge-front photo (camera or file) */
    public $badgePhoto = null;
    /** decoded value of the back-of-badge QR code */
    public string $backQrValue = '';
    /** uploaded badge-back photo */
    public $backQrPhoto = null;
    /** show the manual code-entry field */
    public bool $backManual = false;

    // ---- projects modals ----
    public bool $companyModal = false;
    public ?string $teamModal = null;
    public string $newCoName = '';
    public string $newCoSite = '';
    public string $newTeamName = '';
    public string $newTeamLead = '';
    public ?string $editCompanyId = null;   // company modal in edit mode
    public ?string $editTeamId = null;       // team modal in edit mode
    public ?string $deleteCompanyId = null;  // pending company deletion
    public ?string $deleteTeamId = null;     // pending team deletion

    // ---- attendance ----
    public string $attView = 'records';   // records | qr
    public string $attDate = '';          // Y-m-d for the daily timesheet (set in mount)
    public string $qrMode = 'reader';
    public string $qrTeam = 't1';

    // ---- payroll ----
    public ?int $payDetail = null;
    public bool $payVoucher = false;
    public string $checkNo = '';
    public string $payDate = 'Jul 1, 2026';

    // ---- worker mobile ----
    public string $mobileTab = 'home';
    public string $clock = 'out';
    public string $clockInTime = '6:58 AM';
    public bool $earlyOpen = false;
    public string $earlyReasonVal = '';
    public string $earlyCustom = '';
    public bool $noLunchToday = false;
    /** per-date lunch overrides keyed by day label */
    public array $lunchOv = [];

    // ---- toast ----
    public ?string $toast = null;

    protected const NFC_UID = '04:73:AC:2F:19:B4:5E';

    // =================== helpers ===================

    /** Whole translation dictionary for the current language. */
    protected function dict(): array
    {
        return (array) trans('app', [], $this->lang);
    }

    /** Inline trilingual string. */
    protected function tl(string $en, string $es, string $ko): string
    {
        return $this->lang === 'ko' ? $ko : ($this->lang === 'es' ? $es : $en);
    }

    /** The UID currently in play: manual/Web-NFC input wins, else the simulated tag. */
    protected function currentUid(): ?string
    {
        $manual = trim($this->nfcUidManual);
        if ($manual !== '') {
            return $manual;
        }
        return $this->scanN === 'done' ? self::NFC_UID : null;
    }

    /** N- + last 9 hex/alnum chars of a UID. */
    protected function nfcId(?string $uid = null): string
    {
        $uid = $uid ?? self::NFC_UID;
        $hex = preg_replace('/[^0-9A-Za-z]/', '', $uid);
        return 'N-' . strtoupper(substr($hex, -9));
    }

    protected function isDemo(): bool
    {
        return (bool) config('workforce.demo');
    }

    /** Mutating actions require demo mode or an authenticated admin/manager. */
    protected function canManage(): bool
    {
        return $this->isDemo()
            || (Auth::check() && in_array(Auth::user()->access, ['admin', 'manager'], true));
    }

    /** The employee behind the worker-mobile view. */
    protected function meEmployeeId(): int
    {
        // any authenticated user linked to an employee sees their own record
        if (! $this->isDemo() && Auth::check() && Auth::user()->employee_id) {
            return (int) Auth::user()->employee_id;
        }
        return 106; // sample worker (Carlos) — demo & admins with no linked employee
    }

    /** The employee to clock for from the desktop (admin/manager's own record). */
    protected function selfEmployeeId(): ?int
    {
        if (! $this->isDemo() && Auth::check()) {
            return Auth::user()->employee_id;
        }
        // demo personas map to a manager employee so the control is usable
        return match ($this->role) {
            'manager' => 101,
            'admin' => 103,
            default => null,
        };
    }

    /** Whether the current user may clock themselves in/out from the desktop. */
    protected function canDeskClock(): bool
    {
        if ($this->isDemo()) {
            return $this->role !== 'worker';
        }

        return Auth::check() && in_array(Auth::user()->access, ['admin', 'manager'], true);
    }

    /**
     * Resolve (or create) the employee record for the signed-in admin/manager so
     * they can clock in — admins without a roster record get one provisioned.
     */
    protected function ensureSelfEmployee(): ?int
    {
        if ($this->isDemo()) {
            return $this->selfEmployeeId();
        }
        if (! Auth::check() || ! in_array(Auth::user()->access, ['admin', 'manager'], true)) {
            return null;
        }
        $u = Auth::user();
        if ($u->employee_id && Employee::find($u->employee_id)) {
            return (int) $u->employee_id;
        }

        $parts = preg_split('/\s+/', trim($u->name)) ?: [];
        $emp = Employee::create([
            'emp_id' => 'STAFF-' . str_pad((string) $u->id, 4, '0', STR_PAD_LEFT),
            'first' => $parts[0] ?? $u->name,
            'last' => isset($parts[1]) ? implode(' ', array_slice($parts, 1)) : '',
            'type' => 'manager',                 // staff, not a field worker (kept out of the worker timesheet)
            'access' => $u->access,
            'role' => $u->access === 'admin' ? 'Administrator' : 'Site Manager',
            'email' => $u->email,
            'company_id' => Company::first()?->id,
            'team_id' => Team::first()?->id,
            'site_id' => Site::first()?->id,
            'lang' => $this->lang,
            'emp' => 'active',
        ]);
        $u->update(['employee_id' => $emp->id]);

        return $emp->id;
    }

    /** Clock the current admin/manager in or out (records a real punch). */
    public function doDeskClock(): void
    {
        if (! $this->canDeskClock()) {
            return;
        }
        $eid = $this->ensureSelfEmployee();
        if (! $eid) {
            return;
        }
        $emp = Employee::find($eid);
        if (! $emp) {
            return;
        }
        $nowMin = (int) now()->format('H') * 60 + (int) now()->format('i');
        $p = $this->todayPunch($eid);
        $d = $this->dict();
        $open = $p->exists && $p->in_min !== null && $p->out_min === null;
        if (! $open) {
            $p->in_min = $nowMin;
            $p->out_min = null;
            $p->source = 'self';
            $p->save();
            $emp->update(['status' => 'present', 'in_t' => Shift::fmtMin($nowMin)]);
            $this->showToast($d['w_done_in']);
        } else {
            $p->out_min = $nowMin;
            $p->source = 'self';
            $p->save();
            $emp->update(['status' => 'off', 'out_t' => Shift::fmtMin($nowMin)]);
            $this->showToast($d['w_done_out']);
        }
    }

    protected function todayPunch(int $employeeId): Punch
    {
        return Punch::firstOrNew([
            'employee_id' => $employeeId,
            'work_date' => now()->format('Y-m-d'),
        ]);
    }

    protected function showToast(string $msg): void
    {
        $this->toast = $msg;
    }

    public function clearToast(): void
    {
        $this->toast = null;
    }

    // =================== navigation / auth ===================

    public function mount(): void
    {
        $this->attDate = now()->format('Y-m-d');
        if (Auth::check()) {
            $this->applyUser();
        }
        if (request()->query('auth') === 'denied') {
            $this->showToast($this->dict()['a_denied']);
        } elseif (request()->query('auth') === 'failed') {
            $this->showToast($this->dict()['a_bad']);
        } elseif (request()->query('auth') === 'unconfigured') {
            $this->showToast($this->dict()['a_googleOff']);
        }
    }

    /** Enter the app as the authenticated user (role, language, landing screen). */
    protected function applyUser(): void
    {
        $u = Auth::user();
        $this->access = $u->access;
        if ($u->access === 'worker') {
            $this->role = 'worker';
            $this->screen = 'worker';
            $this->mobileTab = 'home';
            $emp = $u->employee_id ? Employee::find($u->employee_id) : null;
            $this->lang = $emp->lang ?? 'es';
            // restore today's clock state from the punch record
            $p = Punch::where('employee_id', $u->employee_id)
                ->where('work_date', now()->format('Y-m-d'))->first();
            if ($p && $p->in_min !== null && $p->out_min === null) {
                $this->clock = 'in';
                $this->clockInTime = Shift::fmtMin($p->in_min);
                $this->noLunchToday = $p->no_lunch;
            } else {
                $this->clock = 'out';
            }
        } else {
            $this->role = $u->access === 'admin' ? 'admin' : 'manager';
            $this->screen = 'dashboard';
            $this->lang = $u->access === 'manager' ? 'ko' : 'en';
        }
    }

    public function login()
    {
        $email = trim($this->loginEmail);
        if ($email === '' || $this->loginPassword === '') {
            $this->showToast($this->dict()['a_bad']);
            return;
        }
        if (! Auth::attempt(['email' => $email, 'password' => $this->loginPassword], true)) {
            $this->showToast($this->dict()['a_bad']);
            return;
        }
        if (request()->hasSession()) {
            request()->session()->regenerate();
        }
        $this->loginPassword = '';
        $this->applyUser();

        if (($intended = session()->pull('url.intended')) && $intended !== url('/')) {
            return $this->redirect($intended);
        }
        return null;
    }

    /** Rank of a role for the access hierarchy (higher = more access). */
    protected function roleRank(string $r): int
    {
        return ['worker' => 1, 'manager' => 2, 'admin' => 3][$r] ?? 0;
    }

    /**
     * Switch the active view within the account's access ceiling.
     * Admin → admin/manager/worker · manager → manager/worker · worker → worker only.
     */
    public function viewAs(string $target): void
    {
        if (! in_array($target, ['admin', 'manager', 'worker'], true)) {
            return;
        }
        // never let a view exceed the account's own access level
        if ($this->roleRank($target) > $this->roleRank($this->access)) {
            return;
        }
        if ($target === 'worker') {
            $this->selectedEmp = null;
            $this->role = 'worker';
            $this->screen = 'worker';
            $this->mobileTab = 'home';
        } else {
            $this->role = $target;
            if (in_array($this->screen, ['worker', 'login'], true)) {
                $this->screen = 'dashboard';
            }
            // managers never see payroll
            if ($target === 'manager' && $this->screen === 'payroll') {
                $this->screen = 'dashboard';
            }
        }
    }

    public function setRole(string $r): void
    {
        if (! $this->isDemo()) {
            return; // in real mode the role comes from the authenticated account
        }
        $this->access = $r; // demo persona sets the access ceiling
        if ($r === 'worker') {
            $this->reset(['selectedEmp']);
            $this->role = 'worker';
            $this->screen = 'worker';
            $this->mobileTab = 'home';
            $this->lang = 'es';
        } elseif ($r === 'manager') {
            $this->role = 'manager';
            $this->screen = 'dashboard';
            $this->lang = 'ko';
        } else {
            $this->role = 'admin';
            $this->screen = 'dashboard';
            $this->lang = 'en';
        }
    }

    public function setLang(string $l): void
    {
        $this->lang = $l;
    }

    public function go(string $screen): void
    {
        // managers never see payroll; workers never see desktop screens
        if ($this->role === 'manager' && $screen === 'payroll') {
            return;
        }
        if ($this->role === 'worker' && $screen !== 'worker') {
            return;
        }
        $this->screen = $screen;
        $this->selectedEmp = null;
        $this->bstep = 'front';
        $this->scanF = $this->scanB = $this->scanN = 'idle';
    }

    public function setDash(string $l): void
    {
        $this->dashLayout = $l;
    }

    public function setMobileTab(string $k): void
    {
        $this->mobileTab = $k;
    }

    /** Demo-mode stand-in for the Google button (real mode links to /auth/google/redirect). */
    public function googleSignIn(): void
    {
        if (! $this->isDemo()) {
            return;
        }
        $this->role = 'admin';
        $this->screen = 'dashboard';
        $this->showToast($this->dict()['googleBtn']);
    }

    public function demo(string $role): void
    {
        if (! $this->isDemo()) {
            return;
        }
        $this->setRole($role);
    }

    public function logout()
    {
        if (Auth::check()) {
            Auth::logout();
            if (request()->hasSession()) {
                request()->session()->invalidate();
                request()->session()->regenerateToken();
            }
            return $this->redirect('/');
        }
        $this->screen = 'login';
        return null;
    }

    // =================== employees ===================

    public function setEmpFilter(string $f): void
    {
        $this->empFilter = $f;
    }

    public function setTeamFilter(string $t): void
    {
        $this->teamFilter = $t;
    }

    public function selectEmp(int $id): void
    {
        $e = Employee::find($id);
        if (! $e) {
            return;
        }
        $this->selectedEmp = $id;
        $this->editForm = [
            'first' => $e->first, 'last' => $e->last, 'company' => $e->company_id,
            'team' => $e->team_id, 'role' => $e->role, 'rate' => $e->rate,
            'type' => $e->type === 'manager' ? 'manager' : ($e->lang === 'ko' ? 'worker_ko' : 'worker_local'),
            'issued' => $e->issued, 'phone' => $e->phone, 'email' => $e->email,
            'nat' => $e->nat, 'access' => $e->access,
        ];
    }

    public function closeDetail(): void
    {
        $this->selectedEmp = null;
    }

    public function setFormAccess(string $lvl): void
    {
        $this->editForm['access'] = $lvl;
    }

    public function addWorker(): void
    {
        $this->screen = 'badge';
        $this->bstep = 'front';
        $this->scanF = $this->scanB = $this->scanN = 'idle';
    }

    public function saveEmp(): void
    {
        if (! $this->canManage()) {
            return;
        }
        $e = Employee::find($this->selectedEmp);
        if ($e) {
            $type = $this->editForm['type'] ?? 'worker_local';
            $company = $this->editForm['company'] ?? $e->company_id;
            $e->update([
                'first' => $this->editForm['first'] ?? $e->first,
                'last' => $this->editForm['last'] ?? $e->last,
                'company_id' => $company,
                'site_id' => optional(Company::find($company))->site_id ?? $e->site_id,
                'team_id' => $this->editForm['team'] ?? $e->team_id,
                'role' => $this->editForm['role'] ?? $e->role,
                'rate' => (float) ($this->editForm['rate'] ?? $e->rate),
                'type' => $type === 'manager' ? 'manager' : 'worker',
                'lang' => $type === 'worker_local' ? 'es' : 'ko',
                'issued' => $this->editForm['issued'] ?? $e->issued,
                'phone' => $this->editForm['phone'] ?? $e->phone,
                'email' => $this->editForm['email'] ?? $e->email,
                'nat' => $this->editForm['nat'] ?? $e->nat,
                'access' => $this->editForm['access'] ?? $e->access,
            ]);
        }
        $this->selectedEmp = null;
        $this->showToast($this->dict()['e_save'] . ' ✓');
    }

    public function askDelete(int $id): void
    {
        $this->deleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->deleteId = null;
    }

    public function confirmDelete(): void
    {
        if (! $this->canManage()) {
            return;
        }
        Employee::where('id', $this->deleteId)->delete();
        $this->deleteId = null;
        $this->selectedEmp = null;
        $this->showToast($this->dict()['e_delete'] . ' ✓');
    }

    public function askTerm(int $id): void
    {
        $this->terminateId = $id;
    }

    public function cancelTerm(): void
    {
        $this->terminateId = null;
    }

    public function confirmTerm(): void
    {
        if (! $this->canManage()) {
            return;
        }
        Employee::where('id', $this->terminateId)->update([
            'emp' => 'terminated', 'term' => '07/01/2026', 'access' => 'worker', 'status' => 'off',
        ]);
        $this->terminateId = null;
        $this->selectedEmp = null;
        $this->showToast($this->dict()['e_terminate'] . ' ✓');
    }

    public function reactivate(int $id): void
    {
        if (! $this->canManage()) {
            return;
        }
        Employee::where('id', $id)->update(['emp' => 'active', 'term' => null]);
        $this->showToast($this->dict()['e_reactivated']);
    }

    // =================== projects ===================

    public function openCompanyModal(): void
    {
        $this->editCompanyId = null;
        $this->companyModal = true;
        $this->newCoName = '';
        $this->newCoSite = '';
    }

    public function openEditCompany(string $id): void
    {
        $co = Company::find($id);
        if (! $co) {
            return;
        }
        $site = Site::find($co->site_id);
        $this->editCompanyId = $id;
        $this->newCoName = $co->name;
        $this->newCoSite = $site ? trim($site->name . ($site->city ? ' · ' . $site->city : '')) : '';
        $this->companyModal = true;
    }

    public function cancelCompany(): void
    {
        $this->companyModal = false;
        $this->editCompanyId = null;
    }

    public function saveCompany(): void
    {
        if (! $this->canManage()) {
            return;
        }
        $name = trim($this->newCoName);
        $siteName = trim($this->newCoSite);
        if ($name === '' || $siteName === '') {
            return;
        }
        $site = Site::all()->first(function ($s) use ($siteName) {
            return strcasecmp($s->name . ' · ' . $s->city, $siteName) === 0
                || strcasecmp($s->name, $siteName) === 0;
        });
        if (! $site) {
            $site = Site::create(['id' => 's' . Str::random(6), 'name' => $siteName, 'city' => '', 'gc' => 'Hoffman', 'code' => '']);
        }
        if ($this->editCompanyId) {
            Company::where('id', $this->editCompanyId)->update(['name' => $name, 'site_id' => $site->id]);
            $this->showToast($this->dict()['pj_saved'] . ' ✓');
        } else {
            Company::create(['id' => 'c' . Str::random(6), 'name' => $name, 'site_id' => $site->id]);
            $this->showToast(str_replace('+ ', '', $this->dict()['pj_create']) . ' ✓');
        }
        $this->companyModal = false;
        $this->editCompanyId = null;
    }

    public function askDeleteCompany(string $id): void
    {
        $this->deleteCompanyId = $id;
    }

    public function cancelDeleteCompany(): void
    {
        $this->deleteCompanyId = null;
    }

    public function confirmDeleteCompany(): void
    {
        if (! $this->canManage() || ! $this->deleteCompanyId) {
            return;
        }
        $id = $this->deleteCompanyId;
        // unassign members and drop the company's crews
        Employee::where('company_id', $id)->update(['company_id' => null, 'team_id' => null]);
        Team::where('company_id', $id)->delete();
        Company::where('id', $id)->delete();
        $this->deleteCompanyId = null;
        $this->showToast($this->dict()['pj_deleted'] . ' ✓');
    }

    public function openTeamModal(string $companyId): void
    {
        $this->editTeamId = null;
        $this->teamModal = $companyId;
        $this->newTeamName = '';
        $first = Employee::where('type', 'manager')->where('emp', 'active')->orderBy('id')->first();
        $this->newTeamLead = $first ? (string) $first->id : '';
    }

    public function openEditTeam(string $teamId): void
    {
        $t = Team::find($teamId);
        if (! $t) {
            return;
        }
        $this->editTeamId = $teamId;
        $this->teamModal = $t->company_id;
        $this->newTeamName = $t->name;
        $this->newTeamLead = $t->lead !== null ? (string) $t->lead : '';
    }

    public function cancelTeam(): void
    {
        $this->teamModal = null;
        $this->editTeamId = null;
    }

    public function saveTeam(): void
    {
        if (! $this->canManage()) {
            return;
        }
        if (trim($this->newTeamName) === '' || ! $this->teamModal) {
            return;
        }
        if ($this->editTeamId) {
            Team::where('id', $this->editTeamId)->update([
                'name' => trim($this->newTeamName),
                'lead' => $this->newTeamLead !== '' ? (int) $this->newTeamLead : null,
            ]);
            $this->showToast($this->dict()['pj_saved'] . ' ✓');
        } else {
            $cols = ['#3B72E0', '#1F9D6B', '#E85D2A', '#D9483B', '#8A5CF6', '#0EA5A0'];
            $count = Team::count();
            Team::create([
                'id' => 't' . Str::random(6),
                'name' => trim($this->newTeamName),
                'company_id' => $this->teamModal,
                'lead' => $this->newTeamLead !== '' ? (int) $this->newTeamLead : null,
                'color' => $cols[$count % count($cols)],
            ]);
            $this->showToast(str_replace('+ ', '', $this->dict()['pj_newTeam']) . ' ✓');
        }
        $this->teamModal = null;
        $this->editTeamId = null;
    }

    public function askDeleteTeam(string $id): void
    {
        $this->deleteTeamId = $id;
    }

    public function cancelDeleteTeam(): void
    {
        $this->deleteTeamId = null;
    }

    public function confirmDeleteTeam(): void
    {
        if (! $this->canManage() || ! $this->deleteTeamId) {
            return;
        }
        Employee::where('team_id', $this->deleteTeamId)->update(['team_id' => null]);
        Team::where('id', $this->deleteTeamId)->delete();
        $this->deleteTeamId = null;
        $this->showToast($this->dict()['pj_deleted'] . ' ✓');
    }

    public function changeLead(string $teamId, string $leadId): void
    {
        if (! $this->canManage()) {
            return;
        }
        Team::where('id', $teamId)->update(['lead' => (int) $leadId]);
    }

    // =================== badge wizard ===================

    public function startScanF(): void
    {
        $this->scanF = 'scanning';
    }

    /** Analyze the uploaded badge photo with Gemini and fill the extracted fields. */
    public function analyzeBadge(): void
    {
        if (! $this->badgePhoto) {
            // no photo -> run the simulated scan animation instead
            $this->startScanF();
            return;
        }
        $this->validate(['badgePhoto' => 'image|max:10240']);

        $analyzer = app(\App\Services\BadgeAnalyzer::class);
        if (! $analyzer->isConfigured()) {
            $this->showToast($this->dict()['b_aiOff']);
            return;
        }

        $result = $analyzer->analyzeFront(
            file_get_contents($this->badgePhoto->getRealPath()),
            $this->badgePhoto->getMimeType() ?: 'image/jpeg'
        );

        if ($result === null) {
            $this->showToast($this->dict()['b_aiFail']);
            return;
        }

        $this->regCoName = $result['company'] !== '' ? $result['company'] : $this->regCoName;
        $this->regLast = $result['last'] !== '' ? $result['last'] : $this->regLast;
        $this->regFirst = $result['first'] !== '' ? $result['first'] : $this->regFirst;
        $this->regRoleTitle = $result['role'] !== '' ? $result['role'] : $this->regRoleTitle;
        $this->regIssued = $result['issued'] !== '' ? $result['issued'] : $this->regIssued;
        $this->scanF = 'done';
        $this->showToast($this->dict()['b_aiDone']);
    }

    public function finishScanF(): void
    {
        if ($this->scanF === 'scanning') {
            $this->scanF = 'done';
            // prefill blank fields with the simulated OCR result; user can overwrite
            $this->regCoName = $this->regCoName !== '' ? $this->regCoName : 'Sonoran MEP';
            $this->regLast = $this->regLast !== '' ? $this->regLast : 'Martínez';
            $this->regFirst = $this->regFirst !== '' ? $this->regFirst : 'Carlos';
            $this->regRoleTitle = $this->regRoleTitle !== '' ? $this->regRoleTitle : 'Electrician';
            $this->regIssued = $this->regIssued !== '' ? $this->regIssued : '03/14/2026';
        }
    }

    public function rescanF(): void
    {
        $this->scanF = 'idle';
        $this->badgePhoto = null;
    }

    public function toBack(): void
    {
        $this->bstep = 'back';
    }

    public function toAssign(): void
    {
        $this->bstep = 'assign';
    }

    public function backToFront(): void
    {
        $this->bstep = 'front';
    }

    public function backToBack(): void
    {
        $this->bstep = 'back';
    }

    public function startScanB(): void
    {
        $this->scanB = 'scanning';
    }

    public function finishScanB(): void
    {
        if ($this->scanB === 'scanning') {
            $this->scanB = 'done';
        }
    }

    /** Called from JS after decoding the back-QR client-side. */
    public function captureBackQr(string $code): void
    {
        $code = trim($code);
        if ($code === '') {
            return;
        }
        $this->backQrValue = $code;
        $this->scanB = 'done';
        $this->showToast($this->dict()['b_qrCaptured']);
    }

    public function rescanBack(): void
    {
        $this->scanB = 'idle';
        $this->backQrValue = '';
        $this->backQrPhoto = null;
        $this->backManual = false;
    }

    public function toggleBackManual(): void
    {
        $this->backManual = ! $this->backManual;
    }

    /** Server-side fallback: Gemini reads the code printed under the QR. */
    public function analyzeBackQr(): void
    {
        if (! $this->backQrPhoto) {
            $this->showToast($this->dict()['b_qrFail']);
            return;
        }
        $this->validate(['backQrPhoto' => 'image|max:10240']);

        $analyzer = app(\App\Services\BadgeAnalyzer::class);
        if (! $analyzer->isConfigured()) {
            $this->showToast($this->dict()['b_aiOff']);
            return;
        }
        $code = $analyzer->analyzeBack(
            file_get_contents($this->backQrPhoto->getRealPath()),
            $this->backQrPhoto->getMimeType() ?: 'image/jpeg'
        );
        if ($code === null) {
            $this->showToast($this->dict()['b_qrFail']);
            return;
        }
        $this->captureBackQr($code);
    }

    public function startScanN(): void
    {
        $this->scanN = 'scanning';
    }

    public function finishScanN(): void
    {
        if ($this->scanN === 'scanning') {
            $this->scanN = 'done';
        }
    }

    public function setRegType(string $v): void
    {
        $this->regType = $v;
        $this->regAccess = $v === 'manager' ? 'manager' : 'worker';
    }

    public function setRegAccess(string $lvl): void
    {
        $this->regAccess = $lvl;
    }

    public function finishBadge(): void
    {
        if (! $this->canManage()) {
            return;
        }
        $d = $this->dict();
        if (trim($this->regFirst) === '' || trim($this->regLast) === '') {
            $this->showToast($d['b_needName']);
            return;
        }
        $uid = $this->currentUid();
        if ($uid === null) {
            $this->showToast($d['b_needUid']);
            return;
        }
        $empId = $this->nfcId($uid);
        if (Employee::where('emp_id', $empId)->exists()) {
            $this->showToast($d['b_dupId']);
            return;
        }
        $team = Team::find($this->regTeam);
        $companyId = $team->company_id ?? 'c1';
        $siteId = optional(Company::find($companyId))->site_id ?? 's1';
        Employee::create([
            'emp_id' => $empId,
            'first' => trim($this->regFirst), 'last' => trim($this->regLast),
            'nat' => '', 'code' => '',
            'team_id' => $this->regTeam, 'company_id' => $companyId, 'site_id' => $siteId,
            'role' => trim($this->regRoleTitle),
            'type' => $this->regType === 'manager' ? 'manager' : 'worker',
            'lang' => $this->regType === 'worker_local' ? 'es' : 'ko',
            'access' => $this->regAccess,
            'rate' => (float) ($this->regRate ?: 0),
            'issued' => trim($this->regIssued),
            'phone' => trim($this->regPhone), 'email' => trim($this->regEmail),
            'badge_qr' => trim($this->backQrValue) ?: null,
            'status' => 'off', 'in_t' => '—', 'out_t' => '—', 'wh' => 0, 'emp' => 'active', 'term' => null,
        ]);
        $this->screen = 'employees';
        $this->bstep = 'front';
        $this->scanF = $this->scanB = $this->scanN = 'idle';
        $this->reset(['regFirst', 'regLast', 'regCoName', 'regRoleTitle', 'regIssued',
            'regRate', 'regPhone', 'regEmail', 'nfcUidManual', 'badgePhoto', 'backQrValue', 'backQrPhoto', 'backManual']);
        $this->showToast($d['b_finish'] . ' ✓');
    }

    // =================== attendance ===================

    public function setAttView(string $v): void
    {
        $this->attView = in_array($v, ['records', 'qr'], true) ? $v : 'records';
    }

    public function setQrMode(string $m): void
    {
        $this->qrMode = $m;
    }

    public function selectQrTeam(string $tid): void
    {
        $this->qrTeam = $tid;
    }

    public function manualPunch(int $id, string $dir): void
    {
        if (! $this->canManage()) {
            return;
        }
        $e = Employee::find($id);
        if (! $e || $e->emp !== 'active') {
            return;
        }
        $nowMin = (int) now()->format('H') * 60 + (int) now()->format('i');
        $now = Shift::fmtMin($nowMin);
        $p = $this->todayPunch($e->id);
        if ($dir === 'in') {
            $p->in_min = $p->in_min ?? $nowMin;
            $p->out_min = null;
            $p->source = 'manual';
            $p->save();
            $e->update(['status' => 'present', 'in_t' => Shift::fmtMin($p->in_min)]);
        } else {
            if ($p->exists && $p->in_min !== null) {
                $p->out_min = $nowMin;
                $p->save();
            }
            $e->update(['status' => 'off', 'out_t' => $now]);
        }
        $label = $dir === 'in' ? $this->dict()['q_in'] : $this->dict()['q_out'];
        $this->showToast($e->first . ' ' . $e->last . ' · ' . $label);
    }

    public function exportPayroll(): void
    {
        $this->showToast($this->dict()['p_export'] . ' ✓');
    }

    // =================== payroll ===================

    public function openPayDetail(int $id): void
    {
        $this->payDetail = $id;
    }

    public function closePayDetail(): void
    {
        $this->payDetail = null;
    }

    public function openVoucher(): void
    {
        $this->payVoucher = true;
    }

    public function closeVoucher(): void
    {
        $this->payVoucher = false;
    }

    public function printVoucher(): void
    {
        if (! $this->canManage()) {
            return;
        }
        if (trim($this->checkNo) === '') {
            $this->showToast($this->dict()['pv_needCheck']);
            return;
        }
        $e = Employee::find($this->payDetail);
        if ($e) {
            [$start, $end] = Payroll::currentPeriod();
            $hours = Payroll::periodHoursFromPunches($e->id, $start, $end) ?? $e->wh;
            $hours = (int) round($hours);
            Payment::updateOrCreate(
                ['employee_id' => $e->id, 'period_start' => $start],
                [
                    'period_end' => $end,
                    'check_no' => trim($this->checkNo),
                    'pay_date' => $this->payDate,
                    'amount' => round(Payroll::gross($hours, $e->rate), 2),
                    'reg_hours' => Payroll::regHours($hours),
                    'ot_hours' => Payroll::otHours($hours),
                ]
            );
        }
        $this->dispatch('print-now');
        $this->showToast($this->dict()['pv_paidToast']);
    }

    // =================== worker mobile ===================

    public function doClock(): void
    {
        $eid = $this->meEmployeeId();
        $me = Employee::find($eid);
        $nowMin = (int) now()->format('H') * 60 + (int) now()->format('i');
        $p = $this->todayPunch($eid);
        if ($this->clock === 'out') {
            $this->clock = 'in';
            $this->clockInTime = Shift::fmtMin($nowMin);
            $p->in_min = $p->in_min ?? $nowMin;
            $p->out_min = null;                       // re-clock-in reopens the day
            $p->no_lunch = $this->noLunchToday;
            $p->source = 'worker';
            $p->save();
            $me?->update(['status' => 'present', 'in_t' => Shift::fmtMin($p->in_min)]);
            $this->showToast($this->dict()['w_done_in']);
        } else {
            $this->clock = 'out';
            $p->out_min = $nowMin;
            $p->source = 'worker';
            $p->save();
            $me?->update(['status' => 'off', 'out_t' => Shift::fmtMin($nowMin)]);
            $this->showToast($this->dict()['w_done_out']);
        }
    }

    public function toggleNoLunch(): void
    {
        $this->noLunchToday = ! $this->noLunchToday;
        $p = $this->todayPunch($this->meEmployeeId());
        if ($p->exists) {
            $p->update(['no_lunch' => $this->noLunchToday]);
        }
        $d = $this->dict();
        $this->showToast($this->noLunchToday ? $d['lunchSkipToast'] : $d['lunchKeptToast']);
    }

    public function togglePunchLunch(int $punchId): void
    {
        $p = Punch::find($punchId);
        if (! $p || $p->employee_id !== $this->meEmployeeId() && ! $this->canManage()) {
            return;
        }
        $p->update(['no_lunch' => ! $p->no_lunch]);
        $d = $this->dict();
        $this->showToast($p->no_lunch ? $d['lunchSkipToast'] : $d['lunchKeptToast']);
    }

    public function toggleLunchRow(string $dayKey, bool $seedNoLunch): void
    {
        $cur = array_key_exists($dayKey, $this->lunchOv) ? $this->lunchOv[$dayKey] : $seedNoLunch;
        $this->lunchOv[$dayKey] = ! $cur;
        $d = $this->dict();
        $this->showToast(! $cur ? $d['lunchSkipToast'] : $d['lunchKeptToast']);
    }

    public function openEarly(): void
    {
        $this->earlyOpen = true;
        $this->earlyReasonVal = $this->dict()['w_reasons'][0];
        $this->earlyCustom = '';
    }

    public function closeEarly(): void
    {
        $this->earlyOpen = false;
    }

    public function submitEarly(): void
    {
        $d = $this->dict();
        $reason = $this->earlyReasonVal === '__custom__'
            ? (trim($this->earlyCustom) ?: $d['w_earlyOther'])
            : $this->earlyReasonVal;
        $nowMin = (int) now()->format('H') * 60 + (int) now()->format('i');
        $p = $this->todayPunch($this->meEmployeeId());
        $p->out_min = $nowMin;
        $p->early_reason = $reason;
        $p->source = 'worker';
        $p->save();
        Employee::where('id', $this->meEmployeeId())
            ->update(['status' => 'off', 'out_t' => Shift::fmtMin($nowMin)]);
        $this->clock = 'out';
        $this->earlyOpen = false;
        $this->showToast($d['w_earlyDone'] . ' · ' . $reason);
    }

    public function printQr(): void
    {
        $this->dispatch('print-now');
    }

    // =================== render ===================

    public function render()
    {
        $vm = \App\Support\ViewModel::build($this->state());

        return view('livewire.workforce-app', $vm);
    }

    // ---- accessors used by the view-model builder ----
    public function state(): array
    {
        return [
            'screen' => $this->screen, 'role' => $this->role, 'access' => $this->access, 'lang' => $this->lang,
            'dashLayout' => $this->dashLayout, 'site' => $this->site,
            'selectedEmp' => $this->selectedEmp, 'empFilter' => $this->empFilter,
            'teamFilter' => $this->teamFilter, 'search' => $this->search,
            'deleteId' => $this->deleteId, 'terminateId' => $this->terminateId,
            'editForm' => $this->editForm,
            'bstep' => $this->bstep, 'scanF' => $this->scanF, 'scanB' => $this->scanB, 'scanN' => $this->scanN,
            'regTeam' => $this->regTeam, 'regType' => $this->regType, 'regAccess' => $this->regAccess,
            'regFirst' => $this->regFirst, 'regLast' => $this->regLast, 'regCoName' => $this->regCoName,
            'regRoleTitle' => $this->regRoleTitle, 'regIssued' => $this->regIssued,
            'nfcUidManual' => $this->nfcUidManual,
            'backQrValue' => $this->backQrValue,
            'companyModal' => $this->companyModal, 'teamModal' => $this->teamModal,
            'editCompanyId' => $this->editCompanyId, 'editTeamId' => $this->editTeamId,
            'deleteCompanyId' => $this->deleteCompanyId, 'deleteTeamId' => $this->deleteTeamId,
            'newCoName' => $this->newCoName, 'newCoSite' => $this->newCoSite,
            'newTeamName' => $this->newTeamName, 'newTeamLead' => $this->newTeamLead,
            'attView' => $this->attView, 'attDate' => $this->attDate ?: now()->format('Y-m-d'),
            'qrMode' => $this->qrMode, 'qrTeam' => $this->qrTeam,
            'payDetail' => $this->payDetail, 'payVoucher' => $this->payVoucher,
            'checkNo' => $this->checkNo, 'payDate' => $this->payDate,
            'mobileTab' => $this->mobileTab, 'clock' => $this->clock, 'clockInTime' => $this->clockInTime,
            'earlyOpen' => $this->earlyOpen, 'earlyReasonVal' => $this->earlyReasonVal, 'earlyCustom' => $this->earlyCustom,
            'noLunchToday' => $this->noLunchToday, 'lunchOv' => $this->lunchOv,
            'toast' => $this->toast,
            'nfcUid' => $this->currentUid() ?? self::NFC_UID,
            'nfcId' => $this->nfcId($this->currentUid() ?? self::NFC_UID),
            'hasUid' => $this->currentUid() !== null,
            'meEmployeeId' => $this->meEmployeeId(),
            'selfEmployeeId' => $this->selfEmployeeId(),
            'canDeskClock' => $this->canDeskClock(),
        ];
    }

    public function tlPublic(string $en, string $es, string $ko): string
    {
        return $this->tl($en, $es, $ko);
    }
}
