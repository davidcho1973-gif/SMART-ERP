<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Punch;
use App\Models\Site;
use App\Support\Access;
use App\Support\Payroll;
use App\Support\PayrollXlsx;
use App\Support\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Bi-weekly payroll register export, reproducing the NAHSHON MEP timesheet →
 * payroll workbook (same colors/fonts/layout/logo, landscape one-page print).
 *
 * One row per person, a column per calendar day (Sun–Sat weeks covering the
 * settlement period), live formulas: week total = SUM(days), weekly regular
 * capped at 40h (MIN), overtime beyond 40h/week (MAX) paid at 1.5×. Salaried
 * people (Koreans, managers) are listed for hour-tracking only — their rate
 * cell stays empty so every amount renders as "$ -".
 */
class PayrollExportController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(config('workforce.demo') || Auth::check(), 403);

        // capability gate + hard site scope for site managers (mirrors timesheet export)
        $scopeSite = null;
        if (! config('workforce.demo') && Auth::check()) {
            $role = Access::canonical(Auth::user()->access);
            abort_unless(Access::allows([$role], 'payroll.export'), 403);
            if ($role === 'site_manager' && Auth::user()->employee_id) {
                $scopeSite = Employee::find(Auth::user()->employee_id)?->site_id;
            }
        }

        [$defStart, $defEnd] = Payroll::currentPeriod();
        $start = $this->ymd($request->query('start'), $defStart);
        $end = $this->ymd($request->query('end'), $defEnd);
        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }

        // the UI's site filter narrows the register and names the project header
        $siteParam = is_string($request->query('site')) ? $request->query('site') : 'all';
        if ($scopeSite !== null) {
            $siteParam = $scopeSite;   // hard scope beats the URL
        }

        $weeks = $this->calendarWeeks($start, $end);
        $employees = $this->recipients($request->query('recipient', 'hourly'), $siteParam !== 'all' ? $siteParam : null);
        $hoursByEmpDay = $this->paidHours($employees->pluck('id')->all(), $weeks);

        $companyNames = Company::pluck('name', 'id');
        $workers = $employees->map(function ($e) use ($hoursByEmpDay, $companyNames) {
            $co = (string) ($companyNames[$e->company_id] ?? '');
            return [
                // NAHSHON's own people are marked 직영 (direct hire), like the original sheet
                'tag' => ($co === '' || str_contains(strtoupper($co), 'NAHSHON')) ? '직영' : $co,
                'name' => trim($e->first.' '.$e->last) !== '' ? trim($e->first.' '.$e->last) : $e->emp_id,
                'position' => $e->role !== '' ? $e->role : ($e->isManager() ? 'Manager' : 'Worker'),
                'rate' => $e->isHourlyPaid() ? (float) $e->rate : null,
                'hours' => $hoursByEmpDay[$e->id] ?? [],
            ];
        })->values()->all();

        $project = $siteParam !== 'all'
            ? strtoupper((string) (Site::find($siteParam)?->name ?? 'ALL PROJECTS'))
            : 'ALL PROJECTS';

        $s = Carbon::parse($start);
        $e = Carbon::parse($end);
        $logoPath = resource_path('xlsx/nahshon_logo.png');

        $xlsx = PayrollXlsx::build([
            'sheetName' => $s->format('n.j').'-'.$e->format('n.j'),
            'project' => $project,
            'rangeLabel' => $s->format('n/j/y').'-'.$e->format('n/j/y'),
            'companyLine' => "NAHSHON MEP LLC\n1934 TRINITY CHASE DR.\nDACULA, GA 30019",
            'weeks' => $weeks,
            'workers' => $workers,
            'bankInfo' => [
                'PAYMENT BANK INFORMATION',
                'Beneficiary: NAHSHON MEP LLC',
                'Address: 1934 TRINITY CHASE DR',
                'DACULA GA 30019',
                'Contact Person: MUN SUP SHIN',
                'Contact Number: +1 678 343 7510',
                'BANK INFO: BANK OF AMERICA',
                'ACCOUNT# 334080507882',
                'ROUTING#: 061000052',
                'ROUTING# FOR WIRE: 026009593',
                'BANK ADDRESS: 3542 SATELLITE BLVD. DULUTH, GA 30096',
                'TEL: 770.497.3100',
            ],
            'logo' => is_file($logoPath) ? (string) file_get_contents($logoPath) : null,
        ]);

        $filename = 'payroll_'.$start.'_'.$end.'.xlsx';

        return response($xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Sun–Sat calendar weeks covering the period (like the original sheet, whose
     * grid starts on the Sunday before the period). Capped at 4 weeks.
     *
     * @return array<int,array{month:string,monthEnd:string,days:array<int,array{num:int,dow:string,date:string}>}>
     */
    private function calendarWeeks(string $start, string $end): array
    {
        $gridStart = Carbon::parse($start)->startOfWeek(Carbon::SUNDAY);
        $last = Carbon::parse($end);
        $weeks = [];
        $cursor = $gridStart->copy();
        while ($cursor->lte($last) && count($weeks) < 4) {
            $days = [];
            for ($i = 0; $i < 7; $i++) {
                $days[] = [
                    'num' => (int) $cursor->format('j'),
                    'dow' => $cursor->format('D'),
                    'date' => $cursor->format('Y-m-d'),
                ];
                $cursor->addDay();
            }
            $weeks[] = [
                'month' => strtoupper($days[0]['date'] ? Carbon::parse($days[0]['date'])->format('F') : ''),
                'monthEnd' => strtoupper(Carbon::parse($days[6]['date'])->format('F')),
                'days' => $days,
            ];
        }

        return $weeks;
    }

    /** @return \Illuminate\Support\Collection<int,Employee> */
    private function recipients(?string $recipient, ?string $scopeSite)
    {
        $q = Employee::query()->where('emp', 'active');

        $recipient = is_string($recipient) ? $recipient : 'hourly';
        if ($recipient === 'hourly') {
            $q->whereIn('pay_type', ['hourly', 'both']);
        } elseif ($recipient === 'salary') {
            $q->where('pay_type', 'salary');
        } elseif (str_starts_with($recipient, 'co:')) {
            $q->where('company_id', substr($recipient, 3));
        } elseif (str_starts_with($recipient, 'tm:')) {
            $q->where('team_id', substr($recipient, 3));
        }
        // 'all' → no extra filter

        if ($scopeSite !== null) {
            $q->where('site_id', $scopeSite);
        }

        return $q->orderBy('company_id')->orderBy('team_id')->orderBy('last')->get();
    }

    /** @return array<int,array<string,float>> [empId][Y-m-d] = paid hours */
    private function paidHours(array $empIds, array $weeks): array
    {
        if ($empIds === [] || $weeks === []) {
            return [];
        }
        $first = $weeks[0]['days'][0]['date'];
        $lastWeek = end($weeks);
        $last = $lastWeek['days'][6]['date'];

        $punches = Punch::whereIn('employee_id', $empIds)
            ->whereBetween('work_date', [$first, $last])
            ->whereNotNull('in_min')->whereNotNull('out_min')
            ->get();

        $out = [];
        foreach ($punches as $p) {
            $date = Carbon::parse($p->work_date);
            [$si, $so] = Payroll::scheduleFor($p->in_min, $date->isSaturday());
            $paid = max(0.0, Shift::compute(Shift::fmtMin($p->in_min), Shift::fmtMin($p->out_min), $si, $so, $p->no_lunch)['paid']);
            $key = $date->format('Y-m-d');
            $out[$p->employee_id][$key] = round(($out[$p->employee_id][$key] ?? 0) + $paid, 2);
        }

        return $out;
    }

    private function ymd(mixed $v, string $fallback): string
    {
        return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : $fallback;
    }
}
