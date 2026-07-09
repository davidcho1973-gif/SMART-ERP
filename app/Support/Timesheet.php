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
        Attendance::warmTeams($teams);   // settle() below must not Team::find per worker
        $teamName = fn ($tid) => optional($teams->get($tid))->name ?? '—';
        $companyName = fn ($cid) => optional($companies->get($cid))->name ?? '—';
        $teamColor = fn ($tid) => optional($teams->get($tid))->color ?? '#9AA0A6';

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
            $realPunch = $dayPunches->get($e->id);
            // the punch table is the single source of truth. A live clock-in always
            // saves a punch for the day, so we never synthesize attendance from the
            // denormalized live-status fields (in_t/status) — those persist across
            // days and would resurrect a stale clock-in after a rollover or after a
            // past-date punch is voided (dashboard read punches, timesheet didn't).
            $p = ($realPunch && $realPunch->in_min !== null) ? $realPunch : null;
            $hasIn = $p !== null && $p->in_min !== null;
            $settled = ($p !== null && $p->in_min !== null && $p->out_min !== null) ? Attendance::settle($p) : null;

            $actIn = $hasIn ? Shift::fmtMin($p->in_min) : '—';
            $actOut = ($p !== null && $p->out_min !== null) ? Shift::fmtMin($p->out_min) : '—';
            $paidIn = $paidOut = $regStr = $otStr = '—';
            $regNum = $otNum = 0.0;
            if ($settled !== null) {
                $paidIn = $settled['paidInFmt'];
                $paidOut = $settled['paidOutFmt'];
                $paid = max(0, $settled['paid']);
                $regNum = min($paid, 8);
                $otNum = max(0, $paid - 8);
                $regTotal += $regNum;
                $otTotal += $otNum;
                $regStr = number_format($regNum, 1).'h';
                $otStr = $otNum > 0.05 ? number_format($otNum, 1).'h' : '—';
            }
            if ($hasIn) {
                $present++;
            }

            // off-site flag: any punch leg recorded outside the site geofence.
            // Distance is recomputed from stored coords for the reviewer badge.
            [$geoOff, $geoDist, $geoUnverified] = self::geoReview($realPunch, $sites->get($e->site_id));

            // the punch snapshot (crew/company at clock time) wins over the
            // person's CURRENT crew — moving teams later must not rewrite this day
            $dayTeam = $p?->team_id ?? $e->team_id;
            $dayCompany = $p?->company_id ?? $e->company_id;

            $dayShift = ($settled && $settled['source'] === 'team' && $settled['shiftIn'] !== null)
                ? Shift::fmtMin($settled['shiftIn']).' – '.Shift::fmtMin($settled['shiftOut'])
                : null;

            $rows[] = [
                'id' => $e->id,
                'hasPunch' => $realPunch !== null && $realPunch->in_min !== null,   // a real punch record that can be voided / adjusted
                'punchId' => $realPunch?->id,
                'company' => $companyName($dayCompany),
                'team' => $teamName($dayTeam), 'teamId' => $dayTeam,
                'teamColor' => $teamColor($dayTeam),
                'name' => $e->displayName($lang), 'initials' => $e->initials(),
                'actIn' => $actIn, 'actOut' => $actOut,
                'paidIn' => $paidIn, 'paidOut' => $paidOut,
                'reg' => $regStr, 'ot' => $otStr,
                'regNum' => round($regNum, 2), 'otNum' => round($otNum, 2),
                'onDuty' => $p !== null && $p->in_min !== null && $p->out_min === null,
                'adjusted' => $settled['adjusted'] ?? false,
                'shiftLabel' => $dayShift,
                'geoOff' => $geoOff, 'geoDist' => $geoDist, 'geoUnverified' => $geoUnverified,
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
            return [false, null, false];
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
        // "unverified": the site HAS a geofence but a clock leg could not be
        // confirmed on-site (null verdict — location denied, unavailable, or too
        // coarse). Not off-site, but not proven on-site either.
        $hasFence = $site && $site->lat !== null && $site->lng !== null;
        $unverified = ! $off && $hasFence && (
            ($p->in_min !== null && $p->in_geo_ok !== true)
            || ($p->out_min !== null && $p->out_geo_ok !== true)
        );

        return [$off, $dist !== null ? (int) round($dist) : null, $unverified];
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
