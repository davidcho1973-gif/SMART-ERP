<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Site;
use App\Models\Team;

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
            ? ['dashboard', 'projects', 'employees', 'badge', 'attendance']
            : ['dashboard', 'projects', 'employees', 'badge', 'attendance', 'payroll'];
        $nav = array_map(fn ($k) => [
            'key' => $k, 'label' => $L['nav_' . $k], 'active' => $s['screen'] === $k,
        ], $navKeys);

        $mKeys = ['home', 'work', 'pay', 'me'];
        $mobileTabs = array_map(fn ($k) => [
            'key' => $k, 'label' => $L['w_tab_' . $k], 'active' => $s['mobileTab'] === $k,
        ], $mKeys);

        $siteOptions = array_merge(
            [['id' => 'all', 'label' => $L['allSites']]],
            $sites->map(fn ($st) => ['id' => $st->id, 'label' => $st->name . ' · ' . $st->city])->all()
        );

        // ---- dashboard stats ----
        $cnt = ['present' => 0, 'late' => 0, 'absent' => 0, 'off' => 0];
        foreach ($scopedActive as $e) {
            $cnt[$e->status]++;
        }
        $totalActive = $scopedActive->count();
        $onsite = $cnt['present'] + $cnt['late'];
        $rate = $totalActive ? (int) round($onsite / $totalActive * 100) : 0;
        $periodPayNum = $scopedActive->sum(fn ($e) => Payroll::gross($e->wh, $e->rate));
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
                'id' => $t->id, 'name' => $t->name, 'company' => $companyName($t->company_id), 'color' => $t->color,
                'total' => $list->count(), 'present' => $pres,
                'pct' => $list->count() ? (int) round($pres / $list->count() * 100) : 0,
            ];
        })->values()->all();

        $recent = [
            ['txt' => $tl('Carlos Martínez clocked in', 'Carlos Martínez marcó entrada', 'Carlos Martínez 출근 처리'), 'tag' => '6:52', 'c' => '#1F9D6B'],
            ['txt' => $tl('Miguel Torres late', 'Miguel Torres llegó tarde', 'Miguel Torres 지각'), 'tag' => '7:18', 'c' => '#E8A33D'],
            ['txt' => $tl('New badge: A. Vargas', 'Nueva credencial: A. Vargas', '신규 베지 등록: A. Vargas'), 'tag' => $lang === 'ko' ? '등록' : 'new', 'c' => '#3B72E0'],
            ['txt' => $tl('Antonio Díaz terminated', 'Antonio Díaz dado de baja', 'Antonio Díaz 퇴사 처리'), 'tag' => $lang === 'ko' ? '퇴사' : 'exit', 'c' => '#D9483B'],
        ];

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
            $hay = strtolower($e->first . ' ' . $e->last . ' ' . ($e->ko ?? '') . ' ' . $e->role . ' ' . $e->emp_id . ' ' . $e->nat);
            return $okTeam && ($q === '' || str_contains($hay, $q));
        })->values();

        $mapEmp = fn (Employee $e) => [
            'id' => $e->id, 'name' => $empName($e), 'initials' => $inits($e), 'empId' => $e->emp_id,
            'teamName' => $teamName($e->team_id), 'teamColor' => $teamColor($e->team_id), 'companyName' => $companyName($e->company_id),
            'role' => $e->role, 'rate' => $e->rate,
            'statusLabel' => $L['st_' . $e->status], 'statusColor' => $stColor[$e->status], 'statusBg' => $stBg[$e->status],
            'typeLabel' => $e->type === 'manager' ? $L['e_manager'] : $L['e_worker'],
            'access' => $e->access, 'accessLabel' => $L['access_' . $e->access], 'accessColor' => $accColor[$e->access],
            'isTerminated' => $e->emp === 'terminated', 'isActive' => $e->emp === 'active',
            'rowOpacity' => $e->emp === 'terminated' ? '0.55' : '1',
            'inT' => $e->in_t, 'term' => $e->term,
        ];
        $empRows = $filtered->map($mapEmp)->all();

        $selRaw = $employees->firstWhere('id', $s['selectedEmp']);
        $sel = $selRaw ? $mapEmp($selRaw) : null;
        $delRaw = $employees->firstWhere('id', $s['deleteId']);
        $termRaw = $employees->firstWhere('id', $s['terminateId']);

        $managers = $employees->filter(fn ($e) => $e->type === 'manager' && $e->emp === 'active')->values();
        $managerOptions = $managers->map(fn ($m) => ['id' => $m->id, 'label' => $empName($m)])->all();
        $companyOptions = $companies->map(fn ($c) => ['id' => $c->id, 'label' => $c->name])->all();
        $teamOptionsAll = $teams->map(fn ($t) => ['id' => $t->id, 'label' => $t->name . ' · ' . $companyName($t->company_id)])->all();
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
        $regEmpId = $s['scanN'] === 'done' ? $nfcId : 'N- — — —';
        $regTeamOptions = $teams->map(fn ($t) => ['id' => $t->id, 'label' => $t->name . ' · ' . $companyName($t->company_id)])->all();

        // ---- payroll ----
        $payRows = $scopedActive->map(function ($e) use ($empName, $inits, $teamName, $teamColor) {
            $reg = Payroll::regHours($e->wh);
            $ot = Payroll::otHours($e->wh);
            $gross = Payroll::gross($e->wh, $e->rate);
            return [
                'id' => $e->id, 'name' => $empName($e), 'initials' => $inits($e),
                'teamName' => $teamName($e->team_id), 'teamColor' => $teamColor($e->team_id),
                'rate' => Money::rate($e->rate), 'reg' => (string) $reg, 'ot' => (string) $ot,
                'gross' => Money::usd($gross), 'net' => Money::usd($gross),
            ];
        })->all();

        $payDetailData = null;
        $voucher = null;
        if ($s['payDetail'] !== null) {
            $e = $activeAll->firstWhere('id', $s['payDetail']) ?? $employees->firstWhere('id', $s['payDetail']);
            if ($e) {
                $h = Payroll::history($e->wh, $e->id, $e->rate, $tl);
                $payDetailData = [
                    'id' => $e->id, 'name' => $empName($e), 'initials' => $inits($e),
                    'teamName' => $teamName($e->team_id), 'teamColor' => $teamColor($e->team_id), 'role' => $e->role,
                    'rate' => Money::rate($e->rate) . '/hr', 'days' => $h['days'],
                    'reg' => $h['reg'] . 'h', 'ot' => $h['ot'] . 'h', 'gross' => Money::usd($h['gross']),
                ];
                $voucher = [
                    'name' => $payDetailData['name'], 'teamName' => $payDetailData['teamName'], 'role' => $e->role,
                    'reg' => $payDetailData['reg'], 'ot' => $payDetailData['ot'], 'gross' => $payDetailData['gross'],
                    'rate' => $payDetailData['rate'], 'empId' => $e->emp_id,
                    'checkNo' => $s['checkNo'], 'payDate' => $s['payDate'],
                ];
            }
        }
        $totalPayout = Money::usd($scopedActive->sum(fn ($e) => Payroll::gross($e->wh, $e->rate)));

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
            ? Qr::pattern(Qr::seedFor($qrTeamModel->id, $qrTeamModel->company_id, $qrTeamModel->name))
            : Qr::pattern(11);

        // ---- worker mobile (me = employee 106) ----
        $me = $employees->firstWhere('id', 106) ?? $employees->get(5);
        $wReg = Payroll::regHours($me->wh);
        $wOt = Payroll::otHours($me->wh);
        $wGross = Payroll::gross($me->wh, $me->rate);
        $worker = [
            'name' => $me->first . ' ' . $me->last, 'initials' => $inits($me),
            'teamName' => $teamName($me->team_id), 'teamColor' => $teamColor($me->team_id),
            'company' => $companyName($me->company_id), 'role' => $me->role, 'nat' => $me->nat,
            'empId' => $me->emp_id, 'phone' => $me->phone, 'email' => $me->email, 'issued' => $me->issued,
            'rate' => Money::rate($me->rate), 'reg' => $wReg, 'ot' => $wOt, 'hours' => $me->wh,
            'gross' => Money::usd($wGross), 'net' => Money::usd($wGross), 'access' => $L['access_' . $me->access],
        ];

        $rawPunches = [
            ['d' => 'Jun 30', 'dow' => 'Mon', 'in' => '5:53 AM', 'out' => '2:48 PM', 'si' => 360, 'so' => 900, 'noLunch' => false],
            ['d' => 'Jun 28', 'dow' => 'Sat', 'in' => '6:58 AM', 'out' => '1:02 PM', 'si' => 420, 'so' => 840, 'noLunch' => true],
            ['d' => 'Jun 27', 'dow' => 'Fri', 'in' => '6:02 AM', 'out' => '5:12 PM', 'si' => 360, 'so' => 900, 'noLunch' => false],
            ['d' => 'Jun 26', 'dow' => 'Thu', 'in' => '7:06 AM', 'out' => '4:04 PM', 'si' => 420, 'so' => 960, 'noLunch' => false],
        ];
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
                'adjusted' => $c['adjusted'], 'noLunch' => $c['noLunch'], 'seedNoLunch' => $r['noLunch'],
                'rawNote' => $c['adjusted'] ? $tl('Actual', 'Real', '실제') . ' ' . $c['rawIn'] . ' – ' . $c['rawOut'] : '',
                'h' => number_format($c['paid'], 1) . 'h', 'chips' => $chips,
                'lunchToggleLabel' => $c['noLunch'] ? $L['m_lunchOff'] : $L['m_lunchOn'],
                'lunchIsNo' => $c['noLunch'],
            ];
        }, $rawPunches);

        $ruleNote = $tl(
            'Reg 6–3 / 7–4 · 1h unpaid lunch · OT 1.5× over 40h/wk',
            'Reg 6–3 / 7–4 · almuerzo 1h no pagado · Extra 1.5× tras 40h/sem',
            '정규 6–3 / 7–4 · 점심 1h 무급 · 주 40h 초과 OT 1.5×'
        );
        $reasonOptions = array_merge(
            array_map(fn ($r) => ['value' => $r, 'label' => $r], $L['w_reasons']),
            [['value' => '__custom__', 'label' => $L['w_earlyOther']]]
        );

        $meName = $s['role'] === 'admin' ? ($lang === 'ko' ? '김현수' : 'Hyunsoo Kim') : ($lang === 'ko' ? '박정우' : 'Jungwoo Park');

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
            'pageTitle' => $L['t_' . $s['screen']] ?? '', 'pageSub' => $L['s_' . $s['screen']] ?? '', 'today' => $L['today'],
            // dashboard
            'dash' => [
                'layout' => $s['dashLayout'], 'cnt' => $cnt, 'totalActive' => $totalActive, 'onsite' => $onsite, 'rate' => $rate,
                'periodPay' => Money::usd($periodPayNum), 'payPeriod' => 'Jun 15 – 28, 2026', 'avgH' => $avgH,
                'ringDash' => (int) round(2 * M_PI * 52 * (1 - $rate / 100)),
                'teamStats' => $teamStats, 'recent' => $recent,
            ],
            // employees
            'emp' => [
                'rows' => $empRows, 'sel' => $sel, 'editForm' => $s['editForm'],
                'delName' => $delRaw ? $empName($delRaw) : null, 'termName' => $termRaw ? $empName($termRaw) : null,
                'teamChips' => $scopedTeams->map(fn ($t) => ['id' => $t->id, 'label' => $t->name, 'color' => $t->color, 'active' => $s['teamFilter'] === $t->id])->all(),
                'companyOptions' => $companyOptions, 'teamOptionsAll' => $teamOptionsAll, 'typeOptions' => $typeOptions,
                'accColor' => $accColor,
            ],
            // projects
            'projects' => [
                'companyCards' => $companyCards,
                'teamModalCo' => $s['teamModal'] ? $companyName($s['teamModal']) : '',
                'teamLeadOptions' => $managerOptions,
            ],
            // badge
            'badge' => [
                'ext' => $ext, 'nfcUid' => $nfcUid, 'nfcId' => $nfcId, 'regEmpId' => $regEmpId,
                'regTeamOptions' => $regTeamOptions, 'typeOptions' => $typeOptions, 'accColor' => $accColor,
            ],
            // attendance
            'att' => [
                'qrManualRows' => $qrManualRows, 'qrGroups' => $qrGroups, 'selQr' => $selQr,
                'teamQrSvg' => $teamQrSvg, 'leadWord' => $L['pj_lead'],
            ],
            // payroll
            'pay' => [
                'rows' => $payRows, 'totalPayout' => $totalPayout, 'detail' => $payDetailData, 'voucher' => $voucher,
                'companyName' => 'NAHSHON MEP',
                'pdHistory' => $tl('Attendance history', 'Historial de asistencia', '출퇴근 이력'),
                'pdPeriod' => $tl('This pay period · Jun 15–28', 'Este periodo · Jun 15–28', '이번 정산기간 · Jun 15–28'),
                'ruleNote' => $ruleNote,
            ],
            // worker mobile
            'worker' => [
                'me' => $worker, 'punchLog' => $punchLog, 'ruleNote' => $ruleNote,
                'reasonOptions' => $reasonOptions, 'qrSvg' => Qr::pattern(11),
            ],
            'qrPrint' => ['company' => $selQr['company'], 'team' => $selQr['team'], 'lead' => $selQr['lead'], 'svg' => $teamQrSvg, 'leadWord' => $L['pj_lead']],
            'toast' => $s['toast'],
        ];
    }
}
