<?php

namespace App\Support;

use App\Models\Absence;
use App\Models\Assignment;
use App\Models\AttendanceCorrection;
use App\Models\AuditLog;
use App\Models\Channel;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Punch;
use App\Models\Site;
use App\Models\Team;
use App\Support\WorkerStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Builds the derived view data for WorkforceApp — the PHP port of the prototype's renderVals().
 * Pure read: takes the component state array, returns a flat array consumed by the Blade views.
 */
class ViewModel
{
    public static function build(array $s): array
    {
        $lang = $s['lang'];
        $L = (array) trans('app', [], $lang);
        $tl = fn (string $en, string $es, string $ko) => $lang === 'ko' ? $ko : ($lang === 'es' ? $es : $en);

        $stColor = ['present' => '#1F9D6B', 'late' => '#E8A33D', 'absent' => '#D9483B', 'off' => '#9AA0A6'];
        $stBg = ['present' => '#E7F4EE', 'late' => '#FBF1DF', 'absent' => '#FBE9E7', 'off' => '#EFEFEC'];
        $accColor = ['admin' => '#E85D2A', 'owner' => '#E85D2A', 'hr_admin' => '#B9761F', 'manager' => '#3B72E0', 'site_manager' => '#3B72E0', 'company_admin' => '#0EA5A0', 'worker' => '#6B6E76'];

        $isDemo = (bool) config('workforce.demo');
        $authUser = Auth::user();
        [$periodStart, $periodEnd, $periodLabel] = Payroll::currentPeriod();
        $payments = Payment::where('period_start', $periodStart)->get()->keyBy('employee_id');

        // ---- load domain ----
        $sites = Site::orderBy('id')->get();
        $companies = Company::orderBy('id')->get();
        $teams = Team::orderBy('id')->get();
        $employees = Employee::orderBy('id')->get();

        $teamById = $teams->keyBy('id');
        Attendance::warmTeams($teams);   // settle() fallback reads teams without re-querying
        $companyById = $companies->keyBy('id');
        $siteById = $sites->keyBy('id');

        $teamName = fn ($tid) => optional($teamById->get($tid))->name ?? $L['e_unassigned'];
        $companyName = fn ($cid) => optional($companyById->get($cid))->name ?? '—';
        $teamColor = fn ($tid) => optional($teamById->get($tid))->color ?? '#9AA0A6';
        $empName = fn (Employee $e) => $e->displayName($lang);
        $inits = fn (Employee $e) => $e->initials();

        $siteScope = fn ($coll) => $s['site'] === 'all' ? $coll : $coll->filter(fn ($e) => $e->site_id === $s['site'])->values();
        $activeAll = $employees->filter(fn ($e) => $e->emp === 'active')->values();
        $scopedActive = $siteScope($activeAll);

        // period pay math — weekly-40h FLSA breakdowns for the WHOLE roster in one
        // punch query (terminated people included: days already worked still pay)
        $breakdowns = Payroll::periodBreakdowns($employees->pluck('id')->all(), $periodStart, $periodEnd);
        $bFor = fn (Employee $e) => $breakdowns[$e->id] ?? Payroll::fallbackBreakdown($e);
        $grossFor = fn (Employee $e) => Payroll::grossPay($bFor($e), $e->rate);
        // terminated mid-period with worked days → still owed; surfaced on payroll rows
        $terminatedOwed = $employees->filter(
            fn ($e) => $e->emp === 'terminated' && isset($breakdowns[$e->id]) && $breakdowns[$e->id]['total'] > 0
        )->values();

        // ---- nav (managers have no payroll) ----
        $caps = (array) ($s['can'] ?? []);
        $navKeys = ['dashboard', 'comms', 'projects', 'employees', 'badge', 'attendance'];
        if ($caps['payrollView'] ?? ($s['role'] !== 'manager')) {
            $navKeys[] = 'payroll';   // payroll is a permission, not a hidden menu
        }
        // unread badge for the Internal Comms nav item (computed once, reused below)
        $navActor = Employee::find($s['actorId'] ?? null);
        $commsUnread = $navActor ? Comms::totalUnread($navActor) : 0;
        $nav = array_map(fn ($k) => [
            'key' => $k, 'label' => $L['nav_'.$k], 'active' => $s['screen'] === $k,
            'unread' => $k === 'comms' ? $commsUnread : 0,
        ], $navKeys);

        // field lead on the mobile app: crews they lead → extra "우리 팀" tab
        $mobileLeadTeamIds = [];
        if ($s['role'] === 'worker') {
            $meId = $s['meEmployeeId'] ?? null;
            $mobileLeadTeamIds = $meId ? $teams->where('lead', $meId)->pluck('id')->all() : [];
        }
        $isFieldLead = $mobileLeadTeamIds !== [];

        $mKeys = $isFieldLead ? ['home', 'work', 'crew', 'pay', 'me'] : ['home', 'work', 'pay', 'me'];
        $mobileTabs = array_map(fn ($k) => [
            'key' => $k, 'label' => $L['w_tab_'.$k], 'active' => $s['mobileTab'] === $k,
        ], $mKeys);

        $scopeSites = $s['scopeSites'] ?? null;   // null = unrestricted
        $visibleSites = $scopeSites === null ? $sites : $sites->whereIn('id', $scopeSites)->values();
        $siteOptions = array_merge(
            $scopeSites === null ? [['id' => 'all', 'label' => $L['allSites']]] : [],
            $visibleSites->map(fn ($st) => ['id' => $st->id, 'label' => $st->name.' · '.$st->city])->all()
        );

        // ---- dashboard stats ----
        $cnt = ['present' => 0, 'late' => 0, 'absent' => 0, 'off' => 0];
        foreach ($scopedActive as $e) {
            $cnt[$e->status]++;
        }
        $totalActive = $scopedActive->count();
        $onsite = $cnt['present'] + $cnt['late'];
        $rate = $totalActive ? (int) round($onsite / $totalActive * 100) : 0;
        $periodPayNum = $scopedActive->sum($grossFor);
        $avgH = $scopedActive->count()
            ? (int) round($scopedActive->sum(fn ($e) => $e->wh) / $scopedActive->count() / 2)
            : 0;

        $scopedTeams = $s['site'] === 'all'
            ? $teams
            : $teams->filter(function ($t) use ($companyById, $s) {
                $c = $companyById->get($t->company_id);

                return $c && $c->site_id === $s['site'];
            })->values();

        // ---- attendance board: worker-level status per crew (dashboard) ----
        $today = now()->format('Y-m-d');
        $nowC = now();
        $todayPunches = Punch::where('work_date', $today)->get()->keyBy('employee_id');
        $todayLeaves = Leave::where('status', 'approved')
            ->where('start_date', '<=', $today)->where('end_date', '>=', $today)
            ->get()->keyBy('employee_id');
        $todayAbsences = Absence::where('work_date', $today)->get()->keyBy('employee_id');

        // roster for the board: active workers + still-assigned recent leavers
        $boardPeople = $siteScope(
            $employees->filter(fn ($e) => $e->team_id !== null && in_array($e->emp, ['active', 'terminated'], true))->values()
        );

        $teamStats = $scopedTeams->map(function ($t) use ($boardPeople, $companyName, $teamById, $todayPunches, $todayLeaves, $todayAbsences, $today, $nowC, $empName, $tl) {
            $team = $teamById->get($t->id);
            $list = $boardPeople->filter(fn ($e) => $e->team_id === $t->id)->values();
            $tally = [];
            $workers = $list->map(function ($e) use ($team, $todayPunches, $todayLeaves, $todayAbsences, $today, $nowC, $empName, $tl, &$tally) {
                $st = WorkerStatus::resolve($e, $team, $todayPunches->get($e->id), $todayLeaves->get($e->id), $todayAbsences->get($e->id), $today, $nowC, $tl);
                $tally[$st['key']] = ($tally[$st['key']] ?? 0) + 1;

                return [
                    'id' => $e->id, 'name' => $empName($e), 'initials' => $e->initials(),
                    'status' => $st, 'pendingResign' => $e->hasPendingResignation(),
                ];
            })->sortBy(fn ($w) => $w['status']['order'].$w['name'])->values()->all();

            $working = ($tally['working'] ?? 0);
            $active = $list->filter(fn ($e) => $e->emp === 'active')->count();

            return [
                'id' => $t->id, 'name' => $t->name, 'companyId' => $t->company_id,
                'company' => $companyName($t->company_id), 'color' => $t->color,
                'total' => $active, 'present' => $working,
                'pct' => $active ? (int) round($working / $active * 100) : 0,
                'workers' => $workers, 'tally' => $tally,
            ];
        })->values();

        // grouped by company → crews, with a company-level roll-up
        $companyStats = $teamStats->groupBy('companyId')->map(function ($teams) use ($companyName) {
            $present = $teams->sum('present');
            $total = $teams->sum('total');

            return [
                'company' => $companyName($teams->first()['companyId']),
                'present' => $present, 'total' => $total,
                'pct' => $total ? (int) round($present / $total * 100) : 0,
                'teams' => $teams->sortBy('name')->values()->all(),
            ];
        })->sortBy('company')->values()->all();

        $teamStats = $teamStats->all();

        // whole-board roll-up merged into the attendance card header (replaces the
        // separate KPI cards): on-site count + one aggregate status tally
        $boardTally = [];
        $boardOnsite = 0;
        $boardTotal = 0;
        foreach ($teamStats as $ts) {
            $boardOnsite += $ts['present'];
            $boardTotal += $ts['total'];
            foreach ($ts['tally'] as $k => $n) {
                $boardTally[$k] = ($boardTally[$k] ?? 0) + $n;
            }
        }
        $dashSummary = [
            'onsite' => $boardOnsite, 'total' => $boardTotal,
            'pct' => $boardTotal ? (int) round($boardOnsite / $boardTotal * 100) : 0,
            'tally' => $boardTally,
        ];

        // repeat no-show escalation: workers with 3+ unexcused absences in the window
        $unexcWindow = $nowC->copy()->subDays(WorkerStatus::UNEXCUSED_WINDOW_DAYS)->format('Y-m-d');
        // NB: HAVING must repeat the aggregate, not the SELECT alias — Postgres
        // (production) rejects an alias in HAVING even though SQLite allows it.
        $unexcCounts = Absence::where('kind', 'unexcused')->where('work_date', '>=', $unexcWindow)
            ->selectRaw('employee_id, COUNT(*) as n')->groupBy('employee_id')
            ->havingRaw('COUNT(*) >= ?', [WorkerStatus::UNEXCUSED_ALERT])->pluck('n', 'employee_id');
        $repeatNoShow = collect($unexcCounts)->map(function ($n, $id) use ($employees, $empName, $siteScope) {
            $e = $employees->firstWhere('id', $id);

            return $e ? ['id' => $id, 'name' => $empName($e), 'siteId' => $e->site_id, 'count' => (int) $n] : null;
        })->filter()->values();
        if ($s['site'] !== 'all') {
            $repeatNoShow = $repeatNoShow->filter(fn ($r) => $r['siteId'] === $s['site'])->values();
        }
        $repeatNoShow = $repeatNoShow->all();

        // pending leave requests + resignation notices awaiting a decision
        $siteFilter = fn ($coll) => $s['site'] === 'all' ? $coll : $coll->filter(fn ($r) => $r['siteId'] === $s['site'])->values();
        $fmtMD = fn ($ymd) => preg_match('/^\d{4}-(\d{2})-(\d{2})$/', $ymd, $m) ? $m[1].'/'.$m[2] : $ymd;
        $pendLeaves = $siteFilter(Leave::where('status', 'pending')->orderBy('start_date')->get()
            ->map(function ($l) use ($employees, $empName, $fmtMD) {
                $e = $employees->firstWhere('id', $l->employee_id);

                return $e ? ['id' => $l->id, 'name' => $empName($e), 'teamId' => $e->team_id, 'siteId' => $e->site_id,
                    'range' => $fmtMD($l->start_date).' – '.$fmtMD($l->end_date), 'reason' => (string) ($l->reason ?? '')] : null;
            })->filter()->values());
        $pendResign = $siteFilter($employees->filter(fn ($e) => $e->emp === 'active' && ! empty($e->resign_on))
            ->map(fn ($e) => ['id' => $e->id, 'name' => $empName($e), 'teamId' => $e->team_id, 'siteId' => $e->site_id,
                'on' => $fmtMD($e->resign_on), 'reason' => (string) ($e->resign_reason ?? '')])->values());
        $pendLeaves = $pendLeaves->all();
        $pendResign = $pendResign->all();

        $recentPunches = Punch::orderByDesc('updated_at')->limit(4)->get();
        if ($recentPunches->isNotEmpty()) {
            $recent = $recentPunches->map(function ($pn) use ($employees, $empName, $tl) {
                $e = $employees->firstWhere('id', $pn->employee_id);
                $name = $e ? $empName($e) : '#'.$pn->employee_id;
                $isOut = $pn->out_min !== null;
                $t = $isOut ? $pn->out_min : $pn->in_min;

                return [
                    'txt' => $name.' · '.($isOut
                        ? $tl('clocked out', 'marcó salida', '퇴근 처리')
                        : $tl('clocked in', 'marcó entrada', '출근 처리')),
                    'tag' => $t !== null ? Shift::fmtMin($t) : '—',
                    'c' => $isOut ? '#8A8880' : '#1F9D6B',
                ];
            })->all();
        } else {
            $recent = [
                ['txt' => $tl('Carlos Martínez clocked in', 'Carlos Martínez marcó entrada', 'Carlos Martínez 출근 처리'), 'tag' => '6:52', 'c' => '#1F9D6B'],
                ['txt' => $tl('Miguel Torres late', 'Miguel Torres llegó tarde', 'Miguel Torres 지각'), 'tag' => '7:18', 'c' => '#E8A33D'],
                ['txt' => $tl('New badge: A. Vargas', 'Nueva credencial: A. Vargas', '신규 베지 등록: A. Vargas'), 'tag' => $lang === 'ko' ? '등록' : 'new', 'c' => '#3B72E0'],
                ['txt' => $tl('Antonio Díaz terminated', 'Antonio Díaz dado de baja', 'Antonio Díaz 퇴사 처리'), 'tag' => $lang === 'ko' ? '퇴사' : 'exit', 'c' => '#D9483B'],
            ];
        }

        // ---- dashboard: GPS off-site · pending corrections · per-site cards ----
        $today = now()->format('Y-m-d');
        $fmtDist = fn (?float $m) => $m === null ? null : ($m >= 1000 ? number_format($m / 1000, 1).'km' : ((int) round($m)).'m');

        // today's off-site clock-ins (a punch leg recorded outside the site geofence)
        $offList = [];
        foreach (Punch::where('work_date', $today)->get() as $pn) {
            $e = $activeAll->firstWhere('id', $pn->employee_id);
            if (! $e || ($s['site'] !== 'all' && $e->site_id !== $s['site'])) {
                continue;
            }
            $legs = [];
            if ($pn->in_geo_ok === false) {
                $legs[] = [$pn->in_lat, $pn->in_lng];
            }
            if ($pn->out_geo_ok === false) {
                $legs[] = [$pn->out_lat, $pn->out_lng];
            }
            if (! $legs) {
                continue;
            }
            $site = $siteById->get($e->site_id);
            $dist = null;
            foreach ($legs as [$la, $ln]) {
                if ($site && $site->lat !== null && $la !== null && $ln !== null) {
                    $dd = Geo::distanceMeters((float) $site->lat, (float) $site->lng, (float) $la, (float) $ln);
                    $dist = $dist === null ? $dd : max($dist, $dd);
                }
            }
            $offList[] = [
                'name' => $empName($e), 'team' => $teamName($e->team_id),
                'siteId' => $e->site_id, 'site' => $site->name ?? '—',
                'dist' => $fmtDist($dist),
                'time' => $pn->in_min !== null ? Shift::fmtMin($pn->in_min) : '—',
            ];
        }
        $offCount = count($offList);

        // pending correction requests awaiting a decision
        $pendCorr = AttendanceCorrection::where('status', 'pending')->orderBy('created_at')->get()
            ->map(function ($c) use ($employees, $empName, $teamName) {
                $e = $employees->firstWhere('id', $c->employee_id);

                return [
                    'name' => $e ? $empName($e) : '#'.$c->employee_id,
                    'siteId' => $e->site_id ?? null,
                    'team' => $e ? $teamName($e->team_id) : '—',
                    'date' => Carbon::parse($c->work_date)->format('M j'),
                    'isDelete' => $c->type === 'delete',
                    'reqIn' => $c->req_in_min !== null ? Shift::fmtMin($c->req_in_min) : '—',
                    'reqOut' => $c->req_out_min !== null ? Shift::fmtMin($c->req_out_min) : '—',
                ];
            });
        if ($s['site'] !== 'all') {
            $pendCorr = $pendCorr->filter(fn ($c) => $c['siteId'] === $s['site'])->values();
        }
        $pendCount = $pendCorr->count();

        // per-site summary cards — hidden on the dashboard by request; the
        // dashboard leads with the top KPI row + attendance-by-crew instead
        $dashSiteCards = [];

        // ---- employees ----
        $q = strtolower(trim($s['search']));
        $pool = $siteScope($employees);
        if ($s['empFilter'] === 'active') {
            $pool = $pool->filter(fn ($e) => $e->emp === 'active');
        } elseif ($s['empFilter'] === 'terminated') {
            $pool = $pool->filter(fn ($e) => $e->emp === 'terminated');
        } elseif ($s['empFilter'] === 'invited') {
            $pool = $pool->filter(fn ($e) => $e->emp === 'active' && $e->activated_at === null);
        }
        $filtered = $pool->filter(function ($e) use ($s, $q) {
            $okTeam = $s['teamFilter'] === 'all' || $e->team_id === $s['teamFilter'];
            $hay = strtolower($e->first.' '.$e->last.' '.($e->ko ?? '').' '.$e->role.' '.$e->emp_id.' '.$e->nat);

            return $okTeam && ($q === '' || str_contains($hay, $q));
        })->values();

        // all company involvements, grouped per employee (primary + assignments)
        $assignmentsByEmp = Assignment::all()->groupBy('employee_id');

        $mapEmp = function (Employee $e) use ($empName, $inits, $teamName, $teamColor, $companyName, $L, $stColor, $stBg, $accColor, $assignmentsByEmp) {
            $companies = collect([$companyName($e->company_id)]);
            foreach (($assignmentsByEmp[$e->id] ?? collect()) as $a) {
                $companies->push($companyName($a->company_id));
            }
            $companyList = $companies->filter(fn ($c) => $c && $c !== '—')->unique()->values()->all();

            return [
                'id' => $e->id, 'name' => $empName($e), 'initials' => $inits($e), 'empId' => $e->emp_id,
                'teamName' => $teamName($e->team_id), 'teamColor' => $teamColor($e->team_id), 'companyName' => $companyName($e->company_id),
                'companies' => $companyList, 'phone' => $e->phone, 'email' => $e->email,
                'role' => $e->role, 'rate' => $e->rate,
                'statusLabel' => $L['st_'.$e->status], 'statusColor' => $stColor[$e->status], 'statusBg' => $stBg[$e->status],
                'typeLabel' => $e->type === 'manager' ? $L['e_manager'] : $L['e_worker'],
                'payTypeLabel' => $L['b_pt'.ucfirst($e->pay_type ?? 'hourly')] ?? '',
                'payTypeCalc' => in_array($e->pay_type, ['hourly', 'both'], true),
                'access' => $e->access, 'accessLabel' => $L['access_'.$e->access] ?? $e->access, 'accessColor' => $accColor[$e->access] ?? '#6B6E76',
                'badgeQr' => $e->badge_qr, 'badgePhoto' => $e->badge_photo,
                'isTerminated' => $e->emp === 'terminated', 'isActive' => $e->emp === 'active',
                'isInvited' => $e->emp === 'active' && $e->activated_at === null,
                'rowOpacity' => $e->emp === 'terminated' ? '0.55' : '1',
                'inT' => $e->in_t, 'term' => $e->term,
            ];
        };
        $empRows = $filtered->map($mapEmp)->all();

        $selRaw = $employees->firstWhere('id', $s['selectedEmp']);
        $sel = $selRaw ? $mapEmp($selRaw) : null;
        $delRaw = $employees->firstWhere('id', $s['deleteId']);
        $termRaw = $employees->firstWhere('id', $s['terminateId']);

        // team-lead candidates: any active employee may lead a crew (a lead is often
        // a senior field worker, not only office/manager staff). Managers/foremen are
        // listed first, then workers; each labeled with their job title to pick from.
        $leadCandidates = $employees->filter(fn ($e) => $e->emp === 'active')
            ->sortBy(fn ($e) => ($e->type === 'manager' ? '0' : '1').' '.$empName($e))
            ->values();
        $managerOptions = $leadCandidates->map(fn ($m) => [
            'id' => $m->id,
            'label' => $empName($m).($m->role ? ' · '.$m->role : ''),
        ])->all();
        $companyOptions = $companies->map(fn ($c) => ['id' => $c->id, 'label' => $c->name])->all();
        $teamOptionsAll = array_merge(
            [['id' => '', 'label' => $L['e_unassigned']]],
            $teams->map(fn ($t) => ['id' => $t->id, 'label' => $t->name.' · '.$companyName($t->company_id)])->all()
        );

        // company-involvement assignments for the open employee (NAHSHON staffing many clients)
        $empAssignments = [];
        if ($selRaw) {
            $empAssignments = Assignment::where('employee_id', $selRaw->id)->get()->map(fn ($a) => [
                'id' => $a->id,
                'company' => $companyName($a->company_id),
                'team' => $a->team_id ? $teamName($a->team_id) : null,
                'teamColor' => $a->team_id ? $teamColor($a->team_id) : '#9AA0A6',
                'relation' => $a->relation,
            ])->all();
        }
        // crews available for the currently-picked company in the add-assignment form
        $assignTeamOptions = ($s['newAssignCompany'] ?? '') !== ''
            ? $teams->filter(fn ($t) => $t->company_id === $s['newAssignCompany'])
                ->map(fn ($t) => ['id' => $t->id, 'label' => $t->name])->values()->all()
            : [];
        $typeOptions = [
            ['id' => 'worker_local', 'label' => $L['b_tWorkerLocal']],
            ['id' => 'worker_ko', 'label' => $L['b_tWorkerKo']],
            ['id' => 'manager_ko', 'label' => $L['b_tManagerKo']],
            ['id' => 'manager_local', 'label' => $L['b_tManagerLocal']],
            ['id' => 'third_party', 'label' => $L['b_tThirdParty']],
        ];
        $natOptions = [
            ['id' => '', 'label' => $L['b_natPick']],
            ['id' => 'LOCAL', 'label' => $L['b_natLocal']],
            ['id' => '한국인', 'label' => $L['b_natKo']],
        ];
        $payTypeOptions = [
            ['id' => 'salary', 'label' => $L['b_ptSalary']],
            ['id' => 'hourly', 'label' => $L['b_ptHourly']],
            ['id' => 'both', 'label' => $L['b_ptBoth']],
        ];
        // app language for the employee — labels are self-describing, no translation
        $langOptions = [
            ['id' => 'es', 'label' => 'Español'],
            ['id' => 'en', 'label' => 'English'],
            ['id' => 'ko', 'label' => '한국어'],
        ];
        // invite drawer: roles the inviter may grant, real sites, companies on them
        $inviteRoleOptions = array_map(
            fn ($r) => ['id' => $r, 'label' => $L['access_'.$r] ?? $r],
            $caps['assignableRoles'] ?? []
        );
        $inviteSiteOptions = $visibleSites->map(fn ($st) => ['id' => $st->id, 'label' => $st->name.' · '.$st->city])->all();
        $inviteCompanyOptions = array_merge(
            [['id' => '', 'label' => '—']],
            $visibleSites->flatMap(fn ($st) => $companies->where('site_id', $st->id)
                ->map(fn ($c) => ['id' => $c->id, 'label' => $c->name.' · '.$st->name]))->values()->all()
        );

        // ---- projects hub ----
        $scopedCompanies = $s['site'] === 'all' ? $companies : $companies->filter(fn ($c) => $c->site_id === $s['site'])->values();
        $companyCards = $scopedCompanies->map(function ($c) use ($teams, $activeAll, $employees, $empName, $inits, $siteById, $managerOptions) {
            $cTeams = $teams->filter(fn ($t) => $t->company_id === $c->id)->map(function ($t) use ($activeAll, $employees, $empName, $inits, $managerOptions) {
                $members = $activeAll->filter(fn ($e) => $e->team_id === $t->id);
                $pres = $members->filter(fn ($e) => in_array($e->status, ['present', 'late']))->count();
                $leadEmp = $employees->firstWhere('id', $t->lead);

                return [
                    'id' => $t->id, 'name' => $t->name, 'color' => $t->color,
                    'count' => $members->count(), 'present' => $pres, 'leadId' => $t->lead,
                    'leadName' => $leadEmp ? $empName($leadEmp) : '—',
                    'leadInit' => $leadEmp ? $inits($leadEmp) : '?',
                    'leadOptions' => $managerOptions,
                ];
            })->values()->all();
            $st = $siteById->get($c->site_id);

            return [
                'id' => $c->id, 'name' => $c->name,
                'site' => $st->name ?? '', 'gc' => $st->gc ?? '', 'teams' => $cTeams,
            ];
        })->values()->all();

        // ---- site geofences (location + radius) ----
        $scopedSites = $s['site'] === 'all' ? $sites : $sites->filter(fn ($st) => $st->id === $s['site'])->values();
        $siteCards = $scopedSites->map(fn ($st) => [
            'id' => $st->id, 'name' => $st->name, 'city' => $st->city, 'gc' => $st->gc,
            'hasGeo' => $st->lat !== null && $st->lng !== null,
            'lat' => $st->lat, 'lng' => $st->lng,
            'radius' => (int) ($st->radius_m ?: Geo::DEFAULT_RADIUS_M),
            'coords' => $st->lat !== null && $st->lng !== null
                ? number_format((float) $st->lat, 5).', '.number_format((float) $st->lng, 5)
                : null,
            'companyCount' => $companies->where('site_id', $st->id)->count(),
        ])->all();
        $siteModalId = $s['siteModal'] ?? null;
        $siteModal = $siteModalId ? ($siteById->get($siteModalId)) : null;
        $delSiteId = $s['deleteSiteId'] ?? null;
        $delSite = $delSiteId ? $siteById->get($delSiteId) : null;

        // ---- badge wizard ----
        $ext = ['gc' => 'HOFFMAN', 'company' => 'Sonoran MEP', 'last' => 'MARTÍNEZ', 'first' => 'CARLOS', 'role' => 'ELECTRICIAN', 'issued' => '03/14/2026'];
        $nfcUid = $s['nfcUid'];
        $nfcId = $s['nfcId'];
        $regEmpId = ($s['hasUid'] ?? false) ? $nfcId : 'N- — — —';
        $regTeamOptions = $teams->map(fn ($t) => ['id' => $t->id, 'label' => $t->name.' · '.$companyName($t->company_id)])->all();

        // the analyzed badge photo, shown whole (contain) in the extracted panel
        $facePhoto = $s['facePhotoData'] ?? '';
        $faceCrop = $facePhoto !== ''
            ? "background-image:url('{$facePhoto}');background-size:contain;background-position:center;background-repeat:no-repeat;background-color:#16181D;"
            : null;

        // ---- payroll ----
        // active roster + anyone terminated mid-period who still has worked days owed
        $fmtH = fn (float $h) => rtrim(rtrim(number_format($h, 1, '.', ''), '0'), '.');
        $payPeople = $siteScope($activeAll->concat($terminatedOwed)->values());
        $payRows = $payPeople->map(function ($e) use ($empName, $inits, $teamName, $teamColor, $bFor, $payments, $fmtH) {
            $b = $bFor($e);
            $gross = Payroll::grossPay($b, $e->rate);

            return [
                'id' => $e->id, 'name' => $empName($e), 'initials' => $inits($e),
                'teamName' => $teamName($e->team_id), 'teamColor' => $teamColor($e->team_id),
                'rate' => Money::rate($e->rate), 'reg' => $fmtH($b['reg']), 'ot' => $fmtH($b['ot']),
                'gross' => Money::usd($gross), 'net' => Money::usd($gross),
                'paid' => $payments->has($e->id),
                'terminated' => $e->emp === 'terminated',
            ];
        })->all();

        $payDetailData = null;
        $voucher = null;
        if ($s['payDetail'] !== null) {
            $e = $activeAll->firstWhere('id', $s['payDetail']) ?? $employees->firstWhere('id', $s['payDetail']);
            if ($e) {
                $periodPunches = Punch::where('employee_id', $e->id)
                    ->whereBetween('work_date', [$periodStart, $periodEnd])
                    ->whereNotNull('in_min')->whereNotNull('out_min')
                    ->orderBy('work_date')->get();
                if ($periodPunches->isNotEmpty()) {
                    $days = $periodPunches->map(function ($pn) use ($tl) {
                        $date = Carbon::parse($pn->work_date);
                        $c = \App\Support\Attendance::settle($pn);
                        $chips = [];
                        if ($pn->no_lunch) {
                            $chips[] = ['label' => $tl('No lunch', 'Sin almuerzo', '무점심'), 'bg' => '#F0F4EE', 'color' => '#5A7A4A'];
                        }
                        if ($c['adjusted']) {
                            $chips[] = ['label' => $tl('Adjusted', 'Ajustado', '팀장 조정'), 'bg' => '#E9F1FB', 'color' => '#3B72E0'];
                        }
                        if ($pn->early_reason) {
                            $chips[] = ['label' => $pn->early_reason, 'bg' => '#FBF1DF', 'color' => '#8A6A2E'];
                        }

                        return [
                            'd' => $date->format('M j'), 'dow' => $date->format('D'),
                            'inFmt' => $c['paidInFmt'], 'outFmt' => $c['paidOutFmt'],
                            'paid' => number_format(max(0, $c['paid']), 1).'h', 'chips' => $chips,
                        ];
                    })->all();
                    $b = $bFor($e);
                    $h = [
                        'days' => $days,
                        'reg' => $fmtH($b['reg']), 'ot' => $fmtH($b['ot']),
                        'gross' => Payroll::grossPay($b, $e->rate),
                    ];
                } else {
                    $h = Payroll::history($e->wh, $e->id, $e->rate, $tl);
                }
                $payDetailData = [
                    'id' => $e->id, 'name' => $empName($e), 'initials' => $inits($e),
                    'teamName' => $teamName($e->team_id), 'teamColor' => $teamColor($e->team_id), 'role' => $e->role,
                    'rate' => Money::rate($e->rate).'/hr', 'days' => $h['days'],
                    'reg' => $h['reg'].'h', 'ot' => $h['ot'].'h', 'gross' => Money::usd($h['gross']),
                ];
                $existingPay = $payments->get($e->id);
                $voucher = [
                    'name' => $payDetailData['name'], 'teamName' => $payDetailData['teamName'], 'role' => $e->role,
                    'reg' => $payDetailData['reg'], 'ot' => $payDetailData['ot'], 'gross' => $payDetailData['gross'],
                    'rate' => $payDetailData['rate'], 'empId' => $e->emp_id,
                    'checkNo' => $s['checkNo'] !== '' ? $s['checkNo'] : ($existingPay->check_no ?? ''),
                    'payDate' => $s['payDate'],
                    'alreadyPaid' => $existingPay !== null,
                ];
            }
        }
        $totalPayout = Money::usd($payPeople->sum($grossFor));

        // recipient dropdown for the payroll-register export: quick pay-type buckets,
        // then drill down to a specific company or crew (site-scoped)
        $payRecipientOptions = [
            ['id' => 'hourly', 'label' => $L['p_exHourly']],
            ['id' => 'all', 'label' => $L['p_exAll']],
            ['id' => 'salary', 'label' => $L['p_exSalary']],
        ];
        foreach ($scopedCompanies as $c) {
            $payRecipientOptions[] = ['id' => 'co:'.$c->id, 'label' => '🏢 '.$c->name];
            foreach ($teams->where('company_id', $c->id) as $t) {
                $payRecipientOptions[] = ['id' => 'tm:'.$t->id, 'label' => '   · '.$t->name];
            }
        }

        // ---- attendance / qr ----
        $qrManualRows = collect($empRows)->filter(fn ($e) => ! $e['isTerminated'])->take(8)->values()->all();
        $qrGroups = array_map(fn ($c) => [
            'company' => $c['name'],
            'teams' => array_map(fn ($t) => [
                'id' => $t['id'], 'name' => $t['name'], 'color' => $t['color'], 'lead' => $t['leadName'],
                'active' => $s['qrTeam'] === $t['id'],
            ], $c['teams']),
        ], $companyCards);

        $qrTeamModel = $teamById->get($s['qrTeam']) ?? $teams->first();
        $qrLead = $qrTeamModel ? $employees->firstWhere('id', $qrTeamModel->lead) : null;
        $selQr = [
            'company' => $qrTeamModel ? $companyName($qrTeamModel->company_id) : '—',
            'team' => $qrTeamModel->name ?? '—',
            'lead' => $qrLead ? $empName($qrLead) : '—',
        ];
        // QR svg + daily timesheet are attendance-screen work — skip them on the
        // other six screens (every Livewire interaction re-renders, so this is hot)
        $onAttendance = $s['screen'] === 'attendance';
        $teamQrSvg = $onAttendance
            ? ($qrTeamModel ? RealQr::svg(url('/scan/'.$qrTeamModel->id)) : RealQr::svg(url('/')))
            : '';

        // ---- daily timesheet (company → team → worker; actual vs paid, reg/OT) ----
        $timesheet = $onAttendance
            ? Timesheet::forDate($s['attDate'] ?? now()->format('Y-m-d'), $s['site'], $lang)
            : [
                'date' => $s['attDate'] ?? now()->format('Y-m-d'), 'dateLabel' => '', 'rows' => [],
                'count' => 0, 'present' => 0, 'regTotal' => '0.0h', 'otTotal' => '0.0h', 'regNum' => 0.0, 'otNum' => 0.0,
            ];

        // ---- field-lead crew panel (mobile "우리 팀" tab) ----
        $crew = null;
        if ($isFieldLead) {
            $crewDate = now()->format('Y-m-d');
            $crewTs = Timesheet::forDate($crewDate, 'all', $lang);
            $crewRows = array_values(array_filter(
                $crewTs['rows'],
                fn ($r) => in_array($r['teamId'], $mobileLeadTeamIds, true)
            ));
            $crewTeams = $teams->whereIn('id', $mobileLeadTeamIds)->map(fn ($t) => [
                'id' => $t->id, 'name' => $t->name, 'color' => $t->color,
                'hasShift' => $t->shift_in !== null && $t->shift_out !== null,
                'weekday' => ($t->shift_in !== null && $t->shift_out !== null)
                    ? Shift::fmtMin($t->shift_in).' – '.Shift::fmtMin($t->shift_out) : null,
                'saturday' => ($t->sat_in !== null && $t->sat_out !== null)
                    ? Shift::fmtMin($t->sat_in).' – '.Shift::fmtMin($t->sat_out) : null,
            ])->values()->all();
            // pending correction requests the lead can decide from the phone —
            // snapshotted to them as approver, or filed by their crew members
            $leadMeId = $s['meEmployeeId'] ?? null;
            $crewCorrections = AttendanceCorrection::where('status', 'pending')->orderBy('created_at')->get()
                ->filter(function ($c) use ($leadMeId, $employees, $mobileLeadTeamIds) {
                    if ((int) $c->employee_id === (int) $leadMeId) {
                        return false;   // never their own request
                    }
                    if ($c->lead_id !== null && (int) $c->lead_id === (int) $leadMeId) {
                        return true;
                    }
                    $worker = $employees->firstWhere('id', $c->employee_id);

                    return $worker && in_array($worker->team_id, $mobileLeadTeamIds, true);
                })
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => ($w = $employees->firstWhere('id', $c->employee_id)) ? $empName($w) : '#'.$c->employee_id,
                    'date' => Carbon::parse($c->work_date)->format('M j'),
                    'isDelete' => $c->type === 'delete',
                    'reqIn' => $c->req_in_min !== null ? Shift::fmtMin($c->req_in_min) : '—',
                    'reqOut' => $c->req_out_min !== null ? Shift::fmtMin($c->req_out_min) : '—',
                    'reason' => (string) ($c->reason ?? ''),
                    'canDecide' => Corrections::canDecide($c, $leadMeId, false),
                ])->values()->all();

