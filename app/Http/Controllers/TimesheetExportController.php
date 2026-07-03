<?php

namespace App\Http\Controllers;

use App\Support\Timesheet;
use App\Support\Xlsx;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TimesheetExportController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(config('workforce.demo') || Auth::check(), 403);

        $date = $request->query('date');
        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = now()->format('Y-m-d');
        }
        $site = is_string($request->query('site')) ? $request->query('site') : 'all';
        $lang = in_array($request->query('lang'), ['en', 'es', 'ko'], true) ? $request->query('lang') : 'en';
        $L = (array) trans('app', [], $lang);

        $ts = Timesheet::forDate($date, $site, $lang);

        // Sheet 1 — detail (one row per worker)
        $detail = [[
            $L['ts_company'], $L['ts_team'], $L['ts_name'],
            $L['ts_actIn'], $L['ts_actOut'], $L['ts_paidIn'], $L['ts_paidOut'],
            $L['ts_reg'], $L['ts_ot'],
        ]];
        foreach ($ts['rows'] as $r) {
            $detail[] = [
                $r['company'], $r['team'], $r['name'],
                $r['actIn'], $r['onDuty'] ? $L['ts_onduty'] : $r['actOut'],
                $r['paidIn'], $r['paidOut'],
                number_format($r['regNum'], 1), number_format($r['otNum'], 1),
            ];
        }

        // Sheet 2 — summary by company · crew
        $groups = [];
        foreach ($ts['rows'] as $r) {
            $key = $r['company'] . '||' . $r['team'];
            $groups[$key] ??= ['company' => $r['company'], 'team' => $r['team'], 'workers' => 0, 'reg' => 0.0, 'ot' => 0.0];
            $groups[$key]['workers']++;
            $groups[$key]['reg'] += $r['regNum'];
            $groups[$key]['ot'] += $r['otNum'];
        }
        $summary = [[$L['ts_company'], $L['ts_team'], $L['ts_workers'], $L['ts_reg'], $L['ts_ot']]];
        foreach ($groups as $g) {
            $summary[] = [$g['company'], $g['team'], (string) $g['workers'], number_format($g['reg'], 1), number_format($g['ot'], 1)];
        }
        $summary[] = [$L['ts_totals'], '', (string) $ts['count'], number_format($ts['regNum'], 1), number_format($ts['otNum'], 1)];

        $xlsx = Xlsx::build([
            ['name' => $L['ts_records'], 'rows' => $detail],
            ['name' => $L['ts_totals'], 'rows' => $summary],
        ]);

        $filename = 'attendance_' . $date . '.xlsx';

        return response($xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
