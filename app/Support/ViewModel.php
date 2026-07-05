<?php

namespace App\Support;

use App\Models\Assignment;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Payment;
use App\Models\Punch;
use App\Models\Site;
use App\Models\Team;
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
        $accColor = ['admin' => '#E85D2A', 'manager' => '#3B72E0', 'worker' => '#6B6E76'];

        $isDemo = (bool) config('workforce.demo');
        $authUser = Auth::user();
        [$periodStart, $periodEnd, $periodLabel] = Payroll::currentPeriod();
        $periodHours = fn (Employee $e) => Payroll::periodHoursFromPunches($e->id, $periodStart, $periodEnd);
        $hoursFor = function (Employee $e) use ($periodHours) {
            $h = $periodHours($e);

            return $h !== null ? (int) round($h) : $e->wh;
        };
        $payments = Payment::where('period_start', $periodStart)->get()->keyBy('employee_id');

        // ---- load domain ----
        $sites = Site::orderBy('id')->get();
        $companies = Company::orderBy('id')->get();
        $teams = Team::orderBy('id')->get();
        $employees = Employee::orderBy('id')->get();

        $teamById = $teams->keyBy('id');
        $companyById = $companies->keyBy('id');
        $siteById = $sites->keyBy('id');

        $teamName = fn ($tid) => optional($teamById->get($tid))->name ?? '—';
        $companyName = fn ($cid) => optional($companyById->get($cid))->name ?? '—';
        $teamColor = fn ($tid) => optional($teamById->get($tid))->color ?? '#9AA0A6';
        $empName = fn (Employee $e) => $e->displayName($lang);
        $inits = fn (Employee $e) => $e->initials();

        $siteScope = fn ($coll) => $s['site'] === 'all' ? $coll : $coll->filter(fn ($e) => $e->site_id === $s['site'])->values();
        $activeAll = $employees->filter(fn ($e) => $e->emp === 'active')->values();
        $scopedActive = $siteScope($activeAll);

        // ---- nav (managers have no payroll) ----
        $navKeys = $s['role'] === 'manager'
            ? ['dashboard', 'comms', 'projects', 'employees', 'badge', 'attendance']
            : ['dashboard', 'comms', 'projects', 'employees', 'badge', 'attendance', 'payroll'];
        $nav = array_map(fn ($k) => [
            'key' => $k, 'label' => $L['nav_'.$k], 'active' => $s['screen'] === $k,
        ], $navKeys);

        $mKeys = ['home', 'work', 'pay', 'me'];
        $mobileTabs = array_map(fn ($k) => [
            'key' => $k, 'label' => $L['w_tab_'.$k], 'active' => $s['mobileTab'] === $k,
        ], $mKeys);

        $siteOptions = array_merge(
            [['id' => 'all', 'label' => $L['allSites']]],
            $sites->map(fn ($st) => ['id' => $st->id, 'label' => $st->name.' · '.$st->city])->all()
        );

        // ---- dashboard stats ----
        $cnt = ['present' => 0, 'late' => 0, 'absent' => 0, 'off' => 0];
        foreach ($scopedActive as $e) {
            $cnt[$e->status]++;
        }
        $totalActive = $scopedActive->count();
        $onsite = $cnt['present'] + $cnt['late'];
        $rate = $totalActive ? (int) round($onsite / $totalActive * 100) : 0;
        $periodPayNum = $scopedActive->sum(fn ($e) => Payroll::gross($hoursFor($e), $e->rate));
        $avgH = $scopedActive->count()
            ? (int) round($scopedActive->sum(fn ($e) => $e->wh) / $scopedActive->count() / 2)
            : 0;

        $scopedTeams = $s['site'] === 'all'
            ? $teams
            : $teams->filter(function ($t) use ($companyById, $s) {
                $c = $companyById->get($t->company_id);

                return $c && $c->site_id === $s['site'];
            })->values();

        $teamStats = $scopedTeams->map(function ($t) use ($scopedActive, $companyName) {
            $list = $scopedActive->filter(fn ($e) => $e->team_id === $t->id);
            $pres = $list->filter(fn ($e) => in_array($e->status, ['present', 'late']))->count();

            return [
                'id' => $t->id, 'name' => $t->name, 'companyId' => $t->company_id,
                'company' => $companyName($t->company_id), 'color' => $t->color,
                'total' => $list->count(), 'present' => $pres,
                'pct' => $list->count() ? (int) round($pres / $list->count() * 100) : 0,
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

        // ---- employees ----
        $q = strtolower(trim($s['search']));
        $pool = $siteScope($employees);
        if ($s['empFilter'] === 'active') {
            $pool = $pool->filter(fn ($e) => $e->emp === 'active');
        } elseif ($s['empFilter'] === 'terminated') {
            $pool = $pool->filter(fn ($e) => $e->emp === 'terminated');
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
                'access' => $e->access, 'accessLabel' => $L['access_'.$e->access], 'accessColor' => $accColor[$e->access],
                'badgeQr' => $e->badge_qr, 'badgePhoto' => $e->badge_photo,
                'isTerminated' => $e->emp === 'terminated', 'isActive' => $e->emp === 'active',
                'rowOpacity' => $e->emp === 'terminated' ? '0.55' : '1',
                'inT' => $e->in_t, 'term' => $e->term,
            ];
        };
        $empRows = $filtered->map($mapEmp)->all();

        $selRaw = $employees->firstWhere('id', $s['selectedEmp']);
        $sel = $selRaw ? $mapEmp($selRaw) : null;
        $delRaw = $employees->firstWhere('id', $s['deleteId']);
        $termRaw = $employees->firstWhere('id', $s['terminateId']);

        $managers = $employees->filter(fn ($e) => $e->type === 'manager' && $e->emp === 'active')->values();
        $managerOptions = $managers->map(fn ($m) => ['id' => $m->id, 'label' => $empName($m)])->all();
        $companyOptions = $companies->map(fn ($c) => ['id' => $c->id, 'label' => $c->name])->all();
        $teamOptionsAll = $teams->map(fn ($t) => ['id' => $t->id, 'label' => $t->name.' · '.$companyName($t->company_id)])->all();

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
            ['id' => 'manager', 'label' => $L['b_tManager']],
        ];

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
        $payRows = $scopedActive->map(function ($e) use ($empName, $inits, $teamName, $teamColor, $hoursFor, $payments) {
            $wh = $hoursFor($e);
            $reg = Payroll::regHours($wh);
            $ot = Payroll::otHours($wh);
            $gross = Payroll::gross($wh, $e->rate);

            return [
                'id' => $e->id, 'name' => $empName($e), 'initials' => $inits($e),
                'teamName' => $teamName($e->team_id), 'teamColor' => $teamColor($e->team_id),
                'rate' => Money::rate($e->rate), 'reg' => (string) $reg, 'ot' => (string) $ot,
                'gross' => Money::usd($gross), 'net' => Money::usd($gross),
                'paid' => $payments->has($e->id),
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
                        [$si, $so] = Payroll::scheduleFor($pn->in_min, $date->isSaturday());
                        $c = Shift::compute(Shift::fmtMin($pn->in_min), Shift::fmtMin($pn->out_min), $si, $so, $pn->no_lunch);
                        $chips = [];
                        if ($c['noLunch']) {
                            $chips[] = ['label' => $tl('No lunch', 'Sin almuerzo', '무점심'), 'bg' => '#F0F4EE', 'color' => '#5A7A4A'];
                        }
                        if ($pn->early_reason) {
                            $chips[] = ['label' => $pn->early_reason, 'bg' => '#FBF1DF', 'color' => '#8A6A2E'];
                        }

                        return [
                            'd' => $date->format('M j'), 'dow' => $date->format('D'),
                            'inFmt' => $c['inFmt'], 'outFmt' => $c['outFmt'],
                            'paid' => number_format(max(0, $c['paid']), 1).'h', 'chips' => $chips,
                        ];
                    })->all();
                    $sum = (int) round($periodHours($e) ?? 0);
                    $h = [
                        'days' => $days,
                        'reg' => Payroll::regHours($sum), 'ot' => Payroll::otHours($sum),
                        'gross' => Payroll::gross($sum, $e->rate),
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
        $totalPayout = Money::usd($scopedActive->sum(fn ($e) => Payroll::gross($hoursFor($e), $e->rate)));

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
        $teamQrSvg = $qrTeamModel
            ? RealQr::svg(url('/scan/'.$qrTeamModel->id))
            : RealQr::svg(url('/'));

        // ---- daily timesheet (company → team → worker; actual vs paid, reg/OT) ----
        $timesheet = Timesheet::forDate($s['attDate'] ?? now()->format('Y-m-d'), $s['site'], $lang);

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
        $meWh = $hoursFor($me);
        $wReg = Payroll::regHours($meWh);
        $wOt = Payroll::otHours($meWh);
        $wGross = Payroll::gross($meWh, $me->rate);
        $worker = [
            'name' => $me->first.' '.$me->last, 'initials' => $inits($me),
            'teamName' => $teamName($me->team_id), 'teamColor' => $teamColor($me->team_id),
            'company' => $companyName($me->company_id), 'role' => $me->role, 'nat' => $me->nat,
            'empId' => $me->emp_id, 'phone' => $me->phone, 'email' => $me->email, 'issued' => $me->issued,
            'rate' => Money::rate($me->rate), 'reg' => $wReg, 'ot' => $wOt, 'hours' => $meWh,
            'gross' => Money::usd($wGross), 'net' => Money::usd($wGross), 'access' => $L['access_'.$me->access],
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
                    'pid' => $pn->id, 'seedNoLunch' => false,
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
                    'pid' => null,
                    'adjusted' => $c['adjusted'], 'noLunch' => $c['noLunch'], 'seedNoLunch' => $r['noLunch'],
                    'rawNote' => $c['adjusted'] ? $tl('Actual', 'Real', '실제').' '.$c['rawIn'].' – '.$c['rawOut'] : '',
                    'h' => number_format($c['paid'], 1).'h', 'chips' => $chips,
                    'lunchToggleLabel' => $c['noLunch'] ? $L['m_lunchOff'] : $L['m_lunchOn'],
                    'lunchIsNo' => $c['noLunch'],
                ];
            }, $rawPunches);
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
        $rank = ['worker' => 1, 'manager' => 2, 'admin' => 3];
        $access = $s['access'] ?? 'admin';
        $roleLabels = ['admin' => $L['roleAdmin'], 'manager' => $L['roleManager'], 'worker' => $L['roleWorker']];
        $viewSwitch = [];
        foreach (['admin', 'manager', 'worker'] as $r) {
            if (($rank[$r] ?? 0) <= ($rank[$access] ?? 3)) {
                $viewSwitch[] = ['role' => $r, 'label' => $roleLabels[$r], 'active' => $s['role'] === $r];
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
            $deskClock = [
                'show' => true,
                'isIn' => $isIn,
                'statusLabel' => $isIn ? $L['w_status_in'] : $L['w_status_out'],
                'btnLabel' => $isIn ? $L['w_clockout'] : $L['w_clockin'],
                'since' => $isIn && $selfPunch->in_min !== null ? Shift::fmtMin($selfPunch->in_min) : null,
                'sinceWord' => $L['w_since'],
            ];
        }

        // ---- internal comms (announcements · company/crew chat · DM · bell) ----
        $comms = null;
        if ($s['role'] !== 'worker' && $s['screen'] !== 'login') {
            $actor = Employee::find($s['actorId'] ?? null);
            if ($actor) {
                $comms = CommsView::build($actor, $s, $lang, (bool) ($s['canManage'] ?? false));
            }
        }

        return [
            'L' => $L,
            'role' => $s['role'], 'lang' => $lang, 'screen' => $s['screen'],
            'isLogin' => $s['screen'] === 'login',
            'isWorker' => $s['role'] === 'worker',
            'isDesktopApp' => $s['role'] !== 'worker' && $s['screen'] !== 'login',
            'stat_workers' => $activeAll->filter(fn ($e) => $e->type === 'worker')->count(),
            'nav' => $nav, 'mobileTabs' => $mobileTabs,
            'siteOptions' => $siteOptions, 'siteVal' => $s['site'],
            'me' => [
                'name' => $meName,
                'role' => $s['role'] === 'admin' ? $L['roleAdmin'] : $L['roleManager'],
                'initials' => $s['role'] === 'admin' ? 'HK' : 'JP',
                'color' => $s['role'] === 'admin' ? '#E85D2A' : '#3B72E0',
            ],
            'pageTitle' => $L['t_'.$s['screen']] ?? '', 'pageSub' => $L['s_'.$s['screen']] ?? '', 'today' => $L['today'],
            // dashboard
            'dash' => [
                'layout' => $s['dashLayout'], 'cnt' => $cnt, 'totalActive' => $totalActive, 'onsite' => $onsite, 'rate' => $rate,
                'periodPay' => Money::usd($periodPayNum), 'payPeriod' => $periodLabel, 'avgH' => $avgH,
                'ringDash' => (int) round(2 * M_PI * 52 * (1 - $rate / 100)),
                'teamStats' => $teamStats, 'companyStats' => $companyStats, 'recent' => $recent,
            ],
            // employees
            'emp' => [
                'rows' => $empRows, 'sel' => $sel, 'editForm' => $s['editForm'],
                'delName' => $delRaw ? $empName($delRaw) : null, 'termName' => $termRaw ? $empName($termRaw) : null,
                'teamChips' => $scopedTeams->map(fn ($t) => ['id' => $t->id, 'label' => $t->name, 'color' => $t->color, 'active' => $s['teamFilter'] === $t->id])->all(),
                'companyOptions' => $companyOptions, 'teamOptionsAll' => $teamOptionsAll, 'typeOptions' => $typeOptions,
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
            ],
            // badge
            'badge' => [
                'ext' => $ext, 'nfcUid' => $nfcUid, 'nfcId' => $nfcId, 'regEmpId' => $regEmpId, 'faceCrop' => $faceCrop,
                'regTeamOptions' => $regTeamOptions, 'typeOptions' => $typeOptions, 'accColor' => $accColor,
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
                'pdHistory' => $tl('Attendance history', 'Historial de asistencia', '출퇴근 이력'),
                'pdPeriod' => $tl('This pay period', 'Este periodo', '이번 정산기간').' · '.$periodLabel,
                'ruleNote' => $ruleNote,
            ],
            // worker mobile
            'worker' => [
                'me' => $worker, 'punchLog' => $punchLog, 'ruleNote' => $ruleNote,
                'reasonOptions' => $reasonOptions, 'qrSvg' => RealQr::svg(url('/scan/'.$me->team_id)),
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
            'toast' => $s['toast'],
        ];
    }
}
