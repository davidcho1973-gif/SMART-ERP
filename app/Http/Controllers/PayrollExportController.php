<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Punch;
use App\Support\Access;
use App\Support\Payroll;
use App\Support\Shift;
use App\Support\Xlsx;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Bi-weekly payroll register export.
 *
 * Recreates the local-worker timesheet → payroll sheet: one row per person, a
 * column per day in the settlement period, then Reg / OT / Rate / Gross carried
 * by live spreadsheet formulas. Overtime is weekly — hours beyond 40 in any
 * 7-day block pay at 1.5×. Salaried people (Koreans, managers, site managers)
 * appear for hour-tracking only; their pay column reads "salaried" rather than a
 * calculated amount.
 */
class PayrollExportController extends Controller
{
    private const WEEKLY_REG_CAP = 40;

    private const OT_MULTIPLIER = 1.5;

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

        $lang = in_array($request->query('lang'), ['en', 'es', 'ko'], true) ? $request->query('lang') : 'en';
        $L = (array) trans('app', [], $lang);

        [$defStart, $defEnd] = Payroll::currentPeriod();
        $start = $this->ymd($request->query('start'), $defStart);
        $end = $this->ymd($request->query('end'), $defEnd);
        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }

        // build the day list (cap defensively so a bad range can't blow up the sheet)
        $days = [];
        $cursor = Carbon::parse($start);
        $last = Carbon::parse($end);
        while ($cursor->lte($last) && count($days) < 31) {
            $days[] = $cursor->copy();
            $cursor->addDay();
        }
        $weeks = array_chunk($days, 7);

        $employees = $this->recipients($request->query('recipient', 'hourly'), $scopeSite);

        // paid hours per employee per Y-m-d, from completed punches in the range
        $hoursByEmpDay = $this->paidHours($employees->pluck('id')->all(), $start, $end);

        $rows = $this->buildRows($employees, $weeks, $hoursByEmpDay, $L);

        $periodLabel = Carbon::parse($start)->format('M j').' – '.Carbon::parse($end)->format('M j, Y');
        $xlsx = Xlsx::build([[
            'name' => $L['p_shSheet'] ?? 'Payroll',
            'rows' => $rows,
        ]]);

        $filename = 'payroll_'.$start.'_'.$end.'.xlsx';

        return response($xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
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
    private function paidHours(array $empIds, string $start, string $end): array
    {
        if ($empIds === []) {
            return [];
        }
        $punches = Punch::whereIn('employee_id', $empIds)
            ->whereBetween('work_date', [$start, $end])
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

    /**
     * Assemble the full sheet: title, period, header, one row per worker (with
     * live SUM / MIN / MAX / pay formulas), and a grand-total row.
     */
    private function buildRows($employees, array $weeks, array $hoursByEmpDay, array $L): array
    {
        $payTypeLabel = [
            'salary' => $L['b_ptSalary'] ?? 'Salaried',
            'hourly' => $L['b_ptHourly'] ?? 'Hourly',
            'both' => $L['b_ptBoth'] ?? 'Salary + hourly',
        ];

        // ---- column map ----
        // 0:# 1:Name 2:Company 3:Crew 4:PayType, then per week [days..][week total], then Reg OT Rate Gross
        $lead = ['#', $L['ts_name'], $L['ts_company'], $L['ts_team'], $L['b_payType'] ?? 'Pay type'];
        $header = $lead;
        $dayCols = [];      // dayCols[w] = [colIndex,...]
        $weekTotalCol = []; // weekTotalCol[w] = colIndex
        $col = count($lead);
        foreach ($weeks as $w => $days) {
            $dayCols[$w] = [];
            foreach ($days as $d) {
                $header[] = $d->format('M j').' ('.$d->format('D').')';
                $dayCols[$w][] = $col++;
            }
            $header[] = ($L['p_shWeek'] ?? 'Wk').($w + 1);
            $weekTotalCol[$w] = $col++;
        }
        $regCol = $col++;
        $otCol = $col++;
        $rateCol = $col++;
        $grossCol = $col++;
        $header[] = $L['ts_reg'];
        $header[] = $L['ts_ot'];
        $header[] = $L['p_rate'];
        $header[] = $L['p_gross'];

        $c = fn (int $i, int $rowNum) => Xlsx::colLetter($i).$rowNum;

        // ---- title + period + header ----
        $periodStart = $weeks[0][0] ?? null;
        $lastWeek = end($weeks);
        $periodEnd = $lastWeek ? end($lastWeek) : null;
        $periodTxt = $periodStart && $periodEnd
            ? $periodStart->format('M j').' – '.$periodEnd->format('M j, Y')
            : '';

        $out = [];
        $out[] = ['NAHSHON MEP · '.($L['p_shTitle'] ?? 'Bi-Weekly Payroll')];
        $out[] = [($L['p_period'] ?? 'Period').': '.$periodTxt];
        $out[] = [];
        $out[] = $header;

        $firstDataRow = count($out) + 1; // 1-based sheet row of first worker
        $n = 0;
        foreach ($employees as $e) {
            $rowNum = count($out) + 1;
            $n++;
            $row = [
                $n,
                trim($e->first.' '.$e->last) !== '' ? trim($e->first.' '.$e->last) : $e->emp_id,
                $e->company_id ?? '',
                $e->team_id ?? '',
                $payTypeLabel[$e->pay_type] ?? ($e->pay_type ?? ''),
            ];

            $weekTotals = [];
            foreach ($weeks as $w => $days) {
                $sum = 0.0;
                foreach ($days as $i => $d) {
                    $h = $hoursByEmpDay[$e->id][$d->format('Y-m-d')] ?? null;
                    $row[$dayCols[$w][$i]] = $h !== null ? $h : '';
                    $sum += (float) ($h ?? 0);
                }
                $weekTotals[$w] = round($sum, 2);
                $firstDay = $c($dayCols[$w][0], $rowNum);
                $lastDay = $c($dayCols[$w][count($days) - 1], $rowNum);
                $row[$weekTotalCol[$w]] = ['f' => "SUM($firstDay:$lastDay)", 'v' => round($sum, 2)];
            }

            // Reg = Σ MIN(week,40) ; OT = Σ MAX(week-40,0)
            $regParts = [];
            $otParts = [];
            $regVal = 0.0;
            $otVal = 0.0;
            foreach ($weeks as $w => $days) {
                $ref = $c($weekTotalCol[$w], $rowNum);
                $regParts[] = "MIN($ref,".self::WEEKLY_REG_CAP.')';
                $otParts[] = "MAX($ref-".self::WEEKLY_REG_CAP.',0)';
                $regVal += min($weekTotals[$w], self::WEEKLY_REG_CAP);
                $otVal += max($weekTotals[$w] - self::WEEKLY_REG_CAP, 0);
            }
            $row[$regCol] = ['f' => implode('+', $regParts), 'v' => round($regVal, 2)];
            $row[$otCol] = ['f' => implode('+', $otParts), 'v' => round($otVal, 2)];
            $row[$rateCol] = (float) $e->rate;

            if ($e->isHourlyPaid()) {
                $regRef = $c($regCol, $rowNum);
                $otRef = $c($otCol, $rowNum);
                $rateRef = $c($rateCol, $rowNum);
                $grossVal = $regVal * (float) $e->rate + $otVal * (float) $e->rate * self::OT_MULTIPLIER;
                $row[$grossCol] = [
                    'f' => "$regRef*$rateRef+$otRef*$rateRef*".self::OT_MULTIPLIER,
                    'v' => round($grossVal, 2),
                ];
            } else {
                $row[$grossCol] = $payTypeLabel['salary'];
            }

            // pad any gaps so cells land in the right columns
            for ($i = 0; $i <= $grossCol; $i++) {
                $row[$i] ??= '';
            }
            ksort($row);
            $out[] = array_values($row);
        }

        // ---- grand total ----
        $lastDataRow = count($out);
        if ($n > 0) {
            $total = array_fill(0, $grossCol + 1, '');
            $total[1] = $L['ts_totals'] ?? 'Totals';
            $regRange = $c($regCol, $firstDataRow).':'.$c($regCol, $lastDataRow);
            $otRange = $c($otCol, $firstDataRow).':'.$c($otCol, $lastDataRow);
            $total[$regCol] = ['f' => "SUM($regRange)"];
            $total[$otCol] = ['f' => "SUM($otRange)"];
            $total[$grossCol] = ['f' => 'SUM('.$c($grossCol, $firstDataRow).':'.$c($grossCol, $lastDataRow).')'];
            $out[] = array_values($total);
        }

        return $out;
    }

    private function ymd(mixed $v, string $fallback): string
    {
        return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : $fallback;
    }
}
