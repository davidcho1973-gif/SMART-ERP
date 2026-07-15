<?php

namespace App\Livewire;

use App\Models\Absence;
use App\Models\Assignment;
use App\Models\AttendanceCorrection;
use App\Models\AuditLog;
use App\Models\Leave;
use App\Models\Channel;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Punch;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use App\Services\BadgeAnalyzer;
use App\Services\ReportFormatter;
use App\Support\Access;
use App\Support\Attendance;
use App\Support\Attach;
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
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class WorkforceApp extends Component
{
    use WithFileUploads;

    // ---- primary navigation / UI state ----
    public string $screen = 'login';

    /** identity props are server-set ONLY — #[Locked] rejects client tampering */
    #[Locked]
    public string $role = 'admin';

    /** the account's access ceiling — the highest view it may switch to */
    #[Locked]
    public string $access = 'admin';

    public string $lang = 'en';

    public string $dashLayout = 'A';

    public string $site = 'all';

    // ---- employees ----
    public ?int $selectedEmp = null;

    public string $empFilter = 'active';

    // ---- invite → activate ----
    public bool $inviteOpen = false;

    public string $invFirst = '';

    public string $invLast = '';

    public string $invEmail = '';

    public string $invPhone = '';

    public string $invRole = 'worker';

    public string $invSite = '';

    public string $invCompany = '';

    /** out-of-state dispatch (파견) for an invited employee */
    public string $invDispatchTo = '';

    public string $invDispatchFrom = '';

    public string $invDispatchUntil = '';

    public string $invDispatchNote = '';

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

    /** owner-typed login password for the open employee (set-password panel) */
    public string $empPassword = '';

    // ---- badge wizard ----
    public string $bstep = 'front';

    public string $scanF = 'idle';

    public string $scanB = 'idle';

    public string $scanN = 'idle';

    public string $regTeam = 't1';

    public string $regType = 'worker_local';

    public string $regPayType = 'hourly';   // salary | hourly | both

    public string $regLang = 'es';          // app language for the new employee (en | es | ko)

    public string $regAccess = 'worker';

    public string $regNat = 'LOCAL';        // nationality: LOCAL | 한국인 (from the 직원 구분 default)

    // real registration inputs (prefilled by the demo OCR, always editable)
    public string $regFirst = '';

    public string $regLast = '';

    public string $regCoName = '';

    public string $regRoleTitle = '';

    public string $regIssued = '';

    public string $regRate = '';

    public string $regPhone = '';

    public string $regEmail = '';

    /** out-of-state dispatch (파견) for a newly registered employee */
    public string $regDispatchTo = '';

    public string $regDispatchFrom = '';

    public string $regDispatchUntil = '';

    public string $regDispatchNote = '';

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

    // team work shift (HH:MM 24h strings; empty = not configured)
    public string $teamShiftIn = '';

    public string $teamShiftOut = '';

    public string $teamSatIn = '';

    public string $teamSatOut = '';

    // team-lead paid-time adjustment (attendance screen)
    public ?int $adjPunchId = null;

    public string $adjPaidIn = '';

    public string $adjPaidOut = '';

    public string $adjPaidReason = '';

    /** field-lead mobile: id of the crew whose shift is being edited (inline editor) */
    public ?string $crewShiftTeam = null;

    /** admin "view as → field lead": the crew-lead employee the preview is standing in for */
    #[Locked]
    public ?int $previewEmpId = null;

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

    public ?int $voidPunchId = null;      // employee whose punch (on attDate) is being voided

    public string $qrMode = 'reader';

    public string $qrTeam = 't1';

    // ---- payroll ----
    public ?int $payDetail = null;

    /** payroll: badge NFC tag / QR-code lookup box */
    public string $badgeLookup = '';

    public bool $payVoucher = false;

    public string $checkNo = '';

    public string $payDate = 'Jul 1, 2026';

    // ---- payroll export (settlement period + recipient dropdown) ----
    public string $payStart = '';

    public string $payEnd = '';

    public string $payRecipient = 'hourly';   // hourly | all | salary | co:<id> | tm:<id>

    // ---- accounting screen sub-tab ----
    public string $acctTab = 'dashboard';

    // ---- accounting · expenses / receipts (M2) ----
    public bool $expFormOpen = false;

    public string $expCategory = 'other';

    public string $expVendor = '';

    public string $expAmount = '';

    public string $expDate = '';

    public string $expSite = '';

    public string $expNote = '';

    public $expFile = null;                 // receipt image (Livewire temp upload)

    public ?int $expSelId = null;           // expense open in the detail pane

    public ?int $expRejectId = null;        // expense being rejected

    public string $expRejectNote = '';

    public string $expFilter = 'all';       // status filter: all·pending·approved·rejected

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

    // ---- worker self-report of a non-working status (결근 · 휴가 · 퇴사) ----
    public string $statusSheet = '';   // '' | absent | leave | resign

    public string $absentReason = '';

    public string $leaveStart = '';

    public string $leaveEnd = '';

    public string $leaveReason = '';

    public string $resignOn = '';

    public string $resignReason = '';

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

    /** approver: id of the request being adjusted (edit-times-before-approve box open) */
    public ?int $adjustingId = null;

    public string $adjustIn = '';        // HH:MM (24h) — approver's edited clock-in

    public string $adjustOut = '';       // HH:MM (24h) — approver's edited clock-out

    // ---- internal comms (announcements · invite-based rooms · DM · bell) ----
    public ?int $commsChannel = null;

    public string $commsCompose = '';

    /** a pending file attachment for the open channel (Livewire temp upload) */
    public $commsFile = null;

    /** new-chat picker open (pick 1 → DM, several → group room) */
    public bool $commsNewDm = false;

    public string $commsDmSearch = '';

    /** picked employee ids in the new-chat / invite picker */
    public array $commsPicked = [];

    public string $commsRoomName = '';

    /** invite picker open for the active group room */
    public bool $commsInviteOpen = false;

    /** last unread total seen by the poller — drives the "new message" chime */
    public ?int $lastPing = null;

    /** voice/daily report composer open for the active group room */
    public bool $reportOpen = false;

    /** AI-formatted report draft, editable before posting */
    public string $reportDraft = '';

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

    // =================== access policy (central gate) ===================

    /**
     * The actor's effective roles. Real mode reads the account's stored access
     * (legacy values map via Access::canonical); demo personas map admin ⇒ owner,
     * manager ⇒ site_manager. An employee who leads a crew gains 'crew_lead'.
     */
    /** request-scoped memo — Livewire re-instantiates per request, so this can't go stale */
    protected ?array $actorRolesMemo = null;

    protected function actorRoles(): array
    {
        if ($this->actorRolesMemo !== null) {
            return $this->actorRolesMemo;
        }
        if ($this->isDemo()) {
            $base = match ($this->role) {
                'admin' => 'owner',
                'manager' => 'site_manager',
                default => 'worker',
            };
            $empId = $this->role === 'worker' ? $this->meEmployeeId() : $this->selfEmployeeId();
        } else {
            $base = Auth::check() ? Access::canonical(Auth::user()->access) : 'worker';
            $empId = Auth::user()?->employee_id;
        }
        $roles = [$base];
        if ($empId && Access::leadsTeams(Employee::find($empId))) {
            $roles[] = 'crew_lead';
        }

        return $this->actorRolesMemo = $roles;
    }

    /** Site ids a site-scoped actor is confined to; null = unrestricted (D-3 hard scope). */
    protected function scopeSiteIds(): ?array
    {
        $roles = $this->actorRoles();
        if (array_intersect($roles, ['owner', 'hr_admin'])) {
            return null;
        }
        if (! in_array('site_manager', $roles, true)) {
            return null; // workers/crew leads are gated by role, not site scope
        }
        $emp = ($id = $this->selfEmployeeId()) ? Employee::find($id) : null;
        $sid = $emp?->site_id ?? Site::first()?->id;

        return array_values(array_filter([$sid]));
    }

    /** The site id a target maps to, for scope checks. */
    protected function targetSiteId(mixed $target): ?string
    {
        return match (true) {
            $target instanceof Employee => $target->site_id,
            $target instanceof Team => Company::find($target->company_id)?->site_id,
            $target instanceof Company => $target->site_id,
            $target instanceof Site => $target->id,
            is_string($target) => $target,
            default => null,
        };
    }

    /**
     * The one permission gate: role holds the capability AND the target sits
     * inside the actor's scope (owner/hr_admin: global · site_manager: their
     * site · crew_lead: their crew). Call with no target for screen-level gates.
     */
    protected function can(string $cap, mixed $target = null): bool
    {
        $roles = $this->actorRoles();
        if (! Access::allows($roles, $cap)) {
            return false;
        }
        if ($target === null || array_intersect($roles, ['owner', 'hr_admin'])) {
            return true;
        }
        if (in_array('site_manager', $roles, true)) {
            $siteId = $this->targetSiteId($target);

            return $siteId !== null && in_array($siteId, $this->scopeSiteIds() ?? [], true);
        }
        // crew_lead overlay: only their own crew — a person in it, or the team itself
        if (in_array('crew_lead', $roles, true)) {
            $empId = $this->isDemo()
                ? ($this->role === 'worker' ? $this->meEmployeeId() : $this->selfEmployeeId())
                : Auth::user()?->employee_id;
            $leads = $empId ? Access::leadsTeams(Employee::find($empId)) : [];
            if ($target instanceof Employee) {
                return $target->team_id !== null && in_array($target->team_id, $leads, true);
            }
            if ($target instanceof Team) {
                return in_array($target->id, $leads, true);
            }
        }

        return false;
    }

    /** Corrections: may the actor decide requests org-wide (owner/hr_admin)? */
    protected function correctionsGlobal(): bool
    {
        return (bool) array_intersect($this->actorRoles(), ['owner', 'hr_admin']);
    }

    /**
     * The access value that may actually be granted: role changes require the
     * roles.assign capability, the new role must be assignable by the actor, and
     * nobody edits an account above their own rank. Otherwise the value is kept
     * (edits) or clamped to worker (badge registration).
     */
    protected function grantableAccess(?Employee $target, string $wanted): string
    {
        $current = $target?->access ?? 'worker';
        if (Access::canonical($wanted) === Access::canonical($current) && $target !== null) {
            return $current; // unchanged
        }
        $actorRank = max(array_map(fn ($r) => Access::rank($r), $this->actorRoles()));
        $allowed = $this->can('roles.assign')
            && in_array(Access::canonical($wanted), Access::assignable($this->primaryRole()), true)
            && Access::rank($current) <= $actorRank;

        return $allowed ? $wanted : ($target !== null ? $current : 'worker');
    }

    /** Leave an audit row for a permission-sensitive act (Phase 4). */
    protected function audit(string $action, string $target = '', string $detail = ''): void
    {
        $emp = $this->actorEmployee();
        AuditLog::create([
            'actor_id' => $emp?->id,
            'actor_name' => $emp ? trim($emp->first.' '.$emp->last) : ($this->isDemo() ? 'demo·'.$this->role : 'system'),
            'action' => $action,
            'target' => $target !== '' ? $target : null,
            'detail' => $detail !== '' ? $detail : null,
        ]);
    }

    /** The actor's primary (highest) role name. */
    protected function primaryRole(): string
    {
        $roles = $this->actorRoles();
        usort($roles, fn ($a, $b) => Access::rank($b) <=> Access::rank($a));

        return $roles[0] ?? 'worker';
    }

    /** D-3 hard scope: a site-scoped manager cannot point the app at another site. */
    public function updatedSite($value): void
    {
        $scope = $this->scopeSiteIds();
        if ($scope !== null && ! in_array($value, $scope, true)) {
            $this->site = $scope[0] ?? 'all';
        }
    }

    /** Pin the site selector inside the actor's scope (login / persona switch). */
    protected function pinSiteToScope(): void
    {
        $scope = $this->scopeSiteIds();
        if ($scope !== null && ! in_array($this->site, $scope, true)) {
            $this->site = $scope[0] ?? 'all';
        }
    }

    /** The employee behind the worker-mobile view. */
    protected function meEmployeeId(): int
    {
        // admin/manager previewing "view as → field lead": stand in for that crew
        // lead — display only, and never for a worker-ceiling account
        if ($this->previewEmpId !== null && $this->access !== 'worker') {
            return $this->previewEmpId;
        }
        // any authenticated user linked to an employee sees their own record
        if (! $this->isDemo() && Auth::check() && Auth::user()->employee_id) {
            return (int) Auth::user()->employee_id;
        }

        return 106; // sample worker (Carlos) — demo & admins with no linked employee
    }

    /**
     * The employee the current user may clock in/out — or null when nobody is.
     * Unlike meEmployeeId(), this NEVER falls back to a sample id for real
     * traffic: an unauthenticated or unlinked visitor can clock no one. This is
     * the gate for every worker-initiated write (clock, lunch, correction).
     */
    protected function clockableEmployeeId(): ?int
    {
        if ($this->isDemo()) {
            return $this->meEmployeeId();   // demo persona (Carlos / manager)
        }

        return (Auth::check() && Auth::user()->employee_id) ? (int) Auth::user()->employee_id : null;
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

        return Auth::check() && Access::rank(Auth::user()->access) >= Access::RANK['company_admin'];
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
        if (! Auth::check() || Access::rank(Auth::user()->access) < Access::RANK['company_admin']) {
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
            'role' => in_array(Access::canonical($u->access), ['owner', 'hr_admin'], true) ? 'Administrator' : 'Site Manager',
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
    public function doDeskClock(float|string|null $lat = null, float|string|null $lng = null, float|string|null $acc = null): void
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
        // geofence-verify the desk clock exactly like the mobile app. Office/admin
        // self clock-ins used to capture no location at all, so clocking in from
        // home went through unflagged. Never blocks — a denied/coarse fix is null.
        $site = $emp->site_id ? Site::find($emp->site_id) : null;
        $coords = Geo::coords($lat, $lng, $acc);
        [, $geoOk] = Geo::verify($site, $coords);
        if ($p->in_min === null) {
            $p->in_min = $nowMin;
            $p->out_min = null;
            $p->source = 'self';
            $p->in_lat = $coords['lat'] ?? null;
            $p->in_lng = $coords['lng'] ?? null;
            $p->in_acc = $coords['acc'] ?? null;
            $p->in_geo_ok = $geoOk;
            $p->save();
            $emp->update(['status' => 'present', 'in_t' => Shift::fmtMin($nowMin)]);
            $this->showToast($this->clockToast('w_done_in', $geoOk));
        } else {
            $p->out_min = $nowMin;
            $p->source = 'self';
            $p->out_lat = $coords['lat'] ?? null;
            $p->out_lng = $coords['lng'] ?? null;
            $p->out_acc = $coords['acc'] ?? null;
            $p->out_geo_ok = $geoOk;
            $p->save();
            $emp->update(['status' => 'off', 'out_t' => Shift::fmtMin($nowMin)]);
            $this->showToast($this->clockToast('w_done_out', $geoOk));
        }
    }

    protected function todayPunch(int $employeeId): Punch
    {
        return Punch::firstOrNew([
            'employee_id' => $employeeId,
            'work_date' => now()->format('Y-m-d'),
        ]);
    }

    /**
     * Clock-confirmation toast, with a mandatory off-site warning appended when the
     * fix was confidently outside the geofence. The punch is always kept (allow,
     * never block) — the warning just makes sure the person sees it, same on the
     * desktop self-clock as on the mobile app.
     */
    protected function clockToast(string $baseKey, ?bool $geoOk): string
    {
        $d = $this->dict();

        return $geoOk === false ? $d[$baseKey].' · '.$d['w_offsiteWarn'] : $d[$baseKey];
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
        [$this->payStart, $this->payEnd] = Payroll::currentPeriod();
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
        // first authenticated login flips an invited employee to active
        if ($u->employee_id && ($linked = Employee::find($u->employee_id)) && $linked->activated_at === null) {
            $linked->update(['activated_at' => now()]);
        }
        $canon = Access::canonical($u->access);
        $emp = $u->employee_id ? Employee::find($u->employee_id) : null;
        // Only the top office roles (owner/hr_admin) run the admin desktop. Every
        // other account is a mobile user. A FIELD LEAD — any site/company/crew
        // role, OR anyone wired as a crew's lead — gets the worker-mobile app WITH
        // the "우리 팀" crew panel, even when their crew has no members yet; a plain
        // worker gets it without. The access prop is only the VIEW ceiling (it
        // drives the top-bar role badge); real capabilities come from actorRoles().
        // Keeping a field lead at 'manager' makes the badge read 현장 팀장 (not
        // 작업자), while both mobile views stay locked to the phone.
        $isOffice = in_array($canon, ['owner', 'hr_admin'], true);
        $fieldLead = ! $isOffice && (
            in_array($canon, ['site_manager', 'company_admin', 'crew_lead'], true)
            || ($emp && Access::leadsTeams($emp) !== [])
        );
        $this->access = $isOffice ? 'admin' : ($fieldLead ? 'manager' : 'worker');
        $this->actorRolesMemo = null;
        if ($isOffice) {
            $this->role = 'admin';
            $this->screen = 'dashboard';
            // the linked employee's registered language is the app default
            $this->lang = in_array($emp?->lang, ['en', 'es', 'ko'], true) ? $emp->lang : 'en';
            $this->pinSiteToScope();
        } else {
            $this->role = 'worker';
            $this->screen = 'worker';
            $this->mobileTab = 'home';
            $this->lang = in_array($emp?->lang, ['en', 'es', 'ko'], true)
                ? $emp->lang
                : ($fieldLead ? 'ko' : 'es');
            Comms::ensureRooms();   // worker home board reads announcements + their rooms
            // restore today's clock state from the punch record (out · in · done)
            $p = $this->todayPunch($this->meEmployeeId());
            $this->clock = $this->clockStateFor($p);
            if ($this->clock === 'in') {
                $this->clockInTime = Shift::fmtMin($p->in_min);
                $this->noLunchToday = $p->no_lunch;
            }
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
        // 'lead' (field-lead mobile preview) sits at the manager rung
        return ['worker' => 1, 'lead' => 2, 'manager' => 2, 'admin' => 3][$r] ?? 0;
    }

    /** An active employee who leads a crew — the persona behind the field-lead preview. */
    protected function firstFieldLeadId(): ?int
    {
        $leadIds = Team::whereNotNull('lead')->pluck('lead')->unique()->all();
        if ($leadIds === []) {
            return null;
        }

        return Employee::whereIn('id', $leadIds)->where('emp', 'active')->orderBy('id')->value('id');
    }

    /**
     * Switch the active view within the account's access ceiling.
     * Admin → admin/manager/worker · manager → manager/worker · worker → worker only.
     */
    public function viewAs(string $target): void
    {
        if (! in_array($target, ['admin', 'lead', 'worker'], true)) {
            return;
        }
        // never let a view exceed the account's own access level
        if ($this->roleRank($target) > $this->roleRank($this->access)) {
            return;
        }
        if ($target === 'worker' || $target === 'lead') {
            // both render the worker-mobile UI; 'lead' previews it as a field lead
            // (points the phone at a crew lead so the "우리 팀" tab appears)
            $this->selectedEmp = null;
            $this->actorRolesMemo = null;
            $this->role = 'worker';
            $this->screen = 'worker';
            if ($target === 'lead') {
                $this->previewEmpId = $this->firstFieldLeadId();
            } else {
                $this->previewEmpId = null;
            }
            $this->mobileTab = 'home';   // always land on the clock screen first
            $this->syncWorkerClock();
        } else {
            $this->previewEmpId = null;
            $this->actorRolesMemo = null;
            $this->role = $target;
            if (in_array($this->screen, ['worker', 'login'], true)) {
                $this->screen = 'dashboard';
            }
            // landing on payroll without the permission bounces to the dashboard
            if ($this->screen === 'payroll' && ! $this->can('payroll.view')) {
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
            $this->actorRolesMemo = null;
            $this->role = 'worker';
            $this->screen = 'worker';
            $this->mobileTab = 'home';
            $this->lang = 'es';
            $this->syncWorkerClock();
        } elseif ($r === 'manager') {
            $this->actorRolesMemo = null;
            $this->role = 'manager';
            $this->screen = 'dashboard';
            $this->lang = 'ko';
            $this->pinSiteToScope();
        } else {
            $this->actorRolesMemo = null;
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
        // payroll & accounting are finance permissions (not hidden menus); workers have no desktop
        if (in_array($screen, ['payroll', 'accounting'], true) && ! $this->can('payroll.view')) {
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

    /**
     * Switch the sub-tab inside the Accounting screen. NOTE: the method must NOT
     * be named `acctTab` — that collides with the public $acctTab property and
     * Livewire's browser proxy would treat `acctTab(...)` as calling the string
     * value (silently doing nothing on click).
     */
    public function setAcctTab(string $k): void
    {
        if (! $this->can('payroll.view')) {
            return;
        }
        $this->acctTab = in_array($k, ['dashboard', 'expenses', 'materials', 'billing', 'invoice'], true) ? $k : 'dashboard';
    }

    // =================== accounting · expenses / receipts ===================

    private function resetExpenseForm(): void
    {
        $this->expCategory = 'other';
        $this->expVendor = '';
        $this->expAmount = '';
        $this->expNote = '';
        $this->expFile = null;
        $this->expDate = now()->format('Y-m-d');
        $this->expSite = ($this->site && $this->site !== 'all') ? $this->site : (\App\Models\Site::query()->value('id') ?? '');
    }

    /** Open the "add receipt" form. */
    public function openExpenseForm(): void
    {
        if (! $this->can('expenses.submit')) {
            return;
        }
        $this->resetExpenseForm();
        $this->expFormOpen = true;
    }

    public function closeExpenseForm(): void
    {
        $this->expFormOpen = false;
        $this->expFile = null;
    }

    public function clearExpFile(): void
    {
        $this->expFile = null;
    }

    /** Read the uploaded receipt with Gemini and pre-fill vendor / amount / date / category. */
    public function readReceipt(): void
    {
        if (! $this->expFile || ! $this->can('expenses.submit')) {
            return;
        }
        $analyzer = new \App\Services\ReceiptAnalyzer;
        if (! $analyzer->isConfigured()) {
            return;
        }
        try {
            $bytes = $this->expFile->get();
            $mime = (string) $this->expFile->getMimeType();
        } catch (\Throwable) {
            return;
        }
        $out = $analyzer->analyze($bytes, $mime);
        if ($out === null) {
            $this->showToast($this->tl('Could not read the receipt — enter it manually', 'No se pudo leer el recibo — ingrésalo manualmente', '영수증을 읽지 못했어요 — 직접 입력해 주세요'));

            return;
        }
        if ($out['vendor'] !== '') {
            $this->expVendor = mb_substr($out['vendor'], 0, 120);
        }
        if ($out['amount'] > 0) {
            $this->expAmount = (string) $out['amount'];
        }
        if ($out['date'] !== '') {
            $this->expDate = $out['date'];
        }
        $this->expCategory = $out['category'];
        $this->showToast($this->tl('Receipt read — please check the fields', 'Recibo leído — revisa los campos', '영수증을 읽었어요 — 값을 확인해 주세요'));
    }

    /** Create a pending expense from the form (with the receipt image, if any). */
    public function submitExpense(): void
    {
        if (! $this->can('expenses.submit')) {
            return;
        }
        $me = $this->actorEmployee();
        $amount = (float) str_replace(',', '', $this->expAmount);
        $site = ($this->expSite && $this->expSite !== 'all') ? $this->expSite : null;
        if (! $site || $amount <= 0) {
            $this->showToast($this->tl('Pick a site and enter an amount', 'Elige una obra e ingresa el monto', '현장과 금액을 입력해 주세요'));

            return;
        }
        $category = in_array($this->expCategory, \App\Models\Expense::CATEGORIES, true) ? $this->expCategory : 'other';
        $date = $this->expDate ?: now()->format('Y-m-d');

        // Everything below (receipt sniff/store + DB insert) is wrapped so any
        // failure surfaces as a toast instead of a raw 500.
        try {
            $att = ['att_disk' => null, 'att_path' => null, 'att_name' => null, 'att_mime' => null, 'att_size' => null];
            if ($this->expFile) {
                $why = \App\Support\Attach::reject($this->expFile);
                if ($why !== null) {
                    $this->showToast($why === 'size'
                        ? $this->tl('Receipt image is too large', 'La imagen es demasiado grande', '영수증 이미지가 너무 큽니다')
                        : $this->tl('That file type is not allowed', 'Tipo de archivo no permitido', '허용되지 않는 파일 형식입니다'));

                    return;
                }
                $ext = strtolower($this->expFile->getClientOriginalExtension());
                $disk = \App\Support\Attach::disk() ?? 'local';
                $name = \Illuminate\Support\Str::uuid()->toString().'.'.$ext;
                // storeAs streams the Livewire temp file from whatever disk it lives
                // on (R2/s3 or local); no ACL arg (Cloudflare R2 rejects ACLs).
                $path = $this->expFile->storeAs('receipts/'.$site, $name, $disk);
                $att = [
                    'att_disk' => $disk, 'att_path' => $path,
                    'att_name' => mb_substr($this->expFile->getClientOriginalName(), 0, 180),
                    'att_mime' => \App\Support\Attach::MIME[$ext] ?? (string) $this->expFile->getMimeType(),
                    'att_size' => (int) $this->expFile->getSize(),
                ];
            }

            \App\Models\Expense::create(array_merge([
                'site_id' => $site,
                'category' => $category,
                'vendor' => mb_substr(trim($this->expVendor), 0, 120) ?: null,
                'amount' => $amount,
                'spent_on' => $date,
                'note' => mb_substr(trim($this->expNote), 0, 500) ?: null,
                'status' => 'pending',
                'submitted_by' => $me?->id,
            ], $att));
        } catch (\Throwable $e) {
            report($e);   // logged for diagnostics; users see a friendly message
            $this->showToast($this->tl('Could not save the receipt — please try again', 'No se pudo guardar el recibo — inténtalo de nuevo', '영수증을 저장하지 못했어요 — 다시 시도해 주세요'));

            return;
        }

        $this->expFormOpen = false;
        $this->expFile = null;
        $this->showToast($this->tl('Receipt added', 'Recibo agregado', '영수증을 등록했어요'));
    }

    public function selectExpense(int $id): void
    {
        $this->expSelId = $id;
    }

    public function approveExpense(int $id): void
    {
        if (! $this->can('expenses.decide')) {
            return;
        }
        $e = \App\Models\Expense::find($id);
        if (! $e || ! $e->isPending()) {
            return;
        }
        $e->update(['status' => 'approved', 'decided_by' => $this->actorEmployee()?->id, 'decided_at' => now(), 'reject_reason' => null]);
        $this->showToast($this->tl('Approved', 'Aprobado', '승인했어요'));
    }

    public function askRejectExpense(int $id): void
    {
        if (! $this->can('expenses.decide')) {
            return;
        }
        $this->expRejectId = $id;
        $this->expRejectNote = '';
    }

    public function cancelExpReject(): void
    {
        $this->expRejectId = null;
        $this->expRejectNote = '';
    }

    public function rejectExpense(int $id): void
    {
        if (! $this->can('expenses.decide')) {
            return;
        }
        $e = \App\Models\Expense::find($id);
        if (! $e || ! $e->isPending()) {
            return;
        }
        $e->update([
            'status' => 'rejected', 'decided_by' => $this->actorEmployee()?->id,
            'decided_at' => now(), 'reject_reason' => mb_substr(trim($this->expRejectNote), 0, 300) ?: null,
        ]);
        $this->expRejectId = null;
        $this->expRejectNote = '';
        $this->showToast($this->tl('Rejected', 'Rechazado', '반려했어요'));
    }

    // =================== internal comms ===================

    /** The employee acting as the current user (display side — never provisions a record). */
    protected function actorId(): int
    {
        // previewing the field-lead view never impersonates in comms/audit —
        // an admin exploring the lead's screen still speaks as THEMSELVES
        if ($this->previewEmpId !== null) {
            return $this->selfEmployeeId() ?? $this->meEmployeeId();
        }
        if ($this->role === 'worker') {
            return $this->meEmployeeId();
        }

        return $this->selfEmployeeId() ?? $this->meEmployeeId();
    }

    /** The employee acting as the current user, provisioning a staff record if needed. */
    protected function actorEmployee(): ?Employee
    {
        $id = $this->previewEmpId !== null
            ? ($this->ensureSelfEmployee() ?? $this->meEmployeeId())
            : ($this->role === 'worker' ? $this->meEmployeeId() : ($this->ensureSelfEmployee() ?? $this->meEmployeeId()));

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
        $hasFile = $this->commsFile !== null;
        if (($body === '' && ! $hasFile) || ! $this->commsChannel) {
            return;
        }
        $me = $this->actorEmployee();
        if (! $me) {
            return;
        }
        $ch = Channel::find($this->commsChannel);
        if (! $ch || ! Comms::canPost($ch, $me, $this->can('comms.announce'))) {
            $this->showToast($this->tl('You can’t post here', 'No puedes publicar aquí', '여기에는 글을 쓸 수 없어요'));

            return;
        }
        $att = ['att_disk' => null, 'att_path' => null, 'att_name' => null, 'att_mime' => null, 'att_size' => null];
        if ($hasFile) {
            if (! Attach::enabled()) {
                $this->showToast($this->tl('File sharing is not set up yet', 'El envío de archivos no está configurado', '파일 공유가 아직 설정되지 않았어요'));

                return;
            }
            $why = Attach::reject($this->commsFile);
            if ($why !== null) {
                $msg = match ($why) {
                    'size' => $this->tl('File is too large', 'Archivo demasiado grande', '파일 용량이 너무 큽니다'),
                    default => $this->tl('This file type is not allowed', 'Tipo de archivo no permitido', '허용되지 않는 파일 형식입니다'),
                };
                $this->showToast($msg);

                return;
            }
            $stored = Attach::store($this->commsFile, (int) $ch->id);
            $att = ['att_disk' => $stored['disk'], 'att_path' => $stored['path'], 'att_name' => $stored['name'], 'att_mime' => $stored['mime'], 'att_size' => $stored['size']];
        }
        Message::create(array_merge([
            'channel_id' => $ch->id,
            'sender_id' => $me->id,
            'body' => mb_substr($body, 0, 2000),
        ], $att));
        $this->commsCompose = '';
        $this->commsFile = null;
        Comms::markRead($ch, $me);
    }

    /** Clear a picked (not-yet-sent) attachment. */
    public function clearCommsFile(): void
    {
        $this->commsFile = null;
    }

    /** Open/close the new-chat picker (pick 1 person → DM, several → a group room). */
    public function toggleNewDm(): void
    {
        $this->commsNewDm = ! $this->commsNewDm;
        $this->commsInviteOpen = false;
        $this->commsDmSearch = '';
        $this->commsPicked = [];
        $this->commsRoomName = '';
    }

    /** Toggle a person in the picker's selection. */
    public function togglePick(int $employeeId): void
    {
        $key = array_search($employeeId, $this->commsPicked, true);
        if ($key !== false) {
            unset($this->commsPicked[$key]);
            $this->commsPicked = array_values($this->commsPicked);
        } elseif (Employee::whereKey($employeeId)->exists()) {
            $this->commsPicked[] = $employeeId;
        }
    }

    /** Create the chat from the picker: one pick + no name → DM, otherwise a group room. */
    public function createChat(): void
    {
        $me = $this->actorEmployee();
        if (! $me) {
            return;
        }
        $picked = array_values(array_filter(array_map('intval', $this->commsPicked), fn ($id) => $id !== $me->id));
        if (empty($picked)) {
            return;
        }
        $name = trim($this->commsRoomName);
        $ch = (count($picked) === 1 && $name === '')
            ? Comms::findOrCreateDm($me->id, $picked[0])
            : Comms::createRoom($name, $me->id, $picked);
        $this->openRoom($ch);
    }

    /** Quick 1:1 from a person row (still available from the bell/search). */
    public function startDm(int $employeeId): void
    {
        $me = $this->actorEmployee();
        if (! $me || $employeeId === $me->id || ! Employee::whereKey($employeeId)->exists()) {
            return;
        }
        $this->openRoom(Comms::findOrCreateDm($me->id, $employeeId));
    }

    /** Focus a channel and reset the picker state. */
    protected function openRoom(Channel $ch): void
    {
        $me = $this->actorEmployee();
        $this->commsChannel = $ch->id;
        $this->commsNewDm = false;
        $this->commsInviteOpen = false;
        $this->commsDmSearch = '';
        $this->commsPicked = [];
        $this->commsRoomName = '';
        $this->commsCompose = '';
        $this->commsPane = 'thread';
        if ($me) {
            Comms::markRead($ch, $me);
        }
    }

    /** Open the invite picker for the active group room. */
    public function openInvite(): void
    {
        $ch = $this->commsChannel ? Channel::find($this->commsChannel) : null;
        if (! $ch || $ch->type !== 'group') {
            return;
        }
        $this->commsInviteOpen = true;
        $this->commsNewDm = false;
        $this->commsDmSearch = '';
        $this->commsPicked = [];
    }

    /** Add the picked people to the active group room. */
    public function inviteMembers(): void
    {
        $me = $this->actorEmployee();
        $ch = $this->commsChannel ? Channel::find($this->commsChannel) : null;
        if (! $me || ! $ch || $ch->type !== 'group' || ! Comms::canAccess($ch, $me)) {
            return;
        }
        $picked = array_map('intval', $this->commsPicked);
        if (! empty($picked)) {
            Comms::addMembers($ch, $picked);
            $this->showToast($this->tl('Invited', 'Invitado', '초대했어요'));
        }
        $this->commsInviteOpen = false;
        $this->commsPicked = [];
        $this->commsDmSearch = '';
    }

    /** Leave the active group room. */
    public function leaveActiveRoom(): void
    {
        $me = $this->actorEmployee();
        $ch = $this->commsChannel ? Channel::find($this->commsChannel) : null;
        if (! $me || ! $ch || $ch->type !== 'group') {
            return;
        }
        Comms::leaveRoom($ch, $me->id);
        $this->commsChannel = null;
        $this->commsPane = 'list';
        $this->showToast($this->tl('Left the room', 'Saliste de la sala', '채팅방을 나갔어요'));
    }

    /**
     * Polled a few times a minute: chime when the unread total grows since last check.
     * First poll just seeds the baseline so an already-unread inbox doesn't ring.
     */
    public function pollComms(): void
    {
        $me = Employee::find($this->actorId());
        if (! $me) {
            $this->skipRender();

            return;
        }
        $total = Comms::totalUnread($me);
        // never chime while the voice-report composer is open (mic is live)
        if (! $this->reportOpen && $this->lastPing !== null && $total > $this->lastPing) {
            $this->dispatch('comms-ping');   // Alpine plays the notification sound
        }
        $unchanged = $this->lastPing === $total;
        $this->lastPing = $total;
        // steady state: nothing new — skip the (expensive) full re-render entirely,
        // so the poll costs a handful of unread queries instead of every screen
        if ($unchanged) {
            $this->skipRender();
        }
    }

    // =================== voice daily report (dictate → AI-format → post) ===================

    /** Open the report composer for the active group room. */
    public function openReport(): void
    {
        $me = $this->actorEmployee();
        $ch = $this->commsChannel ? Channel::find($this->commsChannel) : null;
        if (! $me || ! $ch || $ch->type !== 'group' || ! Comms::canPost($ch, $me, $this->can('comms.announce'))) {
            return;
        }
        $this->reportOpen = true;
        $this->reportDraft = '';
    }

    public function closeReport(): void
    {
        $this->reportOpen = false;
        $this->reportDraft = '';
    }

    /**
     * Turn the dictated/typed raw update into a structured report draft.
     * The raw text comes straight from the browser (speech recognition or typing).
     * If the AI is unconfigured/unreachable the raw text becomes the draft so the
     * report can still be finished by hand — dictation never blocks posting.
     */
    public function generateReport(string $raw): void
    {
        if (! $this->reportOpen) {
            return;
        }
        $raw = trim($raw);
        $d = $this->dict();
        if ($raw === '') {
            $this->showToast($d['r_needText']);

            return;
        }
        $me = $this->actorEmployee();
        if (! $me) {
            return;
        }

        $formatter = app(ReportFormatter::class);
        $sections = $formatter->isConfigured() ? $formatter->format(mb_substr($raw, 0, 4000), $this->lang) : null;
        if ($sections === null) {
            $this->reportDraft = $raw;   // manual fallback — keep what was said
            $this->showToast($formatter->isConfigured() ? $d['r_aiFail'] : $d['b_aiOff']);

            return;
        }

        // the report body is written in the language the speaker used — labels follow it.
        // Korean speech stays Korean-only; en/es reports get a Korean translation block.
        $repLang = $sections['lang'];
        $Lr = (array) trans('app', [], $repLang);
        $team = $me->team_id ? Team::find($me->team_id) : null;
        $section = function (array $L, string $label, string $body): array {
            return $body === '' ? [] : ['', '▪ '.$L[$label], $body];
        };
        $lines = [
            '📋 '.$Lr['r_title'].' · '.now()->format('Y-m-d (D)'),
            $Lr['r_by'].': '.$me->displayName($repLang).($team ? ' · '.$team->name : ''),
            '',
            '▪ '.$Lr['r_done'],
            $sections['done'],
            ...$section($Lr, 'r_issues', $sections['issues']),
            ...$section($Lr, 'r_plan', $sections['plan']),
        ];
        if ($repLang !== 'ko' && $sections['done_ko'] !== '') {
            $Lko = (array) trans('app', [], 'ko');
            $lines = [
                ...$lines,
                '',
                '────────────────',
                '🇰🇷 한국어 번역',
                '',
                '▪ '.$Lko['r_done'],
                $sections['done_ko'],
                ...$section($Lko, 'r_issues', $sections['issues_ko']),
                ...$section($Lko, 'r_plan', $sections['plan_ko']),
            ];
        }
        $this->reportDraft = implode("\n", $lines);
        $this->showToast($d['r_ready']);
    }

    /** Post the (possibly hand-edited) report draft into the active group room. */
    public function postReport(): void
    {
        $body = trim($this->reportDraft);
        if ($body === '' || ! $this->reportOpen) {
            return;
        }
        $me = $this->actorEmployee();
        $ch = $this->commsChannel ? Channel::find($this->commsChannel) : null;
        if (! $me || ! $ch || ! Comms::canPost($ch, $me, $this->can('comms.announce'))) {
            return;
        }
        Message::create([
            'channel_id' => $ch->id,
            'sender_id' => $me->id,
            'body' => mb_substr($body, 0, 4000),
        ]);
        Comms::markRead($ch, $me);
        $this->reportOpen = false;
        $this->reportDraft = '';
        $this->showToast($this->dict()['r_posted']);
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
        $this->actorRolesMemo = null;
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
        // show the true team; an unset/stale team_id resolves to "미배정" (empty
        // option) rather than silently defaulting to the first crew in the list
        $teamId = ($e->team_id !== null && Team::find($e->team_id)) ? $e->team_id : '';
        $teamModel = $teamId ? Team::find($teamId) : null;
        $companyId = $teamModel?->company_id
            ?? (($e->company_id && Company::find($e->company_id)) ? $e->company_id : null);
        $this->editForm = [
            'first' => $e->first, 'last' => $e->last, 'company' => $companyId,
            'team' => $teamId, 'role' => $e->role, 'rate' => $e->rate,
            'type' => match ($e->type) {
                'manager' => $e->nat === '한국인' || $e->lang === 'ko' ? 'manager_ko' : 'manager_local',
                'third_party' => 'third_party',
                default => $e->nat === '한국인' || $e->lang === 'ko' ? 'worker_ko' : 'worker_local',
            },
            'pay_type' => in_array($e->pay_type, ['salary', 'hourly', 'both'], true) ? $e->pay_type : 'hourly',
            'lang' => in_array($e->lang, ['en', 'es', 'ko'], true) ? $e->lang : 'es',
            'issued' => $e->issued, 'phone' => $e->phone, 'email' => $e->email,
            'nat' => in_array($e->nat, ['LOCAL', '한국인'], true) ? $e->nat : '', 'access' => $e->access,
            'dispatch_to' => (string) ($e->dispatch_to ?? ''), 'dispatch_from' => (string) ($e->dispatch_from ?? ''),
            'dispatch_until' => (string) ($e->dispatch_until ?? ''), 'dispatch_note' => (string) ($e->dispatch_note ?? ''),
        ];
    }

    /** Rotate the selected employee's badge photo 90° clockwise (fixes sideways photos). */
    public function rotateBadgePhoto(): void
    {
        if (! $this->selectedEmp || ! $this->can('employees.edit', Employee::find($this->selectedEmp))) {
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
        if (! $this->selectedEmp || ! $this->can('assignments.manage', Employee::find($this->selectedEmp))) {
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
        if (! $this->selectedEmp || ! $this->can('assignments.manage', Employee::find($this->selectedEmp))) {
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

    /** Open the lightweight employee-invite drawer (name + email + role + site). */
    public function openEmpInvite(): void
    {
        if (! $this->can('employees.register')) {
            return;
        }
        $this->reset(['invFirst', 'invLast', 'invEmail', 'invPhone', 'invCompany']);
        $this->invRole = 'worker';
        $scope = $this->scopeSiteIds();
        $this->invSite = $this->site !== 'all'
            ? $this->site
            : ($scope[0] ?? Site::first()?->id ?? '');
        $this->inviteOpen = true;
    }

    public function closeEmpInvite(): void
    {
        $this->inviteOpen = false;
    }

    /**
     * Create an "invited" employee: the minimum needed to log in and start
     * clocking (name, email, access, site). Rate/badge/crew come later. The
     * record stays activated_at = null until the person's first login.
     */
    public function saveEmpInvite(): void
    {
        $d = $this->dict();
        // company (if picked) fixes the site, mirroring the crew→company→site chain
        $company = $this->invCompany !== '' ? Company::find($this->invCompany) : null;
        $siteId = $company?->site_id ?: ($this->invSite ?: null);

        if (! $this->can('employees.register', $siteId)) {
            return;
        }
        if (trim($this->invFirst) === '' && trim($this->invLast) === '') {
            $this->showToast($d['inv_needName']);

            return;
        }
        $email = trim($this->invEmail);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->showToast($d['inv_needEmail']);

            return;
        }
        $phone = trim($this->invPhone);
        $dup = Employee::where('email', $email)
            ->orWhere(fn ($q) => $phone !== '' ? $q->where('phone', $phone) : $q->whereRaw('1=0'))
            ->exists();
        if ($dup) {
            $this->showToast($d['inv_dup']);

            return;
        }

        $granted = $this->grantableAccess(null, $this->invRole);
        $e = Employee::create([
            'emp_id' => $this->inviteEmpId(),
            'first' => trim($this->invFirst), 'last' => trim($this->invLast),
            'nat' => '', 'code' => '',
            'team_id' => null,
            'company_id' => $company?->id,
            'site_id' => $siteId,
            'role' => '', 'type' => 'worker', 'pay_type' => 'hourly', 'lang' => 'es',
            'access' => $granted,
            'rate' => 0,
            'phone' => $phone, 'email' => $email,
            'status' => 'off', 'in_t' => '—', 'out_t' => '—', 'wh' => 0,
            'emp' => 'active', 'term' => null,
            'activated_at' => null,   // invited — flips to active on first login
        ] + $this->dispatchPayload($this->invDispatchTo, $this->invDispatchFrom, $this->invDispatchUntil, $this->invDispatchNote));
        $this->audit('employee.invite', trim($e->first.' '.$e->last).' (#'.$e->id.')', $email.' · '.$granted);
        $this->inviteOpen = false;
        $this->empFilter = 'active';
        $this->reset(['invFirst', 'invLast', 'invEmail', 'invPhone', 'invDispatchTo', 'invDispatchFrom', 'invDispatchUntil', 'invDispatchNote']);
        $this->showToast($d['inv_sent']);
    }

    /** Unique human-ish id for a badge-less invited employee. */
    protected function inviteEmpId(): string
    {
        do {
            $id = 'INV-'.strtoupper(Str::random(6));
        } while (Employee::where('emp_id', $id)->exists());

        return $id;
    }

    public function saveEmp(): void
    {
        if (! $this->can('employees.edit', Employee::find($this->selectedEmp))) {
            return;
        }
        $e = Employee::find($this->selectedEmp);
        if ($e) {
            $this->applyEmpForm($e);
        }
        $this->selectedEmp = null;
        $this->showToast($this->dict()['e_save'].' ✓');
    }

    /** Persist the open drawer's editForm onto an employee (shared by save + approve). */
    protected function applyEmpForm(Employee $e): void
    {
        $type = $this->editForm['type'] ?? 'worker_local';
        // the crew determines the company (a crew belongs to exactly one company);
        // an empty selection means "미배정" and persists as null, not ''
        $teamId = $this->editForm['team'] ?? $e->team_id;
        $teamId = ($teamId === '' || $teamId === null) ? null : $teamId;
        $teamModel = $teamId ? Team::find($teamId) : null;
        $company = $teamModel?->company_id ?? ($this->editForm['company'] ?? $e->company_id);
        $prevAccess = $e->access;
        $prevRate = $e->rate;
        $granted = $this->grantableAccess($e, $this->editForm['access'] ?? $e->access);
        $e->update([
            'first' => $this->editForm['first'] ?? $e->first,
            'last' => $this->editForm['last'] ?? $e->last,
            'company_id' => $company,
            'site_id' => optional(Company::find($company))->site_id ?? $e->site_id,
            'team_id' => $teamId,
            'role' => $this->editForm['role'] ?? $e->role,
            'rate' => (float) ($this->editForm['rate'] ?? $e->rate),
            'type' => $this->empTypeFromForm($type),
            'pay_type' => in_array($this->editForm['pay_type'] ?? '', ['salary', 'hourly', 'both'], true)
                ? $this->editForm['pay_type']
                : $e->pay_type,
            'lang' => in_array($this->editForm['lang'] ?? '', ['en', 'es', 'ko'], true)
                ? $this->editForm['lang']
                : $e->lang,
            'issued' => $this->editForm['issued'] ?? $e->issued,
            'phone' => $this->editForm['phone'] ?? $e->phone,
            'email' => $this->editForm['email'] ?? $e->email,
            'nat' => $this->editForm['nat'] ?? $e->nat,
            'access' => $granted,
        ] + $this->dispatchPayload(
            (string) ($this->editForm['dispatch_to'] ?? ''),
            (string) ($this->editForm['dispatch_from'] ?? ''),
            (string) ($this->editForm['dispatch_until'] ?? ''),
            (string) ($this->editForm['dispatch_note'] ?? ''),
        ));
        if ($granted !== $prevAccess) {
            $this->audit('role.grant', $e->first.' '.$e->last.' (#'.$e->id.')', $prevAccess.' → '.$granted);
        }
        $newRate = (float) ($this->editForm['rate'] ?? $e->rate);
        if (abs($newRate - (float) $prevRate) > 0.001) {
            // a pay-rate change is money — always audited
            $this->audit('rate.change', $e->first.' '.$e->last.' (#'.$e->id.')', '$'.$prevRate.' → $'.$newRate);
        }
    }

    /**
     * Approve a self-service sign-up: apply the approver's finalized fields
     * (rate/access/crew) from the open drawer, activate the record, and create the
     * login account from the password the applicant chose during sign-up.
     */
    public function approveSignup(): void
    {
        $e = Employee::find($this->selectedEmp);
        if (! $e || $e->emp !== 'pending' || ! $this->can('employees.register', $e)) {
            return;
        }
        $this->applyEmpForm($e);
        $e->refresh();
        $email = trim((string) $e->email);
        if ($email !== '' && ! empty($e->join_password) && ! User::where('email', $email)->exists()) {
            $user = User::create([
                'name' => trim($e->first.' '.$e->last) ?: $email,
                'email' => $email,
                'password' => 'pending-'.Str::random(24),   // placeholder, replaced below
                'access' => $e->access,
                'employee_id' => $e->id,
            ]);
            // the stored join_password is ALREADY a hash — write it raw so the
            // model's 'hashed' cast doesn't double-hash it
            DB::table('users')->where('id', $user->id)->update(['password' => $e->join_password]);
        }
        $e->update(['emp' => 'active', 'join_password' => null, 'activated_at' => null]);
        $this->audit('signup.approve', trim($e->first.' '.$e->last).' (#'.$e->id.')', $email);
        $this->selectedEmp = null;
        $this->empFilter = 'active';
        $this->showToast($this->dict()['sg_approved'].' ✓');
    }

    /** Reject a self-service sign-up — the pending record is discarded. */
    public function rejectSignup(int $id): void
    {
        $e = Employee::find($id);
        if (! $e || $e->emp !== 'pending' || ! $this->can('employees.register', $e)) {
            return;
        }
        $this->audit('signup.reject', trim($e->first.' '.$e->last).' (#'.$e->id.')', (string) $e->email);
        Employee::where('id', $id)->delete();
        $this->selectedEmp = null;
        $this->showToast($this->dict()['sg_rejected']);
    }

    /**
     * Owner-only: set a login password for an employee's account so they can sign
     * in with email + password (no Google needed — e.g. an owner with no Gmail).
     * Creates the User if absent, else updates it; the User's 'hashed' cast hashes.
     */
    public function setEmpPassword(): void
    {
        $d = $this->dict();
        if (! $this->can('users.password')) {
            return;   // owner only
        }
        $e = Employee::find($this->selectedEmp);
        if (! $e) {
            return;
        }
        $email = trim((string) $e->email);
        if ($email === '') {
            $this->showToast($d['e_pwNeedEmail']);   // give them a login email first

            return;
        }
        if (strlen($this->empPassword) < 8) {
            $this->showToast($d['e_pwTooShort']);

            return;
        }
        $user = User::where('email', $email)->first();
        $name = trim($e->first.' '.$e->last);
        if ($user) {
            $user->update([
                'password' => $this->empPassword,   // hashed by the model cast
                'access' => $e->access,
                'employee_id' => $e->id,
                'name' => $name !== '' ? $name : $user->name,
            ]);
        } else {
            User::create([
                'name' => $name !== '' ? $name : $email,
                'email' => $email,
                'password' => $this->empPassword,
                'access' => $e->access,
                'employee_id' => $e->id,
            ]);
        }
        $this->audit('user.password_set', $name.' (#'.$e->id.')', $email);
        $this->empPassword = '';
        $this->showToast($d['e_pwDone'].' ✓');
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
        if (! $this->can('employees.delete', Employee::find($this->deleteId))) {
            return;
        }
        $d = Employee::find($this->deleteId);
        Employee::where('id', $this->deleteId)->delete();
        if ($d) {
            $this->audit('employee.delete', $d->first.' '.$d->last.' (#'.$d->id.')');
        }
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
        if (! $this->can('employees.terminate', Employee::find($this->terminateId))) {
            return;
        }
        $t = Employee::find($this->terminateId);
        Employee::where('id', $this->terminateId)->update([
            'emp' => 'terminated', 'term' => '07/01/2026', 'access' => 'worker', 'status' => 'off',
        ]);
        if ($t) {
            $this->audit('employee.terminate', $t->first.' '.$t->last.' (#'.$t->id.')');
        }
        $this->terminateId = null;
        $this->selectedEmp = null;
        $this->showToast($this->dict()['e_terminate'].' ✓');
    }

    public function reactivate(int $id): void
    {
        if (! $this->can('employees.terminate', Employee::find($id))) {
            return;
        }
        Employee::where('id', $id)->update(['emp' => 'active', 'term' => null]);
        $this->audit('employee.reactivate', '#'.$id);
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
        if (! $this->can($this->editCompanyId ? 'companies.edit' : 'companies.create')) {
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
        if (! $this->deleteCompanyId || ! $this->can('companies.delete')) {
            return;
        }
        $id = $this->deleteCompanyId;
        // unassign members and drop the company's crews
        Employee::where('company_id', $id)->update(['company_id' => null, 'team_id' => null]);
        Team::where('company_id', $id)->delete();
        Company::where('id', $id)->delete();
        $this->audit('company.delete', $id);
        $this->deleteCompanyId = null;
        $this->showToast($this->dict()['pj_deleted'].' ✓');
    }

    /** minutes since midnight → "HH:MM" (24h) for a time input, or '' */
    protected function hhmm24(?int $min): string
    {
        return $min === null ? '' : sprintf('%02d:%02d', intdiv($min, 60), $min % 60);
    }

    /** "HH:MM" (24h time input) → minutes since midnight, or null when blank/invalid */
    protected function minOf24(string $v): ?int
    {
        return preg_match('/^(\d{1,2}):(\d{2})$/', trim($v), $m) ? ((int) $m[1]) * 60 + (int) $m[2] : null;
    }

    public function openTeamModal(string $companyId): void
    {
        $this->editTeamId = null;
        $this->teamModal = $companyId;
        $this->newTeamName = '';
        $this->reset(['teamShiftIn', 'teamShiftOut', 'teamSatIn', 'teamSatOut']);
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
        $this->teamShiftIn = $this->hhmm24($t->shift_in);
        $this->teamShiftOut = $this->hhmm24($t->shift_out);
        $this->teamSatIn = $this->hhmm24($t->sat_in);
        $this->teamSatOut = $this->hhmm24($t->sat_out);
    }

    public function cancelTeam(): void
    {
        $this->teamModal = null;
        $this->editTeamId = null;
    }

    public function saveTeam(): void
    {
        if (! $this->can('teams.manage', $this->teamModal ? Company::find($this->teamModal) : null)) {
            return;
        }
        if (trim($this->newTeamName) === '' || ! $this->teamModal) {
            return;
        }
        // A configured shift is optional; leads set it later.
        $shift = $this->validShiftPayload();
        if ($shift === null) {
            return;   // invalid pair — an error toast was shown, keep the modal open
        }
        if ($this->editTeamId) {
            Team::where('id', $this->editTeamId)->update([
                'name' => trim($this->newTeamName),
                'lead' => $this->newTeamLead !== '' ? (int) $this->newTeamLead : null,
            ] + $shift);
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
            ] + $shift);
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
        if (! $this->deleteTeamId || ! $this->can('teams.manage', Team::find($this->deleteTeamId))) {
            return;
        }
        Employee::where('team_id', $this->deleteTeamId)->update(['team_id' => null]);
        Team::where('id', $this->deleteTeamId)->delete();
        $this->deleteTeamId = null;
        $this->showToast($this->dict()['pj_deleted'].' ✓');
    }

    public function changeLead(string $teamId, string $leadId): void
    {
        $t = Team::find($teamId);
        if (! $t || ! $this->can('teams.manage', $t)) {
            return;
        }
        $was = $t->lead;
        Team::where('id', $teamId)->update(['lead' => (int) $leadId]);
        // grants crew_lead powers — leave a trail
        $this->audit('team.lead', $t->name.' ('.$teamId.')', ($was ?? '—').' → '.$leadId);
    }

    // =================== site geofence ===================

    public function openSiteModal(string $id): void
    {
        $site = Site::find($id);
        if (! $site) {
            return;
        }
        $site->ensureJoinToken();   // mint the self-sign-up token so its QR/poster are ready
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
        if (! $this->can('sites.edit', $this->siteModal ? Site::find($this->siteModal) : null)) {
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
        if (! $this->siteModal || ! $this->can('sites.edit', Site::find($this->siteModal))) {
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
        $wasGeo = ($site->lat ?? '—').','.($site->lng ?? '—').' r'.($site->radius_m ?? '—');
        $site->update([
            'name' => $name !== '' ? $name : $site->name,
            'city' => trim($this->siteCity),
            'lat' => $lat !== '' ? (float) $lat : null,
            'lng' => $lng !== '' ? (float) $lng : null,
            'radius_m' => $radius > 0 ? $radius : Geo::DEFAULT_RADIUS_M,
        ]);
        // moving a geofence changes what counts as "on site" — leave a trail
        $this->audit('site.geo', $site->name.' ('.$site->id.')',
            $wasGeo.' → '.($site->lat ?? '—').','.($site->lng ?? '—').' r'.$site->radius_m);
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
        if (! $this->deleteSiteId || ! $this->can('sites.delete')) {
            return;
        }
        $id = $this->deleteSiteId;
        Company::where('site_id', $id)->update(['site_id' => null]);
        Employee::where('site_id', $id)->update(['site_id' => null]);
        Site::where('id', $id)->delete();
        $this->audit('site.delete', $id);
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

    /** Map a form "직원 구분" id (worker_local · worker_ko · manager_ko · manager_local · third_party) → stored Employee.type. */
    protected function empTypeFromForm(string $v): string
    {
        return match ($v) {
            'manager_ko', 'manager_local', 'manager' => 'manager',
            'third_party' => 'third_party',
            default => 'worker',
        };
    }

    public function setRegType(string $v): void
    {
        $this->regType = $v;
        $isMgr = in_array($v, ['manager_ko', 'manager_local'], true);
        $isKo = in_array($v, ['worker_ko', 'manager_ko'], true);
        $this->regAccess = $isMgr ? 'manager' : 'worker';
        // sensible default: managers & Korean staff are salaried, local/third-party hourly
        $this->regPayType = ($isMgr || $isKo) ? 'salary' : 'hourly';
        // suggested app language + nationality — both still freely changeable
        $this->regLang = $isKo ? 'ko' : 'es';
        $this->regNat = $isKo ? '한국인' : 'LOCAL';
    }

    public function setRegAccess(string $lvl): void
    {
        $this->regAccess = $lvl;
    }

    /**
     * Normalize the 파견 (out-of-state dispatch) form inputs into employee columns.
     * An empty destination clears the whole dispatch (not currently dispatched).
     */
    protected function dispatchPayload(string $to, string $from, string $until, string $note): array
    {
        $to = trim($to);
        if ($to === '') {
            return ['dispatch_to' => null, 'dispatch_from' => null, 'dispatch_until' => null, 'dispatch_note' => null];
        }
        $ymd = fn (string $v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($v)) ? trim($v) : null;

        return [
            'dispatch_to' => $to,
            'dispatch_from' => $ymd($from),
            'dispatch_until' => $ymd($until),
            'dispatch_note' => trim($note) ?: null,
        ];
    }

    public function finishBadge(): void
    {
        if (! $this->can('employees.register', Team::find($this->regTeam))) {
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
            'nat' => in_array($this->regNat, ['LOCAL', '한국인'], true) ? $this->regNat : '', 'code' => '',
            'team_id' => $team?->id, 'company_id' => $companyId, 'site_id' => $siteId,
            'role' => trim($this->regRoleTitle),
            'type' => $this->empTypeFromForm($this->regType),
            'pay_type' => in_array($this->regPayType, ['salary', 'hourly', 'both'], true) ? $this->regPayType : 'hourly',
            'lang' => in_array($this->regLang, ['en', 'es', 'ko'], true) ? $this->regLang : 'es',
            'access' => $this->grantableAccess(null, $this->regAccess),
            'rate' => (float) ($this->regRate ?: 0),
            'issued' => trim($this->regIssued),
            'phone' => trim($this->regPhone), 'email' => trim($this->regEmail),
            'badge_qr' => trim($this->backQrValue) ?: null,
            'badge_photo' => $this->facePhotoData ?: null,
            'status' => 'off', 'in_t' => '—', 'out_t' => '—', 'wh' => 0, 'emp' => 'active', 'term' => null,
        ] + $this->dispatchPayload($this->regDispatchTo, $this->regDispatchFrom, $this->regDispatchUntil, $this->regDispatchNote));
        $this->screen = 'employees';
        $this->bstep = 'front';
        $this->scanF = $this->scanB = $this->scanN = 'idle';
        $this->reset(['regFirst', 'regLast', 'regCoName', 'regRoleTitle', 'regIssued',
            'regRate', 'regPhone', 'regEmail', 'nfcUidManual', 'badgePhoto', 'backQrValue', 'backQrPhoto', 'backManual',
            'facePhotoData', 'faceBox', 'regDispatchTo', 'regDispatchFrom', 'regDispatchUntil', 'regDispatchNote']);
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
        if (! $this->can('punch.manual', Employee::find($id))) {
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
            $p->team_id = $p->team_id ?? $e->team_id;
            $p->company_id = $p->company_id ?? $e->company_id;
            $p->site_id = $p->site_id ?? $e->site_id;
            if ($p->shift_in_snap === null) {
                $p->stampShiftSnap();
            }
            $p->save();
            $e->update(['status' => 'present', 'in_t' => Shift::fmtMin($p->in_min)]);
        } else {
            if ($p->exists && $p->in_min !== null) {
                $p->out_min = $nowMin;
                $p->save();
            }
            $e->update(['status' => 'off', 'out_t' => $now]);
        }
        $this->audit('punch.manual', $e->first.' '.$e->last.' (#'.$e->id.')', $dir.' '.$now);
        $label = $dir === 'in' ? $this->dict()['q_in'] : $this->dict()['q_out'];
        $this->showToast($e->first.' '.$e->last.' · '.$label);
    }

    public function exportPayroll(): void
    {
        if (! $this->can('payroll.export')) {
            return;
        }
        $this->showToast($this->dict()['p_export'].' ✓');
    }

    // =================== punch void (admin redo of a mistaken clock) ===================

    /** Ask to void a worker's punch record for the date shown on the attendance screen. */
    public function askVoidPunch(int $employeeId): void
    {
        if (! $this->can('punch.manual', Employee::find($employeeId))) {
            return;
        }
        $has = Punch::where('employee_id', $employeeId)
            ->where('work_date', $this->attDate)
            ->whereNotNull('in_min')->exists();
        if ($has) {
            $this->voidPunchId = $employeeId;
        }
    }

    public function cancelVoidPunch(): void
    {
        $this->voidPunchId = null;
    }

    /**
     * Void the punch: the day's record is deleted so the worker can clock in
     * fresh (the day is no longer locked). Status resets when it's today.
     * Audited with the old times so nothing disappears silently.
     */
    public function confirmVoidPunch(): void
    {
        $id = $this->voidPunchId;
        $this->voidPunchId = null;
        if ($id === null || ! $this->can('punch.manual', Employee::find($id))) {
            return;
        }
        $e = Employee::find($id);
        $p = Punch::where('employee_id', $id)->where('work_date', $this->attDate)->first();
        if (! $e || ! $p) {
            return;
        }
        $was = ($p->in_min !== null ? Shift::fmtMin($p->in_min) : '—')
            .' → '.($p->out_min !== null ? Shift::fmtMin($p->out_min) : '—');
        $p->delete();
        // resync the denormalized live-status cache to TODAY's punch — voiding a
        // PAST punch must still clear a stale 'present' left over from a day the
        // worker forgot to clock out, or the employee list keeps showing 근무중
        $this->resyncLiveStatus($e);
        $this->audit('punch.void', $e->first.' '.$e->last.' (#'.$e->id.')', $this->attDate.' · '.$was);
        $this->showToast($this->dict()['ts_voided']);
    }

    /**
     * Rewrite an employee's denormalized live-status cache (status/in_t/out_t) to
     * match their punch for TODAY. Called after edits that can leave it stale — a
     * void, a manual correction — so every surface that reads the cache agrees with
     * the punch table. No today punch → "off / — / —".
     */
    protected function resyncLiveStatus(Employee $e): void
    {
        $p = Punch::where('employee_id', $e->id)->where('work_date', now()->format('Y-m-d'))->first();
        if ($p === null || $p->in_min === null) {
            $e->update(['status' => 'off', 'in_t' => '—', 'out_t' => '—']);
        } elseif ($p->out_min !== null) {
            $e->update(['status' => 'off', 'in_t' => Shift::fmtMin($p->in_min), 'out_t' => Shift::fmtMin($p->out_min)]);
        } else {
            $e->update(['status' => 'present', 'in_t' => Shift::fmtMin($p->in_min), 'out_t' => '—']);
        }
    }

    /**
     * Team-lead paid-time adjustment: open the editor for one punch, prefilled
     * with the currently-settled paid in/out so the lead nudges (approve OT past
     * the shift end, restore an early-leave) rather than retyping the shift.
     */
    public function openAdjust(int $punchId): void
    {
        $p = Punch::find($punchId);
        if (! $p) {
            return;
        }
        if (! $this->can('attendance.adjust', Employee::find($p->employee_id))) {
            return;
        }
        $settled = Attendance::settle($p);
        $this->adjPunchId = $punchId;
        $this->adjPaidIn = $this->hhmm24($p->adj_in_min ?? $settled['paidIn'] ?? $p->in_min);
        $this->adjPaidOut = $this->hhmm24($p->adj_out_min ?? $settled['paidOut'] ?? $p->out_min);
        $this->adjPaidReason = (string) ($p->adj_reason ?? '');
    }

    public function closeAdjust(): void
    {
        $this->adjPunchId = null;
        $this->adjPaidIn = $this->adjPaidOut = $this->adjPaidReason = '';
    }

    /** Save the lead's adjusted paid times onto the punch (audited). */
    public function saveAdjust(): void
    {
        $id = $this->adjPunchId;
        $p = $id ? Punch::find($id) : null;
        if (! $p) {
            return;
        }
        $e = Employee::find($p->employee_id);
        if (! $this->can('attendance.adjust', $e)) {
            return;
        }
        $in = $this->minOf24($this->adjPaidIn);
        $out = $this->minOf24($this->adjPaidOut);
        if ($in === null || $out === null) {
            $this->showToast($this->tl('Enter both times', 'Ingresa ambas horas', '두 시각을 모두 입력하세요'));

            return;
        }
        if ($out < $in) {
            $this->showToast($this->tl('Clock-out is before clock-in', 'La salida es antes de la entrada', '퇴근이 출근보다 빠릅니다'));

            return;
        }
        if (trim($this->adjPaidReason) === '') {
            $this->showToast($this->tl('A reason is required', 'Se requiere un motivo', '사유를 입력하세요'));

            return;
        }
        $p->update([
            'adj_in_min' => $in,
            'adj_out_min' => $out,
            'adj_reason' => trim($this->adjPaidReason),
            'adj_by' => $this->actorEmployee()?->id,
        ]);
        $this->audit('attendance.adjust',
            ($e ? $e->first.' '.$e->last.' (#'.$e->id.')' : '#'.$p->employee_id),
            $p->work_date.' · '.Shift::fmtMin($in).'→'.Shift::fmtMin($out).' · '.trim($this->adjPaidReason));
        $this->closeAdjust();
        $this->showToast($this->tl('Adjusted & applied', 'Ajustado y aplicado', '조정 반영 완료'));
    }

    /**
     * Field-lead mobile: open the inline shift editor for a crew they lead,
     * prefilled with the crew's current weekday/Saturday shift.
     */
    public function openCrewShift(string $teamId): void
    {
        $t = Team::find($teamId);
        if (! $t || ! $this->can('shifts.manage', $t)) {
            return;
        }
        $this->crewShiftTeam = $teamId;
        $this->teamShiftIn = $this->hhmm24($t->shift_in);
        $this->teamShiftOut = $this->hhmm24($t->shift_out);
        $this->teamSatIn = $this->hhmm24($t->sat_in);
        $this->teamSatOut = $this->hhmm24($t->sat_out);
    }

    public function closeCrewShift(): void
    {
        $this->crewShiftTeam = null;
        $this->reset(['teamShiftIn', 'teamShiftOut', 'teamSatIn', 'teamSatOut']);
    }

    /**
     * Validate the four shift time fields into a save payload. Each pair must be
     * either fully blank (clears the shift) or a same-day range (end after start).
     * Anything else — half-set, or a midnight-crossing "22:00→06:00" night shift —
     * returns null with an explanatory toast instead of silently saving nothing.
     */
    protected function validShiftPayload(): ?array
    {
        $pairs = [
            'shift' => [$this->minOf24($this->teamShiftIn), $this->minOf24($this->teamShiftOut), trim($this->teamShiftIn) !== '' || trim($this->teamShiftOut) !== ''],
            'sat' => [$this->minOf24($this->teamSatIn), $this->minOf24($this->teamSatOut), trim($this->teamSatIn) !== '' || trim($this->teamSatOut) !== ''],
        ];
        $out = [];
        foreach ($pairs as $key => [$in, $end, $touched]) {
            if ($in === null && $end === null) {
                $out[$key.'_in'] = $out[$key.'_out'] = null;
                if ($touched) {   // something was typed but didn't parse
                    $this->showToast($this->tl('Enter both start and end times', 'Ingresa hora de inicio y fin', '출근·퇴근 시각을 모두 입력하세요'));

                    return null;
                }

                continue;
            }
            if ($in === null || $end === null) {
                $this->showToast($this->tl('Enter both start and end times', 'Ingresa hora de inicio y fin', '출근·퇴근 시각을 모두 입력하세요'));

                return null;
            }
            if ($end <= $in) {
                $this->showToast($this->tl('End must be after start (overnight shifts are not supported yet)',
                    'El fin debe ser después del inicio (turnos nocturnos aún no soportados)',
                    '퇴근이 출근보다 늦어야 합니다 (자정을 넘는 야간 시프트는 아직 지원되지 않아요)'));

                return null;
            }
            $out[$key.'_in'] = $in;
            $out[$key.'_out'] = $end;
        }

        return $out;
    }

    /** Save the crew shift a field lead set from their phone (gated shifts.manage). */
    public function saveCrewShift(): void
    {
        $t = $this->crewShiftTeam ? Team::find($this->crewShiftTeam) : null;
        if (! $t || ! $this->can('shifts.manage', $t)) {
            return;
        }
        $shift = $this->validShiftPayload();
        if ($shift === null) {
            return;   // invalid pair — an error toast was shown, keep the editor open
        }
        $t->update($shift);
        $this->audit('shifts.manage', $t->name.' (#'.$t->id.')',
            'shift '.($t->shift_in !== null ? Shift::fmtMin($t->shift_in).'–'.Shift::fmtMin($t->shift_out) : '—'));
        $this->closeCrewShift();
        $this->showToast($this->dict()['pj_saved'].' ✓');
    }

    /** Quick nudge for the adjust editor: add minutes to the paid-in/out leg. */
    public function bumpAdjust(string $leg, int $mins): void
    {
        $prop = $leg === 'in' ? 'adjPaidIn' : 'adjPaidOut';
        $cur = $this->minOf24($this->$prop);
        if ($cur === null) {
            return;
        }
        $this->$prop = $this->hhmm24(max(0, min(1439, $cur + $mins)));
    }

    /** Remove a lead adjustment — the punch reverts to the automatic shift settle. */
    public function clearAdjust(): void
    {
        $id = $this->adjPunchId;
        $p = $id ? Punch::find($id) : null;
        if (! $p) {
            return;
        }
        $e = Employee::find($p->employee_id);
        if (! $this->can('attendance.adjust', $e)) {
            return;
        }
        $p->update(['adj_in_min' => null, 'adj_out_min' => null, 'adj_reason' => null, 'adj_by' => null]);
        $this->audit('attendance.adjust.clear',
            ($e ? $e->first.' '.$e->last.' (#'.$e->id.')' : '#'.$p->employee_id), $p->work_date);
        $this->closeAdjust();
        $this->showToast($this->tl('Adjustment removed', 'Ajuste eliminado', '조정 취소 완료'));
    }

    // =================== payroll ===================

    /**
     * Find a worker by their badge NFC tag or QR-code number and pop open their
     * attendance-history drawer. Accepts an employee id (N-… or HOF-…), a badge QR
     * value, or a raw NFC UID (converted to the N-… employee id).
     */
    public function findByBadge(): void
    {
        if (! $this->can('payroll.view')) {
            return;
        }
        $q = trim($this->badgeLookup);
        if ($q === '') {
            return;
        }
        $upper = mb_strtoupper($q);
        $emp = Employee::where(function ($w) use ($upper, $q) {
            $w->whereRaw('UPPER(emp_id) = ?', [$upper])
                ->orWhereRaw('UPPER(badge_qr) = ?', [$upper])
                ->orWhere('emp_id', $this->nfcId($q));   // raw NFC UID → N-######### id
        })->first();

        if (! $emp) {
            $this->showToast($this->tl('No worker for that badge / QR', 'Sin trabajador para ese código', '해당 베지·QR의 작업자를 찾을 수 없어요'));

            return;
        }
        $this->badgeLookup = '';
        $this->payDetail = $emp->id;   // opens the attendance-history drawer
    }

    public function openPayDetail(int $id): void
    {
        if (! $this->can('payroll.view')) {
            return;
        }
        $this->payDetail = $id;
    }

    public function closePayDetail(): void
    {
        $this->payDetail = null;
    }

    public function openVoucher(): void
    {
        if (! $this->can('payroll.view')) {
            return;
        }
        $this->payVoucher = true;
    }

    public function closeVoucher(): void
    {
        $this->payVoucher = false;
    }

    public function printVoucher(): void
    {
        if (! $this->can('payroll.process')) {
            return;
        }
        if (trim($this->checkNo) === '') {
            $this->showToast($this->dict()['pv_needCheck']);

            return;
        }
        $e = Employee::find($this->payDetail);
        if ($e) {
            [$start, $end] = Payroll::currentPeriod();
            // weekly-40h FLSA breakdown — the same math the screen and the Excel register use
            $b = Payroll::breakdownFor($e, $start, $end);
            Payment::updateOrCreate(
                ['employee_id' => $e->id, 'period_start' => $start],
                [
                    'period_end' => $end,
                    'check_no' => trim($this->checkNo),
                    'pay_date' => $this->payDate,
                    'amount' => round(Payroll::grossPay($b, $e->rate), 2),
                    'reg_hours' => (int) round($b['reg']),
                    'ot_hours' => (int) round($b['ot']),
                ]
            );
            $this->audit('payroll.pay', $e->first.' '.$e->last.' (#'.$e->id.')',
                $start.'–'.$end.' · check '.trim($this->checkNo).' · $'.number_format(Payroll::grossPay($b, $e->rate), 2));
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
        $d = $this->dict();
        // 1) only an authenticated, linked person may clock — and only themselves
        $eid = $this->clockableEmployeeId();
        if ($eid === null) {
            return;
        }
        $me = Employee::find($eid);
        // 2) a terminated / missing record cannot punch
        if (! $me || ! $me->isActive()) {
            $this->showToast($d['w_notActive']);

            return;
        }
        // 3) throttle: a handful of attempts per minute per employee
        $rlKey = 'clock:'.$eid;
        if (RateLimiter::tooManyAttempts($rlKey, 6)) {
            $this->showToast($d['w_tooMany']);

            return;
        }
        RateLimiter::hit($rlKey, 60);

        $p = $this->todayPunch($eid);
        // day already complete (in + out) — locked. Only an admin may correct it via
        // manualPunch; a repeat tap must not reopen the record or overwrite the times.
        if ($this->dayLocked($p)) {
            $this->clock = 'done';
            $this->showToast($d['w_workDone']);

            return;
        }
        $nowMin = (int) now()->format('H') * 60 + (int) now()->format('i');
        $site = $me->site_id ? Site::find($me->site_id) : null;
        // 4) sanitize coordinates; verify() withholds a verdict when the fix is too
        //    coarse or its accuracy circle straddles the fence (no false off-site)
        $coords = Geo::coords($lat, $lng, $acc);
        [, $geoOk] = Geo::verify($site, $coords);
        if ($p->in_min === null) {
            // first clock-in of the day
            $this->clock = 'in';
            $this->clockInTime = Shift::fmtMin($nowMin);
            $p->in_min = $nowMin;
            $p->out_min = null;
            $p->no_lunch = $this->noLunchToday;
            $p->source = 'worker';
            // stamp today's crew/company/site — later team moves must not rewrite this day
            $p->team_id = $me->team_id;
            $p->company_id = $me->company_id;
            $p->site_id = $me->site_id;
            $p->stampShiftSnap();   // freeze today's shift — later shift edits can't change earned pay
            $p->in_lat = $coords['lat'] ?? null;
            $p->in_lng = $coords['lng'] ?? null;
            $p->in_acc = $coords['acc'] ?? null;
            $p->in_geo_ok = $geoOk;
            $p->save();
            $me?->update(['status' => 'present', 'in_t' => Shift::fmtMin($nowMin)]);
            $this->showToast($this->clockToast('w_done_in', $geoOk));
        } else {
            // guard: no clock-out within minutes of clocking in — this soaks up
            // duplicate taps / double-fired GPS callbacks (which would otherwise
            // clock the worker straight back out) and accidental immediate outs
            if ($nowMin - $p->in_min < Shift::MIN_OUT_GAP_MIN) {
                $this->clock = 'in';
                $this->clockInTime = Shift::fmtMin($p->in_min);
                $this->showToast($d['w_tooSoonOut']);

                return;
            }
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
            $this->showToast($this->clockToast('w_done_out', $geoOk));
        }
    }

    /**
     * Worker-home QR scanner result: the scanned crew becomes today's crew.
     * Accepts the raw QR text (a /scan/{team} URL) or a bare team id. If the
     * worker hasn't clocked in yet, the same tap clocks them in too.
     */
    public function assignTeamByQr(string $qrValue, float|string|null $lat = null, float|string|null $lng = null, float|string|null $acc = null): void
    {
        $d = $this->dict();
        $eid = $this->clockableEmployeeId();
        if ($eid === null) {
            return;
        }
        $me = Employee::find($eid);
        if (! $me || ! $me->isActive()) {
            return;
        }
        // extract the team id from a /scan/{team} URL, else treat the value as an id
        $teamId = preg_match('#/scan/([^/?\s]+)#', $qrValue, $m) ? $m[1] : trim($qrValue);
        $team = Team::find($teamId);
        $company = $team ? Company::find($team->company_id) : null;
        if (! $team) {
            $this->showToast($d['w_scanBadQr']);

            return;
        }
        if ($me->team_id !== $team->id) {
            $from = $me->team_id;
            $me->update([
                'team_id' => $team->id,
                'company_id' => $company?->id ?? $me->company_id,
                'site_id' => $company?->site_id ?? $me->site_id,
            ]);
            $this->audit('team.move', trim($me->first.' '.$me->last).' (#'.$me->id.')', ($from ?? '—').' → '.$team->id.' · via QR');
            // restamp today's open (not yet clocked-out) punch so the day reads as the new crew
            $p = $this->todayPunch($eid);
            if ($p->exists && $p->out_min === null) {
                $p->team_id = $team->id;
                $p->company_id = $company?->id;
                $p->site_id = $company?->site_id;
                $p->stampShiftSnap();   // the new crew's shift governs today
                $p->save();
            }
        }
        $p = $this->todayPunch($eid);
        if ($p->in_min === null) {
            // not clocked in yet — one scan does both: assign + clock in
            $this->doClock($lat, $lng, $acc);
            // keep doClock's off-site warning, which its toast would otherwise lose
            $done = $this->clockToast('w_done_in', $this->todayPunch($eid)->in_geo_ok);
            $this->showToast($team->name.' · '.$d['w_teamMoved'].' · '.$done);
        } else {
            $this->showToast($team->name.' · '.$d['w_teamMoved']);
        }
    }

    public function toggleNoLunch(): void
    {
        $eid = $this->clockableEmployeeId();
        if ($eid === null) {
            return;
        }
        $this->noLunchToday = ! $this->noLunchToday;
        $p = $this->todayPunch($eid);
        if ($p->exists) {
            $p->update(['no_lunch' => $this->noLunchToday]);
        }
        $d = $this->dict();
        $this->showToast($this->noLunchToday ? $d['lunchSkipToast'] : $d['lunchKeptToast']);
    }

    public function togglePunchLunch(int $punchId): void
    {
        $p = Punch::find($punchId);
        if (! $p) {
            return;
        }
        $d = $this->dict();
        $isSelf = $p->employee_id === $this->clockableEmployeeId();
        $isManager = $this->can('punch.manual', Employee::find($p->employee_id));
        if (! $isSelf && ! $isManager) {
            return;
        }
        // the lunch hour is pay — a worker may only flip TODAY's own record.
        // Older days go through the correction/lead-adjust flow like any other pay change.
        if (! $isManager && $p->work_date !== now()->format('Y-m-d')) {
            $this->showToast($this->tl('Past days need a correction request', 'Días pasados requieren corrección', '지난 날짜는 정정 요청으로 처리하세요'));

            return;
        }
        $p->update(['no_lunch' => ! $p->no_lunch]);
        if ($isManager && ! $isSelf) {
            $e = Employee::find($p->employee_id);
            $this->audit('punch.lunch', $e ? $e->first.' '.$e->last.' (#'.$e->id.')' : '#'.$p->employee_id,
                $p->work_date.' · no_lunch='.($p->no_lunch ? '1' : '0'));
        }
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
        // a clock-out write — only the authenticated person's own punch, never a preview
        $eid = $this->clockableEmployeeId();
        if ($eid === null) {
            return;
        }
        $p = $this->todayPunch($eid);
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
        Employee::where('id', $eid)
            ->update(['status' => 'off', 'out_t' => Shift::fmtMin($nowMin)]);
        $this->clock = 'done';   // in + out recorded → day locked
        $this->earlyOpen = false;
        $this->showToast($d['w_earlyDone'].' · '.$reason);
    }

    public function printQr(): void
    {
        $this->dispatch('print-now');
    }

    // =============== worker self-report: 결근 · 휴가 · 퇴사 ===============

    /** Open one of the self-report sheets (absent | leave | resign), prefilled. */
    public function openStatusSheet(string $kind): void
    {
        if (! in_array($kind, ['absent', 'leave', 'resign'], true)) {
            return;
        }
        $this->reset(['absentReason', 'leaveStart', 'leaveEnd', 'leaveReason', 'resignOn', 'resignReason']);
        if ($kind === 'leave') {
            $this->leaveStart = $this->leaveEnd = now()->format('Y-m-d');
        }
        $this->statusSheet = $kind;
    }

    public function closeStatusSheet(): void
    {
        $this->statusSheet = '';
    }

    /**
     * The employee filing a self-report. A field worker/lead is their punch
     * identity; a desktop admin/staff is their self record (created on first use),
     * so admins can report their own 휴가/퇴사/결근 too.
     */
    protected function selfReportEmployeeId(): ?int
    {
        return $this->clockableEmployeeId() ?? $this->ensureSelfEmployee();
    }

    /** Report an excused absence for TODAY (call-in) — takes effect immediately. */
    public function reportAbsent(): void
    {
        $eid = $this->selfReportEmployeeId();
        if ($eid === null) {
            return;
        }
        $today = now()->format('Y-m-d');
        // can't be absent on a day you already clocked into
        if (Punch::where('employee_id', $eid)->where('work_date', $today)->whereNotNull('in_min')->exists()) {
            $this->showToast($this->tl('You already clocked in today', 'Ya fichaste hoy', '오늘 이미 출근 기록이 있어요'));

            return;
        }
        Absence::updateOrCreate(
            ['employee_id' => $eid, 'work_date' => $today],
            ['kind' => 'excused', 'reason' => trim($this->absentReason) ?: null, 'source' => 'worker', 'marked_by' => $eid],
        );
        Employee::where('id', $eid)->update(['status' => 'absent']);
        $this->audit('attendance.absent', '#'.$eid, $today.' · '.$this->tl('self-reported', 'auto-reportado', '본인 보고').($this->absentReason ? ' · '.trim($this->absentReason) : ''));
        $this->statusSheet = '';
        $this->showToast($this->tl('Absence reported', 'Ausencia registrada', '결근 보고 완료'));
    }

    /** File a leave request (휴가 신청) — pending until a lead/manager approves. */
    public function saveLeave(): void
    {
        $eid = $this->selfReportEmployeeId();
        if ($eid === null) {
            return;
        }
        $s = trim($this->leaveStart);
        $e = trim($this->leaveEnd);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $e) || $e < $s) {
            $this->showToast($this->tl('Check the leave dates', 'Revisa las fechas', '휴가 기간을 확인하세요'));

            return;
        }
        Leave::create([
            'employee_id' => $eid, 'start_date' => $s, 'end_date' => $e,
            'reason' => trim($this->leaveReason) ?: null, 'status' => 'pending',
        ]);
        $this->audit('leave.request', '#'.$eid, $s.' – '.$e.($this->leaveReason ? ' · '.trim($this->leaveReason) : ''));
        $this->statusSheet = '';
        $this->showToast($this->tl('Leave requested · awaiting approval', 'Permiso solicitado · pendiente', '휴가 신청 완료 · 승인 대기'));
    }

    /** File a resignation notice (퇴사 신청) — pending until admin approves. */
    public function saveResign(): void
    {
        $eid = $this->selfReportEmployeeId();
        if ($eid === null) {
            return;
        }
        $on = trim($this->resignOn);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $on)) {
            $this->showToast($this->tl('Pick your last working day', 'Elige tu último día', '마지막 근무일을 선택하세요'));

            return;
        }
        Employee::where('id', $eid)->update(['resign_on' => $on, 'resign_reason' => trim($this->resignReason) ?: null]);
        $this->audit('resign.request', '#'.$eid, $on.($this->resignReason ? ' · '.trim($this->resignReason) : ''));
        $this->statusSheet = '';
        $this->showToast($this->tl('Resignation filed · awaiting approval', 'Renuncia enviada · pendiente', '퇴사 신청 완료 · 승인 대기'));
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
        $eid = $this->clockableEmployeeId();
        if ($eid === null) {
            return;
        }
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
        $eid = $this->clockableEmployeeId();
        if ($eid === null) {
            return;
        }
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
    /** Legacy shim: "admin" now means a global corrections decider (owner/hr_admin). */
    protected function actorIsAdmin(): bool
    {
        return $this->correctionsGlobal();
    }

    /** Approver: approve a request → apply it to the punch immediately. */
    // =============== leave · resignation · absence decisions ===============

    /** Lead/manager marks a crew member's day: 결근(사유) or 무단결근. */
    public function markAbsent(int $employeeId, string $kind, string $reason = ''): void
    {
        $e = Employee::find($employeeId);
        if (! $e || ! $this->can('attendance.adjust', $e)) {
            return;
        }
        $kind = $kind === 'excused' ? 'excused' : 'unexcused';
        $today = now()->format('Y-m-d');
        Absence::updateOrCreate(
            ['employee_id' => $employeeId, 'work_date' => $today],
            ['kind' => $kind, 'reason' => trim($reason) ?: null, 'source' => 'lead', 'marked_by' => $this->actorEmployee()?->id],
        );
        Employee::where('id', $employeeId)->update(['status' => 'absent']);
        $this->audit('attendance.absent', $e->first.' '.$e->last.' (#'.$e->id.')',
            $today.' · '.($kind === 'unexcused' ? '무단결근' : '결근').(trim($reason) ? ' · '.trim($reason) : ''));
        $this->showToast($this->tl('Marked absent', 'Marcado ausente', '결근 처리 완료'));
    }

    /** May the actor decide this leave? (owner/hr global · site_manager scope · crew_lead own crew) */
    protected function canDecideLeave(Leave $l): bool
    {
        return $this->can('corrections.decide', Employee::find($l->employee_id));
    }

    public function approveLeave(int $id): void
    {
        $l = Leave::find($id);
        if (! $l || ! $l->isPending() || ! $this->canDecideLeave($l)) {
            return;
        }
        $l->update(['status' => 'approved', 'decided_by' => $this->actorEmployee()?->id, 'decided_at' => now()]);
        $e = Employee::find($l->employee_id);
        $this->audit('leave.approve', ($e ? $e->first.' '.$e->last.' (#'.$e->id.')' : '#'.$l->employee_id), $l->start_date.' – '.$l->end_date);
        $this->showToast($this->tl('Leave approved', 'Permiso aprobado', '휴가 승인 완료'));
    }

    public function rejectLeave(int $id): void
    {
        $l = Leave::find($id);
        if (! $l || ! $l->isPending() || ! $this->canDecideLeave($l)) {
            return;
        }
        $l->update(['status' => 'rejected', 'decided_by' => $this->actorEmployee()?->id, 'decided_at' => now()]);
        $this->audit('leave.reject', '#'.$l->employee_id, $l->start_date.' – '.$l->end_date);
        $this->showToast($this->tl('Leave rejected', 'Permiso rechazado', '휴가 반려'));
    }

    /** Admin approves a resignation notice → runs the terminate flow on the requested day. */
    public function approveResign(int $employeeId): void
    {
        $e = Employee::find($employeeId);
        if (! $e || ! $e->hasPendingResignation() || ! $this->can('employees.terminate', $e)) {
            return;
        }
        $e->update(['emp' => 'terminated', 'term' => $e->resign_on, 'status' => 'off', 'access' => 'worker', 'resign_on' => null]);
        $this->audit('employee.terminate', $e->first.' '.$e->last.' (#'.$e->id.')', 'resignation · '.$e->term);
        $this->showToast($this->tl('Resignation approved', 'Renuncia aprobada', '퇴사 승인 완료'));
    }

    public function rejectResign(int $employeeId): void
    {
        $e = Employee::find($employeeId);
        if (! $e || ! $e->hasPendingResignation() || ! $this->can('employees.terminate', $e)) {
            return;
        }
        $e->update(['resign_on' => null, 'resign_reason' => null]);
        $this->audit('resign.reject', $e->first.' '.$e->last.' (#'.$e->id.')');
        $this->showToast($this->tl('Resignation declined', 'Renuncia rechazada', '퇴사 신청 반려'));
    }

    public function approveCorrection(int $id): void
    {
        $c = AttendanceCorrection::find($id);
        $me = $this->actorEmployee();
        if (! $c || ! $me) {
            return;
        }
        if (! Corrections::canDecide($c, $me->id, $this->correctionsGlobal(), $this->scopeSiteIds())) {
            $this->showToast($this->tl('Not allowed to decide this', 'No puedes decidir esto', '승인 권한이 없습니다'));

            return;
        }
        Corrections::approve($c, $me->id);
        $this->showToast($this->tl('Approved & applied', 'Aprobado y aplicado', '정정 승인·반영 완료'));
    }

    /** Approver: open the adjust box, prefilled with the worker's requested times. */
    public function askAdjustCorrection(int $id): void
    {
        $c = AttendanceCorrection::find($id);
        if (! $c || $c->type === 'delete') {
            return;
        }
        $this->adjustingId = $id;
        $this->rejectingId = null;
        $this->adjustIn = $this->hhmm($c->req_in_min);
        $this->adjustOut = $this->hhmm($c->req_out_min);
    }

    public function cancelAdjust(): void
    {
        $this->adjustingId = null;
        $this->adjustIn = '';
        $this->adjustOut = '';
    }

    /** Approver: approve with edited times — the adjusted values are applied to the punch. */
    public function approveAdjusted(int $id): void
    {
        $c = AttendanceCorrection::find($id);
        $me = $this->actorEmployee();
        if (! $c || ! $me) {
            return;
        }
        if (! Corrections::canDecide($c, $me->id, $this->correctionsGlobal(), $this->scopeSiteIds())) {
            $this->showToast($this->tl('Not allowed to decide this', 'No puedes decidir esto', '승인 권한이 없습니다'));

            return;
        }
        $in = $this->minsFromHHMM($this->adjustIn);
        $out = $this->adjustOut === '' ? null : $this->minsFromHHMM($this->adjustOut);
        if ($in === null) {
            $this->showToast($this->tl('Enter a clock-in time', 'Ingresa la hora de entrada', '출근 시각을 입력하세요'));

            return;
        }
        if ($out !== null && $out < $in) {
            $this->showToast($this->tl('Clock-out is before clock-in', 'La salida es antes de la entrada', '퇴근이 출근보다 빠릅니다'));

            return;
        }
        Corrections::approve($c, $me->id, $in, $out);
        $this->adjustingId = null;
        $this->adjustIn = $this->adjustOut = '';
        $this->showToast($this->tl('Adjusted & applied', 'Ajustado y aplicado', '조정 승인·반영 완료'));
    }

    public function askRejectCorrection(int $id): void
    {
        $this->rejectingId = $id;
        $this->adjustingId = null;
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
        if (! Corrections::canDecide($c, $me->id, $this->correctionsGlobal(), $this->scopeSiteIds())) {
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
            'acctTab' => $this->acctTab,
            'expFormOpen' => $this->expFormOpen, 'expSelId' => $this->expSelId,
            'expRejectId' => $this->expRejectId, 'expFilter' => $this->expFilter,
            'expCategory' => $this->expCategory, 'expSite' => $this->expSite,
            'mobileTab' => $this->mobileTab, 'clock' => $this->clock, 'clockInTime' => $this->clockInTime,
            'earlyOpen' => $this->earlyOpen, 'earlyReasonVal' => $this->earlyReasonVal, 'earlyCustom' => $this->earlyCustom,
            'noLunchToday' => $this->noLunchToday, 'lunchOv' => $this->lunchOv,
            'correctionOpen' => $this->correctionOpen, 'correctionDate' => $this->correctionDate,
            'correctionType' => $this->correctionType, 'correctionIn' => $this->correctionIn,
            'correctionOut' => $this->correctionOut, 'correctionReason' => $this->correctionReason,
            'rejectingId' => $this->rejectingId,
            'corrGlobal' => $this->correctionsGlobal(), 'corrSites' => $this->scopeSiteIds(),
            'scopeSites' => $this->scopeSiteIds(),
            'can' => [
                'payrollView' => $this->can('payroll.view'),
                'expensesSubmit' => $this->can('expenses.submit'),
                'expensesDecide' => $this->can('expenses.decide'),
                'sitesCreate' => $this->can('sites.create'),
                'sitesDelete' => $this->can('sites.delete'),
                'companiesCreate' => $this->can('companies.create'),
                'companiesDelete' => $this->can('companies.delete'),
                'employeesDelete' => $this->can('employees.delete'),
                'employeesTerminate' => Access::allows($this->actorRoles(), 'employees.terminate'),
                'employeesRegister' => Access::allows($this->actorRoles(), 'employees.register'),
                'punchManual' => Access::allows($this->actorRoles(), 'punch.manual'),
                'attendanceAdjust' => Access::allows($this->actorRoles(), 'attendance.adjust'),
                'shiftsManage' => Access::allows($this->actorRoles(), 'shifts.manage'),
                'rolesAssign' => $this->can('roles.assign'),
                'usersPassword' => Access::allows($this->actorRoles(), 'users.password'),
                'auditView' => $this->correctionsGlobal(),
                'assignableRoles' => Access::assignable($this->primaryRole()),
            ],
            'adjustingId' => $this->adjustingId,
            'commsChannel' => $this->commsChannel, 'commsCompose' => $this->commsCompose,
            'commsNewDm' => $this->commsNewDm, 'commsDmSearch' => $this->commsDmSearch,
            'commsPicked' => $this->commsPicked, 'commsRoomName' => $this->commsRoomName,
            'commsInviteOpen' => $this->commsInviteOpen,
            'commsPane' => $this->commsPane,
            'reportOpen' => $this->reportOpen, 'reportDraft' => $this->reportDraft,
            'bellOpen' => $this->bellOpen,
            'actorId' => $this->actorId(),
            'canManage' => $this->can('comms.announce'),
            'toast' => $this->toast,
            'nfcUid' => $this->currentUid() ?? self::NFC_UID,
            'nfcId' => $this->nfcId($this->currentUid() ?? self::NFC_UID),
            'hasUid' => $this->currentUid() !== null,
            'meEmployeeId' => $this->meEmployeeId(),
            'selfEmployeeId' => $this->selfEmployeeId(),
            'previewEmpId' => $this->previewEmpId,
            'canDeskClock' => $this->canDeskClock(),
        ];
    }

    public function tlPublic(string $en, string $es, string $ko): string
    {
        return $this->tl($en, $es, $ko);
    }
}
