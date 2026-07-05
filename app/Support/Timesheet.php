<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Punch;
use App\Models\Site;
use App\Models\Team;
use Illuminate\Support\Carbon;

/**
 * Daily attendance timesheet — company · crew · worker rows with actual vs
 * paid punch times and per-day regular / overtime hours. Shared by the
 * on-screen table (ViewModel) and the Excel export.
 */
class Timesheet
{
    /**
     * @return array{rows:array<int,array>, count:int, present:int, regTotal:string, otTotal:string, regNum:float, otNum:float, date:string, dateLabel:string}
     */
    public static function forDate(string $date, string $siteId, string $lang): array
    {
        $teams = Team::all()->keyBy('id');
        $companies = Company::all()->keyBy('id');
        $sites = Site::all()->keyBy('id');
        $teamName = fn ($tid) => optional($teams->get($tid))->name ?? '—';
        $companyName = fn ($cid) => optional($companies->get($cid))->name ?? '—';
        $teamColor = fn ($tid) => optional($teams->get($tid))->color ?? '#9AA0A6';

        $isToday = $date === now()->format('Y-m-d');
        $isSat = Carbon::parse($date)->isSaturday();
        $dayPunches = Punch::where('work_date', $date)->get()->keyBy('employee_id');

        // everyone active on the roster — workers and managers/staff who clock in
        $workers = Employee::where('emp', 'active')
            ->when($siteId !== 'all', fn ($q) => $q->where('site_id', $siteId))
            ->get()
            ->sortBy(fn ($e) => sprintf('%s|%s|%s', $companyName($e->company_id), $teamName($e->team_id), $e->displayName($lang)))
            ->values();

        $rows = [];
        $regTotal = 0.0;
        $otTotal = 0.0;
        $present = 0;

        foreach ($workers as $e) {
            $p = $dayPunches->get($e->id);
            $inMin = $outMin = null;
            $noLunch = false;
            if ($p && $p->in_min !== null) {
                $inMin = $p->in_min;
                $outMin = $p->out_min;
                $noLunch = (bool) $p->no_lunch;
            } elseif ($isToday && $e->in_t !== '—' && $e->in_t !== '') {
                $inMin = Shift::minOf($e->in_t);
                $outMin = ($e->out_t !== '—' && $e->out_t !== '') ? Shift::minOf($e->out_t) : null;
            }

            $actIn = $inMin !== null ? Shift::fmtMin($inMin) : '—';
            $actOut = $outMin !== null ? Shift::fmtMin($outMin) : '—';
            $paidIn = $paidOut = $regStr = $otStr = '—';
            $regNum = $otNum = 0.0;
            if ($inMin !== null && $outMin !== null) {
                [$si, $so] = Payroll::scheduleFor($inMin, $isSat);
                $c = Shift::compute(Shift::fmtMin($inMin), Shift::fmtMin($outMin), $si, $so, $noLunch);
                $paidIn = $c['inFmt'];
                $paidOut = $c['outFmt'];
                $paid = max(0, $c['paid']);
                $regNum = min($paid, 8);
                $otNum = max(0, $paid - 8);
                $regTotal += $regNum;
                $otTotal += $otNum;
                $regStr = number_format($regNum, 1).'h';
                $otStr = $otNum > 0.05 ? number_format($otNum, 1).'h' : '—';
            }
            if ($inMin !== null) {
                $present++;
            }

            // off-site flag: any punch leg recorded outside the site geofence.
            // Distance is recomputed from stored coords for the reviewer badge.
            [$geoOff, $geoDist] = self::geoReview($p, $sites->get($e->site_id));

            $rows[] = [
                'company' => $companyName($e->company_id),
                'team' => $teamName($e->team_id),
                'teamColor' => $teamColor($e->team_id),
                'name' => $e->displayName($lang), 'initials' => $e->initials(),
                'actIn' => $actIn, 'actOut' => $actOut,
                'paidIn' => $paidIn, 'paidOut' => $paidOut,
                'reg' => $regStr, 'ot' => $otStr,
                'regNum' => round($regNum, 2), 'otNum' => round($otNum, 2),
                'onDuty' => $inMin !== null && $outMin === null,
                'geoOff' => $geoOff, 'geoDist' => $geoDist,
            ];
        }

        return self::totals($date, $rows, $regTotal, $otTotal, $present);
    }

    /**
     * Off-site review for a punch: was any leg recorded outside the site geofence?
     *
     * @return array{0: bool, 1: int|null} [off-site flag, worst distance in metres]
     */
    protected static function geoReview(?Punch $p, ?Site $site): array
    {
        if (! $p) {
            return [false, null];
        }
        $off = false;
        $dist = null;
        foreach ([['in_geo_ok', 'in_lat', 'in_lng'], ['out_geo_ok', 'out_lat', 'out_lng']] as [$okC, $latC, $lngC]) {
            if ($p->$okC === false) {
                $off = true;
                if ($site && $site->lat !== null && $p->$latC !== null && $p->$lngC !== null) {
                    $d = Geo::distanceMeters((float) $site->lat, (float) $site->lng, (float) $p->$latC, (float) $p->$lngC);
                    $dist = $dist === null ? $d : max($dist, $d);
                }
            }
        }

        return [$off, $dist !== null ? (int) round($dist) : null];
    }

    /** Assemble the timesheet return payload. */
    protected static function totals(string $date, array $rows, float $regTotal, float $otTotal, int $present): array
    {
        return [
            'date' => $date,
            'dateLabel' => Carbon::parse($date)->format('D · M j, Y'),
            'rows' => $rows,
            'count' => count($rows),
            'present' => $present,
            'regTotal' => number_format($regTotal, 1).'h',
            'otTotal' => number_format($otTotal, 1).'h',
            'regNum' => round($regTotal, 2),
            'otNum' => round($otTotal, 2),
        ];
    }
}
