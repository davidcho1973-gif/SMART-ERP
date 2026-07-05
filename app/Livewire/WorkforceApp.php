<?php

namespace App\Livewire;

use App\Models\Assignment;
use App\Models\AttendanceCorrection;
use App\Models\Channel;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Punch;
use App\Models\Site;
use App\Models\Team;
use App\Services\BadgeAnalyzer;
use App\Support\Comms;
use App\Support\Corrections;
use App\Support\Geo;
use App\Support\Payroll;
use App\Support\Qr;
use App\Support\Shift;
use App\Support\ViewModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
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

    // ---- company-involvement assignments (add form) ----
    public string $newAssignCompany = '';

    public string $newAssignTeam = '';

    public string $newAssignRelation = '파견';

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

    /** downscaled data-URI of the analyzed badge photo (for the auto-cropped face) */
    public string $facePhotoData = '';

    /** normalized face bounding box {x,y,w,h} detected on the badge, or empty */
    public array $faceBox = [];

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

    // ---- site geofence (location + radius) editor ----
    public ?string $siteModal = null;  // site id being edited

    public string $siteLat = '';

    public string $siteLng = '';

    public string $siteRadius = '';

    public string $siteAddress = '';

    public string $siteName = '';

    public string $siteCity = '';

    public ?string $deleteSiteId = null;  // pending site deletion

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

    // ---- attendance-correction requests (worker files · lead/HR approves) ----
    public bool $correctionOpen = false;

    public string $correctionDate = '';      // Y-m-d being corrected

    public string $correctionType = 'set';   // set | delete

    public string $correctionIn = '';        // HH:MM (24h)

    public string $correctionOut = '';       // HH:MM (24h)

    public string $correctionReason = '';

    /** approver: id of the request being rejected (reject-with-note box open) */
    public ?int $rejectingId = null;

    public string $rejectNote = '';

    // ---- internal comms (announcements · company/crew chat · DM · bell) ----
    public ?int $commsChannel = null;

    public string $commsCompose = '';

    public bool $commsNewDm = false;

    public string $commsDmSearch = '';

    public bool $bellOpen = false;

    /** mobile master-detail pane: 'list' (channel list) or 'thread' (open conversation) */
    public string $commsPane = 'list';

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

        return 'N-'.strtoupper(substr($hex, -9));
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
            'emp_id' => 'STAFF-'.str_pad((string) $u->id, 4, '0', STR_PAD_LEFT),
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

    /**
     * A day is locked once the punch has both an in and an out — one clock-in and
     * one clock-out per worker per day. Locking is decided from the punch record,
     * never from transient UI state, so a re-tap can't reopen and overwrite the day.
     */
    protected function dayLocked(Punch $p): bool
    {
        return $p->exists && $p->in_min !== null && $p->out_min !== null;
    }

    /** Clock UI state derived from the punch: 'out' (pre-in) · 'in' (working) · 'done' (locked). */
    protected function clockStateFor(Punch $p): string
    {
        if ($this->dayLocked($p)) {
            return 'done';
        }

        return ($p->exists && $p->in_min !== null && $p->out_min === null) ? 'in' : 'out';
    }

    /** Clock the current admin/manager in or out (records a real punch, one in/out per day). */
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
        $p = $this->todayPunch($eid);
        $d = $this->dict();
        // already clocked in AND out today — the day is locked, only an admin may
        // correct it via manualPunch. A repeat tap must not reopen the record.
        if ($this->dayLocked($p)) {
            $this->showToast($d['w_workDone']);

            return;
        }
        $nowMin = (int) now()->format('H') * 60 + (int) now()->format('i');
        if ($p->in_min === null) {
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
            Comms::ensureRooms();   // worker home board reads announcements + their rooms
            // restore today's clock state from the punch record (out · in · done)
            $p = $this->todayPunch($this->meEmployeeId());
            $this->clock = $this->clockStateFor($p);
            if ($this->clock === 'in') {
                $this->clockInTime = Shift::fmtMin($p->in_min);
                $this->noLunchToday = $p->no_lunch;
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
            $this->syncWorkerClock();
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
            $this->syncWorkerClock();
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
        // entering badge registration: make sure the crew selector points at a real crew
        if ($screen === 'badge' && ! Team::find($this->regTeam)) {
            $this->regTeam = Team::first()?->id ?? '';
        }
        if ($screen === 'comms') {
            $this->bellOpen = false;
            $this->commsPane = 'list'; // on mobile, land on the channel list
            $this->enterComms();
        }
    }

    // =================== internal comms ===================

    /** The employee acting as the current user (display side — never provisions a record). */
    protected function actorId(): int
    {
        if ($this->role === 'worker') {
            return $this->meEmployeeId();
        }

        return $this->selfEmployeeId() ?? $this->meEmployeeId();
    }

    /** The employee acting as the current user, provisioning a staff record if needed. */
    protected function actorEmployee(): ?Employee
    {
        $id = $this->role === 'worker' ? $this->meEmployeeId() : ($this->ensureSelfEmployee() ?? $this->meEmployeeId());

        return Employee::find($id);
    }

    /** Land on the comms screen: make sure rooms exist and a channel is selected + read. */
    public function enterComms(): void
    {
        Comms::ensureRooms();
        $me = Employee::find($this->actorId());
        if (! $me) {
            return;
        }
        $channels = Comms::visibleChannels($me);
        $current = $this->commsChannel ? $channels->firstWhere('id', $this->commsChannel) : null;
        if (! $current) {
            $current = $channels->firstWhere('type', 'announcement') ?? $channels->first();
            $this->commsChannel = $current?->id;
        }
        if ($current) {
            Comms::markRead($current, $me);
        }
    }

    public function selectChannel(int $id): void
    {
        $me = Employee::find($this->actorId());
        if (! $me) {
            return;
        }
        $ch = Channel::find($id);
        if (! $ch || ! Comms::canAccess($ch, $me)) {
            return;
        }
        $this->commsChannel = $id;
        $this->commsCompose = '';
        $this->commsNewDm = false;
        $this->bellOpen = false;
        $this->commsPane = 'thread'; // mobile: reveal the conversation
        Comms::markRead($ch, $me);
    }

    /** Mobile: step back from an open conversation to the channel list. */
    public function commsBack(): void
    {
        $this->commsPane = 'list';
    }

    public function sendMessage(): void
    {
        $body = trim($this->commsCompose);
        if ($body === '' || ! $this->commsChannel) {
            return;
        }
        $me = $this->actorEmployee();
        if (! $me) {
            return;
        }
        $ch = Channel::find($this->commsChannel);
        if (! $ch || ! Comms::canPost($ch, $me, $this->canManage())) {
            $this->showToast($this->tl('You can’t post here', 'No puedes publicar aquí', '여기에는 글을 쓸 수 없어요'));

            return;
        }
        Message::create([
            'channel_id' => $ch->id,
            'sender_id' => $me->id,
            'body' => mb_substr($body, 0, 2000),
        ]);
        $this->commsCompose = '';
        Comms::markRead($ch, $me);
    }

    public function toggleNewDm(): void
    {
        $this->commsNewDm = ! $this->commsNewDm;
        $this->commsDmSearch = '';
    }

    public function startDm(int $employeeId): void
    {
        $me = $this->actorEmployee();
        if (! $me || $employeeId === $me->id) {
            return;
        }
        if (! Employee::whereKey($employeeId)->exists()) {
            return;
        }
        $ch = Comms::findOrCreateDm($me->id, $employeeId);
        $this->commsChannel = $ch->id;
        $this->commsNewDm = false;
        $this->commsDmSearch = '';
        $this->commsCompose = '';
        $this->commsPane = 'thread'; // mobile: reveal the conversation
        Comms::markRead($ch, $me);
    }

    public function toggleBell(): void
    {
        $this->bellOpen = ! $this->bellOpen;
    }

    public function openFromBell(int $channelId): void
    {
        $this->bellOpen = false;
        if ($this->role === 'worker') {
            return;
        }
        $this->screen = 'comms';
        $this->selectChannel($channelId); // also flips the mobile pane to the thread
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
        $this->reset(['newAssignCompany', 'newAssignTeam']);
        $this->newAssignRelation = '파견';
        // repair a stale/invalid team (e.g. left over from cleared demo ids) so the
        // drawer shows a real selection and a plain Save persists it
        $teamId = $e->team_id;
        if ($teamId !== null && ! Team::find($teamId)) {
            $teamId = Team::first()?->id;
        }
        $teamModel = $teamId ? Team::find($teamId) : null;
        $companyId = $teamModel?->company_id
            ?? (($e->company_id && Company::find($e->company_id)) ? $e->company_id : null);
        $this->editForm = [
            'first' => $e->first, 'last' => $e->last, 'company' => $companyId,
            'team' => $teamId, 'role' => $e->role, 'rate' => $e->rate,
            'type' => $e->type === 'manager' ? 'manager' : ($e->lang === 'ko' ? 'worker_ko' : 'worker_local'),
            'issued' => $e->issued, 'phone' => $e->phone, 'email' => $e->email,
            'nat' => $e->nat, 'access' => $e->access,
        ];
    }

    /** Rotate the selected employee's badge photo 90° clockwise (fixes sideways photos). */
    public function rotateBadgePhoto(): void
    {
        if (! $this->canManage() || ! $this->selectedEmp) {
            return;
        }
        $e = Employee::find($this->selectedEmp);
        if (! $e || ! $e->badge_photo || ! function_exists('imagerotate')) {
            return;
        }
        if (! preg_match('#^data:image/\w+;base64,(.+)$#', $e->badge_photo, $m)) {
            return;
        }
        $img = @imagecreatefromstring(base64_decode($m[1]));
        if (! $img) {
            return;
        }
        $rot = imagerotate($img, -90, 0);   // 90° clockwise
        imagedestroy($img);
        ob_start();
        imagejpeg($rot, null, 82);
        $data = (string) ob_get_clean();
        imagedestroy($rot);
        $e->update(['badge_photo' => 'data:image/jpeg;base64,'.base64_encode($data)]);
    }

    /** Add a company-involvement assignment to the selected employee. */
    public function addAssignment(): void
    {
        if (! $this->canManage() || ! $this->selectedEmp) {
            return;
        }
        if ($this->newAssignCompany === '') {
            $this->showToast($this->dict()['e_involveNeedCompany']);

            return;
        }
        try {
            Assignment::create([
                'employee_id' => $this->selectedEmp,
                'company_id' => $this->newAssignCompany,
                'team_id' => $this->newAssignTeam !== '' ? $this->newAssignTeam : null,
                'relation' => trim($this->newAssignRelation) !== '' ? trim($this->newAssignRelation) : '파견',
            ]);
        } catch (\Throwable) {
            $this->showToast($this->dict()['e_involveSaveFail']);

            return;
        }
        $this->reset(['newAssignCompany', 'newAssignTeam']);
        $this->newAssignRelation = '파견';
        $this->showToast($this->dict()['e_involveAdded']);
    }

    public function removeAssignment(int $id): void
    {
        if (! $this->canManage()) {
            return;
        }
        Assignment::where('id', $id)
            ->where('employee_id', $this->selectedEmp)->delete();
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
            // the crew determines the company (a crew belongs to exactly one company)
            $teamId = $this->editForm['team'] ?? $e->team_id;
            $teamModel = $teamId ? Team::find($teamId) : null;
            $company = $teamModel?->company_id ?? ($this->editForm['company'] ?? $e->company_id);
            $e->update([
                'first' => $this->editForm['first'] ?? $e->first,
                'last' => $this->editForm['last'] ?? $e->last,
                'company_id' => $company,
                'site_id' => optional(Company::find($company))->site_id ?? $e->site_id,
                'team_id' => $teamId,
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
        $this->showToast($this->dict()['e_save'].' ✓');
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
        $this->showToast($this->dict()['e_delete'].' ✓');
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
        $this->showToast($this->dict()['e_terminate'].' ✓');
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
        $this->newCoSite = $site ? trim($site->name.($site->city ? ' · '.$site->city : '')) : '';
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
            return strcasecmp($s->name.' · '.$s->city, $siteName) === 0
                || strcasecmp($s->name, $siteName) === 0;
        });
        if (! $site) {
            $site = Site::create(['id' => 's'.Str::random(6), 'name' => $siteName, 'city' => '', 'gc' => 'Hoffman', 'code' => '']);
        }
        if ($this->editCompanyId) {
            Company::where('id', $this->editCompanyId)->update(['name' => $name, 'site_id' => $site->id]);
            $this->showToast($this->dict()['pj_saved'].' ✓');
        } else {
            Company::create(['id' => 'c'.Str::random(6), 'name' => $name, 'site_id' => $site->id]);
            $this->showToast(str_replace('+ ', '', $this->dict()['pj_create']).' ✓');
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
        $this->showToast($this->dict()['pj_deleted'].' ✓');
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
            $this->showToast($this->dict()['pj_saved'].' ✓');
        } else {
            $cols = ['#3B72E0', '#1F9D6B', '#E85D2A', '#D9483B', '#8A5CF6', '#0EA5A0'];
            $count = Team::count();
            Team::create([
                'id' => 't'.Str::random(6),
                'name' => trim($this->newTeamName),
                'company_id' => $this->teamModal,
                'lead' => $this->newTeamLead !== '' ? (int) $this->newTeamLead : null,
                'color' => $cols[$count % count($cols)],
            ]);
            $this->showToast(str_replace('+ ', '', $this->dict()['pj_newTeam']).' ✓');
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
        $this->showToast($this->dict()['pj_deleted'].' ✓');
    }

    public function changeLead(string $teamId, string $leadId): void
    {
        if (! $this->canManage()) {
            return;
        }
        Team::where('id', $teamId)->update(['lead' => (int) $leadId]);
    }

    // =================== site geofence ===================

    public function openSiteModal(string $id): void
    {
        $site = Site::find($id);
        if (! $site) {
            return;
        }
        $this->siteModal = $id;
        $this->siteName = $site->name;
        $this->siteCity = $site->city;
        $this->siteLat = $site->lat !== null ? (string) $site->lat : '';
        $this->siteLng = $site->lng !== null ? (string) $site->lng : '';
        $this->siteRadius = (string) ($site->radius_m ?: Geo::DEFAULT_RADIUS_M);
        $this->siteAddress = '';
    }

    public function cancelSiteModal(): void
    {
        $this->siteModal = null;
    }

    /** Fill the lat/lng fields from the admin's current position ("현재 위치로 설정"). */
    public function setSiteCurrentLocation(float|string|null $lat, float|string|null $lng): void
    {
        if ($lat !== null && $lat !== '') {
            $this->siteLat = (string) round((float) $lat, 7);
        }
        if ($lng !== null && $lng !== '') {
            $this->siteLng = (string) round((float) $lng, 7);
        }
        $this->showToast($this->dict()['pj_geoCaptured']);
    }

    /**
     * Geocode the typed address to lat/lng via OpenStreetMap Nominatim (keyless).
     * The admin can then adjust the radius and save. Failures just toast — the
     * manual lat/lng fields remain available as a fallback.
     */
    public function geocodeSiteAddress(): void
    {
        if (! $this->canManage()) {
            return;
        }
        $q = trim($this->siteAddress);
        if ($q === '') {
            return;
        }
        $hit = null;
        try {
            $res = Http::withHeaders(['User-Agent' => 'SMART-ERP/1.0 (site attendance geofence)'])
                ->timeout(8)
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $q, 'format' => 'jsonv2', 'limit' => 1,
                ]);
            $hit = $res->successful() ? ($res->json()[0] ?? null) : null;
        } catch (\Throwable) {
            $hit = null;
        }
        if (! $hit || ! isset($hit['lat'], $hit['lon'])) {
            $this->showToast($this->dict()['pj_geoNotFound']);

            return;
        }
        $this->siteLat = (string) round((float) $hit['lat'], 7);
        $this->siteLng = (string) round((float) $hit['lon'], 7);
        $this->showToast($this->dict()['pj_geoFound']);
    }

    public function saveSiteGeo(): void
    {
        if (! $this->canManage() || ! $this->siteModal) {
            return;
        }
        $site = Site::find($this->siteModal);
        if (! $site) {
            return;
        }
        $lat = trim($this->siteLat);
        $lng = trim($this->siteLng);
        $radius = (int) $this->siteRadius;
        $name = trim($this->siteName);
        $site->update([
            'name' => $name !== '' ? $name : $site->name,
            'city' => trim($this->siteCity),
            'lat' => $lat !== '' ? (float) $lat : null,
            'lng' => $lng !== '' ? (float) $lng : null,
            'radius_m' => $radius > 0 ? $radius : Geo::DEFAULT_RADIUS_M,
        ]);
        $this->siteModal = null;
        $this->showToast($this->dict()['pj_saved'].' ✓');
    }

    public function askDeleteSite(string $id): void
    {
        $this->deleteSiteId = $id;
    }

    public function cancelDeleteSite(): void
    {
        $this->deleteSiteId = null;
    }

    /**
     * Delete a site. Companies and workers pointing at it are unassigned (site_id
     * cleared) rather than deleted — records are kept, matching company/crew deletes.
     */
    public function confirmDeleteSite(): void
    {
        if (! $this->canManage() || ! $this->deleteSiteId) {
            return;
        }
        $id = $this->deleteSiteId;
        Company::where('site_id', $id)->update(['site_id' => null]);
        Employee::where('site_id', $id)->update(['site_id' => null]);
        Site::where('id', $id)->delete();
        if ($this->site === $id) {
            $this->site = 'all';   // the filtered-on site is gone → fall back to all
        }
        $this->deleteSiteId = null;
        $this->siteModal = null;
        $this->showToast($this->dict()['pj_deleted'].' ✓');
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

        $analyzer = app(BadgeAnalyzer::class);
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

        // keep a small copy of the photo (EXIF-rotated upright) for the badge photo
        $path = $this->badgePhoto->getRealPath();
        $this->facePhotoData = $this->downscaleToDataUri(file_get_contents($path), 420, $this->exifOrientation($path));
        $this->faceBox = $result['face'] ?? [];

        $this->scanF = 'done';
        $this->showToast($this->dict()['b_aiDone']);
    }

    /** Read the EXIF orientation flag of an image file (1 = upright). */
    protected function exifOrientation(string $path): int
    {
        if (! function_exists('exif_read_data')) {
            return 1;
        }
        try {
            $exif = @exif_read_data($path);

            return (int) ($exif['Orientation'] ?? 1);
        } catch (\Throwable) {
            return 1;
        }
    }

    /** Downscale image bytes to a compact JPEG data-URI (keeps Livewire state light). */
    protected function downscaleToDataUri(string $bytes, int $maxW = 420, int $orientation = 1): string
    {
        if (! function_exists('imagecreatefromstring')) {
            return '';
        }
        $img = @imagecreatefromstring($bytes);
        if (! $img) {
            return '';
        }
        // apply EXIF orientation so phone photos aren't shown sideways/upside-down
        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($angle !== 0) {
            $img = imagerotate($img, $angle, 0);
        }
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w > $maxW) {
            $nh = (int) round($h * $maxW / $w);
            $resized = imagecreatetruecolor($maxW, $nh);
            imagecopyresampled($resized, $img, 0, 0, 0, 0, $maxW, $nh, $w, $h);
            imagedestroy($img);
            $img = $resized;
        }
        ob_start();
        imagejpeg($img, null, 82);
        $data = (string) ob_get_clean();
        imagedestroy($img);

        return 'data:image/jpeg;base64,'.base64_encode($data);
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
        $this->facePhotoData = '';
        $this->faceBox = [];
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

        $analyzer = app(BadgeAnalyzer::class);
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
        // resolve the crew robustly (a stale regTeam falls back to a real crew),
        // and derive company/site from it so the record is always consistent
        $team = Team::find($this->regTeam) ?? Team::first();
        $companyId = $team?->company_id;
        $siteId = optional(Company::find($companyId))->site_id;
        Employee::create([
            'emp_id' => $empId,
            'first' => trim($this->regFirst), 'last' => trim($this->regLast),
            'nat' => '', 'code' => '',
            'team_id' => $team?->id, 'company_id' => $companyId, 'site_id' => $siteId,
            'role' => trim($this->regRoleTitle),
            'type' => $this->regType === 'manager' ? 'manager' : 'worker',
            'lang' => $this->regType === 'worker_local' ? 'es' : 'ko',
            'access' => $this->regAccess,
            'rate' => (float) ($this->regRate ?: 0),
            'issued' => trim($this->regIssued),
            'phone' => trim($this->regPhone), 'email' => trim($this->regEmail),
            'badge_qr' => trim($this->backQrValue) ?: null,
            'badge_photo' => $this->facePhotoData ?: null,
            'status' => 'off', 'in_t' => '—', 'out_t' => '—', 'wh' => 0, 'emp' => 'active', 'term' => null,
        ]);
        $this->screen = 'employees';
        $this->bstep = 'front';
        $this->scanF = $this->scanB = $this->scanN = 'idle';
        $this->reset(['regFirst', 'regLast', 'regCoName', 'regRoleTitle', 'regIssued',
            'regRate', 'regPhone', 'regEmail', 'nfcUidManual', 'badgePhoto', 'backQrValue', 'backQrPhoto', 'backManual',
            'facePhotoData', 'faceBox']);
        $this->showToast($d['b_finish'].' ✓');
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
        $this->showToast($e->first.' '.$e->last.' · '.$label);
    }

    public function exportPayroll(): void
    {
        $this->showToast($this->dict()['p_export'].' ✓');
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

    /** Point the worker-mobile clock button at the real punch state (out · in · done). */
    protected function syncWorkerClock(): void
    {
        Comms::ensureRooms();   // worker home board reads announcements + their rooms
        $p = $this->todayPunch($this->meEmployeeId());
        $this->clock = $this->clockStateFor($p);
        if ($this->clock === 'in') {
            $this->clockInTime = Shift::fmtMin($p->in_min);
            $this->noLunchToday = (bool) $p->no_lunch;
        }
    }

    /**
     * Worker-mobile clock. GPS lat/lng/accuracy are captured by the browser when the
     * button is tapped and passed in — null when permission is denied or unavailable.
     * The punch is recorded regardless; out-of-radius fixes are flagged, not blocked.
     */
    public function doClock(float|string|null $lat = null, float|string|null $lng = null, float|string|null $acc = null): void
    {
        $eid = $this->meEmployeeId();
        $me = Employee::find($eid);
        $p = $this->todayPunch($eid);
        $d = $this->dict();
        // day already complete (in + out) — locked. Only an admin may correct it via
        // manualPunch; a repeat tap must not reopen the record or overwrite the times.
        if ($this->dayLocked($p)) {
            $this->clock = 'done';
            $this->showToast($d['w_workDone']);

            return;
        }
        $nowMin = (int) now()->format('H') * 60 + (int) now()->format('i');
        $site = $me && $me->site_id ? Site::find($me->site_id) : null;
        [, $geoOk] = Geo::verifySite($site, $lat, $lng);
        $coords = $lat !== null && $lng !== null && $lat !== '' && $lng !== ''
            ? ['lat' => (float) $lat, 'lng' => (float) $lng, 'acc' => $acc !== null && $acc !== '' ? (float) $acc : null]
            : null;
        if ($p->in_min === null) {
            // first clock-in of the day
            $this->clock = 'in';
            $this->clockInTime = Shift::fmtMin($nowMin);
            $p->in_min = $nowMin;
            $p->out_min = null;
            $p->no_lunch = $this->noLunchToday;
            $p->source = 'worker';
            $p->in_lat = $coords['lat'] ?? null;
            $p->in_lng = $coords['lng'] ?? null;
            $p->in_acc = $coords['acc'] ?? null;
            $p->in_geo_ok = $geoOk;
            $p->save();
            $me?->update(['status' => 'present', 'in_t' => Shift::fmtMin($nowMin)]);
            $this->showToast($d['w_done_in']);
        } else {
            // clock-out completes and locks the day
            $this->clock = 'done';
            $p->out_min = $nowMin;
            $p->source = 'worker';
            $p->out_lat = $coords['lat'] ?? null;
            $p->out_lng = $coords['lng'] ?? null;
            $p->out_acc = $coords['acc'] ?? null;
            $p->out_geo_ok = $geoOk;
            $p->save();
            $me?->update(['status' => 'off', 'out_t' => Shift::fmtMin($nowMin)]);
            $this->showToast($d['w_done_out']);
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
        $p = $this->todayPunch($this->meEmployeeId());
        // early leave is a clock-out: only valid while on the clock (in, not yet out).
        // A completed day is locked, and there's nothing to leave early from before in.
        if ($p->in_min === null || $p->out_min !== null) {
            $this->earlyOpen = false;
            $this->clock = $this->clockStateFor($p);
            $this->showToast($this->dayLocked($p) ? $d['w_workDone'] : $d['w_status_out']);

            return;
        }
        $reason = $this->earlyReasonVal === '__custom__'
            ? (trim($this->earlyCustom) ?: $d['w_earlyOther'])
            : $this->earlyReasonVal;
        $nowMin = (int) now()->format('H') * 60 + (int) now()->format('i');
        $p->out_min = $nowMin;
        $p->early_reason = $reason;
        $p->source = 'worker';
        $p->save();
        Employee::where('id', $this->meEmployeeId())
            ->update(['status' => 'off', 'out_t' => Shift::fmtMin($nowMin)]);
        $this->clock = 'done';   // in + out recorded → day locked
        $this->earlyOpen = false;
        $this->showToast($d['w_earlyDone'].' · '.$reason);
    }

    public function printQr(): void
    {
        $this->dispatch('print-now');
    }

    // =================== attendance corrections ===================

    /** Parse a 24h "HH:MM" field into minutes-from-midnight (null if blank/invalid). */
    protected function minsFromHHMM(string $t): ?int
    {
        if (! preg_match('/^\s*(\d{1,2}):(\d{2})\s*$/', $t, $m)) {
            return null;
        }
        $h = (int) $m[1];
        $mi = (int) $m[2];

        return ($h > 23 || $mi > 59) ? null : $h * 60 + $mi;
    }

    protected function hhmm(?int $min): string
    {
        return $min === null ? '' : sprintf('%02d:%02d', intdiv($min, 60), $min % 60);
    }

    /** Reason the worker may NOT file a correction for this date (window / paid-period), else null. */
    protected function correctionBlockedReason(int $eid, string $workDate): ?string
    {
        $d = \DateTime::createFromFormat('Y-m-d', $workDate);
        if (! $d || $d->format('Y-m-d') !== $workDate) {
            return $this->tl('Invalid date', 'Fecha inválida', '날짜 오류');
        }
        $today = now()->startOfDay();
        $day = Carbon::instance($d)->startOfDay();
        if ($day->gt($today)) {
            return $this->tl('Future date', 'Fecha futura', '미래 날짜는 정정 불가');
        }
        if ($day->lt($today->copy()->subDays(Corrections::WINDOW_DAYS))) {
            return $this->tl('Too old to correct — ask HR', 'Muy antiguo — consulta a RR. HH.', '기간 초과 · 인사팀 문의');
        }
        if (Corrections::isPaidPeriod($eid, $workDate)) {
            return $this->tl('Paid period — ask HR', 'Periodo pagado — consulta a RR. HH.', '지급 완료 기간 · 인사팀 문의');
        }

        return null;
    }

    /** Worker: open the correction form for a work date (prefilled with the current punch). */
    public function openCorrection(string $workDate): void
    {
        $eid = $this->meEmployeeId();
        if ($reason = $this->correctionBlockedReason($eid, $workDate)) {
            $this->showToast($reason);

            return;
        }
        if (AttendanceCorrection::where('employee_id', $eid)->where('work_date', $workDate)->where('status', 'pending')->exists()) {
            $this->showToast($this->tl('A request is already pending', 'Ya hay una solicitud pendiente', '이미 처리 대기 중인 요청이 있어요'));

            return;
        }
        $p = Punch::where('employee_id', $eid)->where('work_date', $workDate)->first();
        $this->correctionDate = $workDate;
        $this->correctionType = 'set';
        $this->correctionIn = $this->hhmm($p?->in_min);
        $this->correctionOut = $this->hhmm($p?->out_min);
        $this->correctionReason = '';
        $this->correctionOpen = true;
    }

    public function closeCorrection(): void
    {
        $this->correctionOpen = false;
        $this->reset(['correctionDate', 'correctionIn', 'correctionOut', 'correctionReason']);
        $this->correctionType = 'set';
    }

    /** Worker: validate and file the correction request. */
    public function submitCorrection(): void
    {
        $eid = $this->meEmployeeId();
        $me = Employee::find($eid);
        if (! $me || $this->correctionDate === '') {
            return;
        }
        if ($reason = $this->correctionBlockedReason($eid, $this->correctionDate)) {
            $this->showToast($reason);
            $this->closeCorrection();

            return;
        }
        if (AttendanceCorrection::where('employee_id', $eid)->where('work_date', $this->correctionDate)->where('status', 'pending')->exists()) {
            $this->showToast($this->tl('A request is already pending', 'Ya hay una solicitud pendiente', '이미 처리 대기 중인 요청이 있어요'));

            return;
        }
        $note = trim($this->correctionReason);
        if ($note === '') {
            $this->showToast($this->tl('Enter a reason', 'Escribe un motivo', '정정 사유를 입력하세요'));

            return;
        }

        $in = null;
        $out = null;
        if ($this->correctionType !== 'delete') {
            $in = $this->minsFromHHMM($this->correctionIn);
            $out = $this->correctionOut === '' ? null : $this->minsFromHHMM($this->correctionOut);
            if ($in === null) {
                $this->showToast($this->tl('Enter a clock-in time', 'Indica la hora de entrada', '출근 시각을 입력하세요'));

                return;
            }
            if ($this->correctionOut !== '' && $out === null) {
                $this->showToast($this->tl('Invalid clock-out time', 'Hora de salida inválida', '퇴근 시각 형식 오류'));

                return;
            }
            if ($out !== null && $out < $in) {
                $this->showToast($this->tl('Clock-out is before clock-in', 'La salida es antes de la entrada', '퇴근이 출근보다 빠릅니다'));

                return;
            }
        }

        Corrections::submit($me, $this->correctionDate, $this->correctionType, $in, $out, $note);
        $this->closeCorrection();
        $this->showToast($this->tl('Request sent — pending review', 'Solicitud enviada — en revisión', '정정요청을 보냈어요 · 팀장·인사 확인 예정'));
    }

    /** Whether the current viewer is an HR admin (may approve any request). */
    protected function actorIsAdmin(): bool
    {
        if ($this->isDemo()) {
            return $this->role === 'admin';
        }

        return Auth::check() && Auth::user()->access === 'admin';
    }

    /** Approver: approve a request → apply it to the punch immediately. */
    public function approveCorrection(int $id): void
    {
        $c = AttendanceCorrection::find($id);
        $me = $this->actorEmployee();
        if (! $c || ! $me) {
            return;
        }
        if (! Corrections::canDecide($c, $me->id, $this->actorIsAdmin())) {
            $this->showToast($this->tl('Not allowed to decide this', 'No puedes decidir esto', '승인 권한이 없습니다'));

            return;
        }
        Corrections::approve($c, $me->id);
        $this->showToast($this->tl('Approved & applied', 'Aprobado y aplicado', '정정 승인·반영 완료'));
    }

    public function askRejectCorrection(int $id): void
    {
        $this->rejectingId = $id;
        $this->rejectNote = '';
    }

    public function cancelReject(): void
    {
        $this->rejectingId = null;
        $this->rejectNote = '';
    }

    /** Approver: reject a request with a note. */
    public function rejectCorrection(int $id): void
    {
        $c = AttendanceCorrection::find($id);
        $me = $this->actorEmployee();
        if (! $c || ! $me) {
            return;
        }
        if (! Corrections::canDecide($c, $me->id, $this->actorIsAdmin())) {
            $this->showToast($this->tl('Not allowed to decide this', 'No puedes decidir esto', '반려 권한이 없습니다'));

            return;
        }
        Corrections::reject($c, $me->id, $this->rejectNote);
        $this->rejectingId = null;
        $this->rejectNote = '';
        $this->showToast($this->tl('Request rejected', 'Solicitud rechazada', '정정요청을 반려했어요'));
    }

    // =================== render ===================

    public function render()
    {
        $vm = ViewModel::build($this->state());

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
            'newAssignCompany' => $this->newAssignCompany, 'newAssignTeam' => $this->newAssignTeam, 'newAssignRelation' => $this->newAssignRelation,
            'bstep' => $this->bstep, 'scanF' => $this->scanF, 'scanB' => $this->scanB, 'scanN' => $this->scanN,
            'facePhotoData' => $this->facePhotoData, 'faceBox' => $this->faceBox,
            'regTeam' => $this->regTeam, 'regType' => $this->regType, 'regAccess' => $this->regAccess,
            'regFirst' => $this->regFirst, 'regLast' => $this->regLast, 'regCoName' => $this->regCoName,
            'regRoleTitle' => $this->regRoleTitle, 'regIssued' => $this->regIssued,
            'nfcUidManual' => $this->nfcUidManual,
            'backQrValue' => $this->backQrValue,
            'companyModal' => $this->companyModal, 'teamModal' => $this->teamModal,
            'editCompanyId' => $this->editCompanyId, 'editTeamId' => $this->editTeamId,
            'deleteCompanyId' => $this->deleteCompanyId, 'deleteTeamId' => $this->deleteTeamId,
            'siteModal' => $this->siteModal, 'siteLat' => $this->siteLat, 'siteLng' => $this->siteLng, 'siteRadius' => $this->siteRadius,
            'siteName' => $this->siteName, 'siteCity' => $this->siteCity, 'deleteSiteId' => $this->deleteSiteId,
            'newCoName' => $this->newCoName, 'newCoSite' => $this->newCoSite,
            'newTeamName' => $this->newTeamName, 'newTeamLead' => $this->newTeamLead,
            'attView' => $this->attView, 'attDate' => $this->attDate ?: now()->format('Y-m-d'),
            'qrMode' => $this->qrMode, 'qrTeam' => $this->qrTeam,
            'payDetail' => $this->payDetail, 'payVoucher' => $this->payVoucher,
            'checkNo' => $this->checkNo, 'payDate' => $this->payDate,
            'mobileTab' => $this->mobileTab, 'clock' => $this->clock, 'clockInTime' => $this->clockInTime,
            'earlyOpen' => $this->earlyOpen, 'earlyReasonVal' => $this->earlyReasonVal, 'earlyCustom' => $this->earlyCustom,
            'noLunchToday' => $this->noLunchToday, 'lunchOv' => $this->lunchOv,
            'correctionOpen' => $this->correctionOpen, 'correctionDate' => $this->correctionDate,
            'correctionType' => $this->correctionType, 'correctionIn' => $this->correctionIn,
            'correctionOut' => $this->correctionOut, 'correctionReason' => $this->correctionReason,
            'rejectingId' => $this->rejectingId, 'actorIsAdmin' => $this->actorIsAdmin(),
            'commsChannel' => $this->commsChannel, 'commsCompose' => $this->commsCompose,
            'commsNewDm' => $this->commsNewDm, 'commsDmSearch' => $this->commsDmSearch,
            'commsPane' => $this->commsPane,
            'bellOpen' => $this->bellOpen,
            'actorId' => $this->actorId(),
            'canManage' => $this->canManage(),
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