            // pending leave requests from crew members (lead approves on the phone)
            $crewLeaves = Leave::where('status', 'pending')->orderBy('start_date')->get()
                ->filter(function ($l) use ($employees, $mobileLeadTeamIds, $leadMeId) {
                    if ((int) $l->employee_id === (int) $leadMeId) {
                        return false;
                    }
                    $w = $employees->firstWhere('id', $l->employee_id);

                    return $w && in_array($w->team_id, $mobileLeadTeamIds, true);
                })
                ->map(function ($l) use ($employees, $empName) {
                    $w = $employees->firstWhere('id', $l->employee_id);
                    $md = fn ($ymd) => preg_match('/^\d{4}-(\d{2})-(\d{2})$/', $ymd, $m) ? $m[1].'/'.$m[2] : $ymd;

                    return ['id' => $l->id, 'name' => $w ? $empName($w) : '#'.$l->employee_id,
                        'range' => $md($l->start_date).' – '.$md($l->end_date), 'reason' => (string) ($l->reason ?? '')];
                })->values()->all();

            $crew = [
                'date' => $crewDate,
                'dateLabel' => Carbon::parse($crewDate)->format('D · M j'),
                'rows' => $crewRows,
                'count' => count($crewRows),
                'present' => count(array_filter($crewRows, fn ($r) => ! empty($r['hasPunch']))),
                'teams' => $crewTeams,
                'corrections' => $crewCorrections,
                'leaves' => $crewLeaves,
            ];
        }

        // ---- worker mobile (me = authed employee, demo fallback 106) ----
        $meId = $s['meEmployeeId'] ?? 106;
        $me = $employees->firstWhere('id', $meId) ?? $employees->firstWhere('id', 106) ?? $employees->first();
        if (! $me) {
            // empty roster (e.g. right after app:clear-demo) — safe placeholder so the app still renders
            $me = new Employee([
                'first' => $authUser?->name ?? 'Staff', 'last' => '', 'team_id' => null, 'company_id' => null,
                'role' => '', 'nat' => '', 'emp_id' => '—', 'phone' => '', 'email' => $authUser?->email ?? '',
                'issued' => '', 'rate' => 0, 'access' => $authUser?->access ?? 'worker', 'wh' => 0,
            ]);
            $me->id = 0;
        }
        $meB = $me->id ? $bFor($me) : Payroll::fallbackBreakdown($me);
        $wGross = Payroll::grossPay($meB, $me->rate);
        $worker = [
            'name' => $me->first.' '.$me->last, 'initials' => $inits($me),
            'teamName' => $teamName($me->team_id), 'teamColor' => $teamColor($me->team_id),
            'company' => $companyName($me->company_id), 'role' => $me->role, 'nat' => $me->nat,
            'empId' => $me->emp_id, 'phone' => $me->phone, 'email' => $me->email, 'issued' => $me->issued,
            'rate' => Money::rate($me->rate), 'reg' => $fmtH($meB['reg']), 'ot' => $fmtH($meB['ot']), 'hours' => $fmtH($meB['total']),
            'gross' => Money::usd($wGross), 'net' => Money::usd($wGross), 'access' => $L['access_'.$me->access] ?? $me->access,
        ];

        // authenticated user with no linked employee (e.g. admin previewing the
        // worker view) → show their real account name instead of the sample worker
        if (! $isDemo && $authUser && ! $authUser->employee_id) {
            $parts = preg_split('/\s+/', trim($authUser->name)) ?: [];
            $worker['name'] = $authUser->name;
            $worker['initials'] = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1).(isset($parts[1]) ? mb_substr($parts[1], 0, 1) : ''));
            $worker['role'] = $L['access_'.$authUser->access] ?? $me->role;
        }

        $dbPunches = Punch::where('employee_id', $me->id)
            ->orderByDesc('work_date')->limit(10)->get();
        $rawPunches = [
            ['d' => 'Jun 30', 'dow' => 'Mon', 'in' => '5:53 AM', 'out' => '2:48 PM', 'si' => 360, 'so' => 900, 'noLunch' => false],
            ['d' => 'Jun 28', 'dow' => 'Sat', 'in' => '6:58 AM', 'out' => '1:02 PM', 'si' => 420, 'so' => 840, 'noLunch' => true],
            ['d' => 'Jun 27', 'dow' => 'Fri', 'in' => '6:02 AM', 'out' => '5:12 PM', 'si' => 360, 'so' => 900, 'noLunch' => false],
            ['d' => 'Jun 26', 'dow' => 'Thu', 'in' => '7:06 AM', 'out' => '4:04 PM', 'si' => 420, 'so' => 960, 'noLunch' => false],
        ];
        if ($dbPunches->isNotEmpty()) {
            $punchLog = $dbPunches->map(function ($pn) use ($tl, $L) {
                $date = Carbon::parse($pn->work_date);
                $base = [
                    'd' => $date->format('M j'), 'dow' => $date->format('D'),
                    'pid' => $pn->id, 'seedNoLunch' => false, 'workDate' => $pn->work_date,
                ];
                if ($pn->in_min === null || $pn->out_min === null) {
                    // open day — clocked in, not yet out
                    return $base + [
                        'inFmt' => $pn->in_min !== null ? Shift::fmtMin($pn->in_min) : '—',
                        'outFmt' => '—', 'adjusted' => false, 'noLunch' => $pn->no_lunch,
                        'rawNote' => '', 'h' => '—', 'chips' => [],
                        'lunchToggleLabel' => $pn->no_lunch ? $L['m_lunchOff'] : $L['m_lunchOn'],
                        'lunchIsNo' => $pn->no_lunch,
                    ];
                }
                [$si, $so] = Payroll::scheduleFor($pn->in_min, $date->isSaturday());
                $c = Shift::compute(Shift::fmtMin($pn->in_min), Shift::fmtMin($pn->out_min), $si, $so, $pn->no_lunch);
                $chips = [];
                if ($c['adjusted']) {
                    $chips[] = ['label' => $tl('Shift-time adjusted', 'Ajuste de horario', '정규시각 보정'), 'bg' => '#EAF1FB', 'color' => '#3B72E0'];
                }
                if ($c['lunch'] > 0) {
                    $chips[] = ['label' => $tl('Lunch −1h', 'Almuerzo −1h', '점심 −1h'), 'bg' => '#FBF1E9', 'color' => '#C97A34'];
                }
                if ($c['noLunch']) {
                    $chips[] = ['label' => $tl('No lunch', 'Sin almuerzo', '무점심'), 'bg' => '#F0F4EE', 'color' => '#5A7A4A'];
                }
                if ($pn->early_reason) {
                    $chips[] = ['label' => $pn->early_reason, 'bg' => '#FBF1DF', 'color' => '#8A6A2E'];
                }

                return $base + [
                    'inFmt' => $c['inFmt'], 'outFmt' => $c['outFmt'],
                    'adjusted' => $c['adjusted'], 'noLunch' => $c['noLunch'],
                    'rawNote' => $c['adjusted'] ? $tl('Actual', 'Real', '실제').' '.$c['rawIn'].' – '.$c['rawOut'] : '',
                    'h' => number_format(max(0, $c['paid']), 1).'h', 'chips' => $chips,
                    'lunchToggleLabel' => $c['noLunch'] ? $L['m_lunchOff'] : $L['m_lunchOn'],
                    'lunchIsNo' => $c['noLunch'],
                ];
            })->all();
        } else {
            $punchLog = array_map(function ($r) use ($s, $tl, $L) {
                $nl = array_key_exists($r['d'], $s['lunchOv']) ? $s['lunchOv'][$r['d']] : $r['noLunch'];
                $c = Shift::compute($r['in'], $r['out'], $r['si'], $r['so'], $nl);
                $chips = [];
                if ($c['adjusted']) {
                    $chips[] = ['label' => $tl('Shift-time adjusted', 'Ajuste de horario', '정규시각 보정'), 'bg' => '#EAF1FB', 'color' => '#3B72E0'];
                }
                if ($c['lunch'] > 0) {
                    $chips[] = ['label' => $tl('Lunch −1h', 'Almuerzo −1h', '점심 −1h'), 'bg' => '#FBF1E9', 'color' => '#C97A34'];
                }
                if ($c['noLunch']) {
                    $chips[] = ['label' => $tl('No lunch', 'Sin almuerzo', '무점심'), 'bg' => '#F0F4EE', 'color' => '#5A7A4A'];
                }

                return [
                    'd' => $r['d'], 'dow' => $r['dow'], 'inFmt' => $c['inFmt'], 'outFmt' => $c['outFmt'],
                    'pid' => null, 'workDate' => null,
                    'adjusted' => $c['adjusted'], 'noLunch' => $c['noLunch'], 'seedNoLunch' => $r['noLunch'],
                    'rawNote' => $c['adjusted'] ? $tl('Actual', 'Real', '실제').' '.$c['rawIn'].' – '.$c['rawOut'] : '',
                    'h' => number_format($c['paid'], 1).'h', 'chips' => $chips,
                    'lunchToggleLabel' => $c['noLunch'] ? $L['m_lunchOff'] : $L['m_lunchOn'],
                    'lunchIsNo' => $c['noLunch'],
                ];
            }, $rawPunches);
        }

        // ---- attendance-correction requests (worker side): status chips + open form ----
        $corrByDate = AttendanceCorrection::where('employee_id', $me->id)
            ->orderByDesc('id')->get()->groupBy('work_date')->map(fn ($g) => $g->first());
        $corrChip = function (?string $workDate) use ($corrByDate, $tl) {
            $c = $workDate ? $corrByDate->get($workDate) : null;
            if (! $c) {
                return null;
            }

            return match ($c->status) {
                'pending' => ['label' => $tl('Correction pending', 'Corrección pendiente', '정정 검토중'), 'bg' => '#FBF1DF', 'color' => '#8A6A2E'],
                'approved' => ['label' => $tl('Corrected', 'Corregido', '정정 완료'), 'bg' => '#EAF5EE', 'color' => '#2E7D4F'],
                'rejected' => ['label' => $tl('Correction rejected', 'Corrección rechazada', '정정 반려'), 'bg' => '#FBEAEA', 'color' => '#B23B3B'],
                default => null,
            };
        };
        $punchLog = array_map(function ($row) use ($corrChip, $corrByDate) {
            $wd = $row['workDate'] ?? null;
            $row['corrChip'] = $corrChip($wd);
            $row['corrPending'] = $wd && optional($corrByDate->get($wd))->status === 'pending';

            return $row;
        }, $punchLog);

        $correctionForm = null;
        if (! empty($s['correctionOpen']) && ! empty($s['correctionDate'])) {
            $cTeam = $me->team_id ? Team::find($me->team_id) : null;
            $cLead = $cTeam && $cTeam->lead ? Employee::find($cTeam->lead) : null;
            $correctionForm = [
                'date' => $s['correctionDate'],
                'dateLabel' => Carbon::parse($s['correctionDate'])->format('M j, Y (D)'),
                'company' => $companyName($cTeam?->company_id ?? $me->company_id),
                'team' => $cTeam?->name ?? '—',
                'lead' => $cLead ? $cLead->displayName($lang) : $tl('No lead — HR reviews', 'Sin líder — RR. HH.', '팀장 미지정 · 인사팀 검토'),
                'type' => $s['correctionType'] ?? 'set',
            ];
        }

        $ruleNote = $tl(
            'Reg 6–3 / 7–4 · 1h unpaid lunch · OT 1.5× over 40h/wk',
            'Reg 6–3 / 7–4 · almuerzo 1h no pagado · Extra 1.5× tras 40h/sem',
            '정규 6–3 / 7–4 · 점심 1h 무급 · 주 40h 초과 OT 1.5×'
        );
        $reasonOptions = array_merge(
            array_map(fn ($r) => ['value' => $r, 'label' => $r], $L['w_reasons']),
            [['value' => '__custom__', 'label' => $L['w_earlyOther']]]
        );

        $meName = $authUser?->name
            ?? ($s['role'] === 'admin' ? ($lang === 'ko' ? '김현수' : 'Hyunsoo Kim') : ($lang === 'ko' ? '박정우' : 'Jungwoo Park'));

        // Access hierarchy: which views this account may switch to (rank ≤ ceiling).
        // The middle rung is the field-lead mobile preview ('lead'), which replaced
        // the old desktop "manager" view — clicking it shows the crew-lead phone UI.
        $rank = ['worker' => 1, 'lead' => 2, 'manager' => 2, 'admin' => 3];
        $access = $s['access'] ?? 'admin';
        $previewLead = ($s['previewEmpId'] ?? null) !== null;
        $roleLabels = ['admin' => $L['roleAdmin'], 'lead' => $L['roleFieldLead'], 'worker' => $L['roleWorker']];
        $activeView = fn ($r) => match ($r) {
            'admin' => $s['role'] === 'admin',
            'lead' => $s['role'] === 'worker' && $previewLead,
            'worker' => $s['role'] === 'worker' && ! $previewLead,
            default => false,
        };
        $viewSwitch = [];
        foreach (['admin', 'lead', 'worker'] as $r) {
            if (($rank[$r] ?? 0) <= ($rank[$access] ?? 3)) {
                $viewSwitch[] = ['role' => $r, 'label' => $roleLabels[$r], 'active' => $activeView($r)];
            }
        }

        // Desktop self clock-in/out (any admin/manager, even before an employee
        // record is linked — the record is auto-created on first clock).
        $deskClock = ['show' => false];
        $selfEid = $s['selfEmployeeId'] ?? null;
        if (($s['canDeskClock'] ?? false)) {
            $selfPunch = $selfEid ? Punch::where('employee_id', $selfEid)
                ->where('work_date', Carbon::now()->format('Y-m-d'))->first() : null;
            $isIn = $selfPunch && $selfPunch->in_min !== null && $selfPunch->out_min === null;
            // both in and out recorded → the day is locked (one clock-in + one clock-out)
            $isDone = $selfPunch && $selfPunch->in_min !== null && $selfPunch->out_min !== null;
            $deskClock = [
                'show' => true,
                'isIn' => $isIn,
                'isDone' => $isDone,
                'statusLabel' => $isDone ? $L['w_workDone'] : ($isIn ? $L['w_status_in'] : $L['w_status_out']),
                'btnLabel' => $isDone ? $L['w_workDone'] : ($isIn ? $L['w_clockout'] : $L['w_clockin']),
                'since' => $isIn && $selfPunch->in_min !== null ? Shift::fmtMin($selfPunch->in_min) : null,
                'sinceWord' => $L['w_since'],
            ];
        }

        // ---- internal comms (announcements · company/crew chat · DM · bell) ----
        // Built for the desktop bell/screen AND the worker home board (announcements
        // feed + the rooms the worker belongs to).
        $comms = null;
        if ($s['screen'] !== 'login') {
            $actor = Employee::find($s['actorId'] ?? null);
            if ($actor) {
                $comms = CommsView::build($actor, $s, $lang, (bool) ($s['canManage'] ?? false));

                // announcement notice feed for the worker home board (newest first)
                $annCh = Channel::where('type', 'announcement')->first();
                $comms['annId'] = $annCh?->id;
                $comms['annFeed'] = $annCh
                    ? Message::where('channel_id', $annCh->id)->orderByDesc('id')->limit(6)->get()
                        ->map(fn (Message $m) => [
                            'body' => $m->body,
                            'sender' => optional(Employee::find($m->sender_id))->displayName($lang) ?? '—',
                            'time' => $m->created_at && $m->created_at->isToday()
                                ? $m->created_at->format('g:i A')
                                : optional($m->created_at)->format('M j'),
                        ])->all()
                    : [];
                // the worker's rooms (group rooms + DMs); announcements shown separately as the board
                $comms['myRooms'] = array_merge(
                    $comms['groups']['group'] ?? [],
                    $comms['groups']['dm'] ?? [],
                );
            }
        }

        return [
            'L' => $L,
            'role' => $s['role'], 'lang' => $lang, 'screen' => $s['screen'],
            'isLogin' => $s['screen'] === 'login',
            'reportOpen' => (bool) ($s['reportOpen'] ?? false),
            'isWorker' => $s['role'] === 'worker',
            'isDesktopApp' => $s['role'] !== 'worker' && $s['screen'] !== 'login',
            'stat_workers' => $activeAll->filter(fn ($e) => $e->type === 'worker')->count(),
            'nav' => $nav, 'mobileTabs' => $mobileTabs, 'crew' => $crew, 'isFieldLead' => $isFieldLead,
            'siteOptions' => $siteOptions, 'siteVal' => $s['site'],
            'me' => [
                'name' => $meName,
                'role' => $s['role'] === 'admin' ? $L['roleAdmin'] : $L['roleManager'],
                'initials' => $s['role'] === 'admin' ? 'HK' : 'JP',
                'color' => $s['role'] === 'admin' ? '#E85D2A' : '#3B72E0',
            ],
            'pageTitle' => $L['t_'.$s['screen']] ?? '', 'pageSub' => $L['s_'.$s['screen']] ?? '', 'today' => self::todayLabel($lang),
            // dashboard
            'dash' => [
                'layout' => $s['dashLayout'], 'cnt' => $cnt, 'totalActive' => $totalActive, 'onsite' => $onsite, 'rate' => $rate,
                'periodPay' => Money::usd($periodPayNum), 'payPeriod' => $periodLabel, 'avgH' => $avgH,
                'ringDash' => (int) round(2 * M_PI * 52 * (1 - $rate / 100)),
                'teamStats' => $teamStats, 'companyStats' => $companyStats, 'recent' => $recent,
                'offCount' => $offCount, 'offList' => array_slice($offList, 0, 5),
                'pendCount' => $pendCount, 'pendList' => $pendCorr->take(4)->all(),
                'siteCards' => $dashSiteCards,
                'repeatNoShow' => $repeatNoShow, 'noShowThreshold' => WorkerStatus::UNEXCUSED_ALERT,
                'pendLeaves' => $pendLeaves, 'pendResign' => $pendResign,
                'summary' => $dashSummary,
            ],
            // employees
            'emp' => [
                'rows' => $empRows, 'sel' => $sel, 'editForm' => $s['editForm'],
                'delName' => $delRaw ? $empName($delRaw) : null, 'termName' => $termRaw ? $empName($termRaw) : null,
                'teamChips' => $scopedTeams->map(fn ($t) => ['id' => $t->id, 'label' => $t->name, 'color' => $t->color, 'active' => $s['teamFilter'] === $t->id])->all(),
                'companyOptions' => $companyOptions, 'teamOptionsAll' => $teamOptionsAll, 'typeOptions' => $typeOptions, 'natOptions' => $natOptions,
                'payTypeOptions' => $payTypeOptions, 'langOptions' => $langOptions,
                'inviteRoleOptions' => $inviteRoleOptions, 'inviteSiteOptions' => $inviteSiteOptions,
                'inviteCompanyOptions' => $inviteCompanyOptions,
                'accColor' => $accColor,
                'assignments' => $empAssignments, 'assignTeamOptions' => $assignTeamOptions,
                'operator' => 'NAHSHON',
            ],
            // projects
            'projects' => [
                'companyCards' => $companyCards,
                'teamModalCo' => $s['teamModal'] ? $companyName($s['teamModal']) : '',
                'teamLeadOptions' => $managerOptions,
                'editingCompany' => (bool) ($s['editCompanyId'] ?? null),
                'editingTeam' => (bool) ($s['editTeamId'] ?? null),
                'delCompanyName' => ($s['deleteCompanyId'] ?? null) ? $companyName($s['deleteCompanyId']) : null,
                'delTeamName' => ($s['deleteTeamId'] ?? null) ? (optional($teamById->get($s['deleteTeamId']))->name ?? '—') : null,
                'sites' => $siteCards,
                'siteModal' => $siteModal ? [
                    'id' => $siteModal->id,
                    'name' => trim($siteModal->name.($siteModal->city ? ' · '.$siteModal->city : '')),
                ] : null,
                'siteLat' => $s['siteLat'] ?? '', 'siteLng' => $s['siteLng'] ?? '', 'siteRadius' => $s['siteRadius'] ?? '',
                'delSiteName' => $delSite ? trim($delSite->name.($delSite->city ? ' · '.$delSite->city : '')) : null,
                'delSiteCompanies' => $delSite ? $companies->where('site_id', $delSite->id)->count() : 0,
            ],
            // badge
            'badge' => [
                'ext' => $ext, 'nfcUid' => $nfcUid, 'nfcId' => $nfcId, 'regEmpId' => $regEmpId, 'faceCrop' => $faceCrop,
                'regTeamOptions' => $regTeamOptions, 'typeOptions' => $typeOptions,
                'payTypeOptions' => $payTypeOptions, 'langOptions' => $langOptions, 'accColor' => $accColor,
            ],
            // attendance
            'att' => [
                'view' => $s['attView'] ?? 'records',
                'timesheet' => $timesheet,
                'qrManualRows' => $qrManualRows, 'qrGroups' => $qrGroups, 'selQr' => $selQr,
                'teamQrSvg' => $teamQrSvg, 'leadWord' => $L['pj_lead'],
            ],
            // payroll
            'pay' => [
                'rows' => $payRows, 'totalPayout' => $totalPayout, 'detail' => $payDetailData, 'voucher' => $voucher,
                'companyName' => 'NAHSHON MEP', 'periodLabel' => $periodLabel,
                'recipientOptions' => $payRecipientOptions,
                'pdHistory' => $tl('Attendance history', 'Historial de asistencia', '출퇴근 이력'),
                'pdPeriod' => $tl('This pay period', 'Este periodo', '이번 정산기간').' · '.$periodLabel,
                'ruleNote' => $ruleNote,
            ],
            // worker mobile
            'worker' => [
                'me' => $worker, 'punchLog' => $punchLog, 'ruleNote' => $ruleNote,
                'reasonOptions' => $reasonOptions,
                'qrSvg' => $s['role'] === 'worker' ? RealQr::svg(url('/scan/'.$me->team_id)) : '',
                'correctionForm' => $correctionForm, 'canRequestCorrection' => $me->id > 0,
            ],
            'deskClock' => $deskClock,
            'comms' => $comms,
            'bellOpen' => (bool) ($s['bellOpen'] ?? false),
            'viewSwitch' => $viewSwitch,
            'access' => $access,
            'isDemo' => $isDemo,
            'authName' => $authUser?->name,
            'googleEnabled' => (bool) config('services.google.client_id'),
            'qrPrint' => ['company' => $selQr['company'], 'team' => $selQr['team'], 'lead' => $selQr['lead'], 'svg' => $teamQrSvg, 'leadWord' => $L['pj_lead']],
            'can' => $caps,
            'auditTrail' => ($caps['auditView'] ?? false)
                ? AuditLog::orderByDesc('id')->limit(30)->get()->map(fn ($a) => [
                    'when' => $a->created_at?->format('M j · g:i A') ?? '—',
                    'actor' => $a->actor_name,
                    'action' => $a->action,
                    'target' => $a->target,
                    'detail' => $a->detail,
                ])->all()
                : null,
            'toast' => $s['toast'],
        ];
    }

    /** Top-bar date, computed live per language (Phoenix time — no DST, always MST). */
    protected static function todayLabel(string $lang): string
    {
        $n = now();
        $dowEn = $n->format('D');
        if ($lang === 'ko') {
            $dowKo = ['Mon' => '월', 'Tue' => '화', 'Wed' => '수', 'Thu' => '목', 'Fri' => '금', 'Sat' => '토', 'Sun' => '일'][$dowEn];

            return $n->format('Y').'년 '.$n->format('n').'월 '.$n->format('j').'일 ('.$dowKo.') · MST';
        }
        if ($lang === 'es') {
            $dowEs = ['Mon' => 'Lun', 'Tue' => 'Mar', 'Wed' => 'Mié', 'Thu' => 'Jue', 'Fri' => 'Vie', 'Sat' => 'Sáb', 'Sun' => 'Dom'][$dowEn];
            $monEs = [1 => 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'][(int) $n->format('n')];

            return $dowEs.' · '.$n->format('j').' '.$monEs.' '.$n->format('Y').' · MST';
        }

        return $dowEn.' · '.$n->format('M j, Y').' · MST';
    }
}
