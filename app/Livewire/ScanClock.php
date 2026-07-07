<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Punch;
use App\Models\Site;
use App\Models\Team;
use App\Support\Geo;
use App\Support\Shift;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Landing page opened when a worker scans a site/crew QR with their phone.
 * Requires auth (route middleware); records real punches for the logged-in
 * employee against the scanned crew's site.
 */
#[Layout('components.layouts.app')]
class ScanClock extends Component
{
    public string $teamId = '';

    public string $lang = 'es';

    public string $clock = 'out';

    public string $clockInTime = '';

    public ?string $toast = null;

    public function mount(string $team): void
    {
        $this->teamId = $team;
        $emp = $this->me();
        if ($emp) {
            $this->lang = $emp->lang ?: 'es';
            $p = $this->todayPunch($emp->id);
            $this->clock = $this->clockStateFor($p);
            if ($this->clock === 'in') {
                $this->clockInTime = Shift::fmtMin($p->in_min);
            }
        } else {
            $this->lang = Auth::user()->access === 'admin' ? 'en' : 'ko';
        }
    }

    protected function me(): ?Employee
    {
        $eid = Auth::user()->employee_id ?? null;

        return $eid ? Employee::find($eid) : null;
    }

    protected function todayPunch(int $employeeId): Punch
    {
        return Punch::firstOrNew([
            'employee_id' => $employeeId,
            'work_date' => now()->format('Y-m-d'),
        ]);
    }

    /** A day is locked once the punch holds both an in and an out (one of each per day). */
    protected function dayLocked(Punch $p): bool
    {
        return $p->exists && $p->in_min !== null && $p->out_min !== null;
    }

    /** Clock state derived from the punch: 'out' (pre-in) · 'in' (working) · 'done' (locked). */
    protected function clockStateFor(Punch $p): string
    {
        if ($this->dayLocked($p)) {
            return 'done';
        }

        return ($p->exists && $p->in_min !== null && $p->out_min === null) ? 'in' : 'out';
    }

    protected function dict(): array
    {
        return (array) trans('app', [], $this->lang);
    }

    public function setLang(string $l): void
    {
        $this->lang = $l;
    }

    public function clearToast(): void
    {
        $this->toast = null;
    }

    /** The site behind the scanned crew's QR (crew → company → site). */
    protected function scannedSite(): ?Site
    {
        $team = Team::find($this->teamId);
        $company = $team ? Company::find($team->company_id) : null;

        return $company ? $company->site : null;
    }

    /**
     * Record a clock punch. GPS coordinates (lat/lng/accuracy) are captured by the
     * browser when the button is pressed and passed straight in — null when the
     * worker denied permission or the device has no fix. Attendance is never blocked;
     * out-of-radius punches are still recorded, just flagged geo_ok = false.
     */
    public function doClock(float|string|null $lat = null, float|string|null $lng = null, float|string|null $acc = null): void
    {
        $emp = $this->me();
        $d = $this->dict();
        if (! $emp) {
            return;
        }
        // a terminated / inactive record cannot punch
        if (! $emp->isActive()) {
            $this->toast = $d['w_notActive'];

            return;
        }
        // throttle: a few attempts per minute per employee
        $rlKey = 'scanclock:'.$emp->id;
        if (RateLimiter::tooManyAttempts($rlKey, 6)) {
            $this->toast = $d['w_tooMany'];

            return;
        }
        RateLimiter::hit($rlKey, 60);

        $p = $this->todayPunch($emp->id);
        // one clock-in + one clock-out per day. Once both are recorded the day is
        // locked (punch-based, not UI state) — a re-scan can't reopen or overwrite it.
        if ($this->dayLocked($p)) {
            $this->clock = 'done';
            $this->toast = $d['w_workDone'];

            return;
        }
        $nowMin = (int) now()->format('H') * 60 + (int) now()->format('i');
        $coords = Geo::coords($lat, $lng, $acc);
        [, $geoOk] = Geo::verifySite($this->scannedSite(), $coords['lat'] ?? null, $coords['lng'] ?? null);
        if ($geoOk === true && ($coords['acc'] === null || $coords['acc'] > Geo::MAX_TRUSTED_ACC_M)) {
            $geoOk = null;   // too imprecise to confirm on-site
        }
        if ($p->in_min === null) {
            // scanning a crew's QR IS the day's team assignment: the scanned crew
            // becomes today's crew (stamped on the punch) and the person's current
            // crew — no admin work needed when workers move between crews.
            $team = Team::find($this->teamId);
            $company = $team ? Company::find($team->company_id) : null;
            if ($team && $emp->team_id !== $team->id) {
                $from = $emp->team_id;
                $emp->update([
                    'team_id' => $team->id,
                    'company_id' => $company?->id ?? $emp->company_id,
                    'site_id' => $company?->site_id ?? $emp->site_id,
                ]);
                \App\Models\AuditLog::create([
                    'actor_id' => $emp->id,
                    'actor_name' => trim($emp->first.' '.$emp->last),
                    'action' => 'team.move',
                    'target' => trim($emp->first.' '.$emp->last).' (#'.$emp->id.')',
                    'detail' => ($from ?? '—').' → '.$team->id.' · via QR',
                ]);
            }
            $this->clock = 'in';
            $this->clockInTime = Shift::fmtMin($nowMin);
            $p->in_min = $nowMin;
            $p->out_min = null;
            $p->source = 'qr';
            $p->team_id = $team?->id ?? $emp->team_id;
            $p->company_id = $company?->id ?? $emp->company_id;
            $p->site_id = $company?->site_id ?? $emp->site_id;
            $p->in_lat = $coords['lat'] ?? null;
            $p->in_lng = $coords['lng'] ?? null;
            $p->in_acc = $coords['acc'] ?? null;
            $p->in_geo_ok = $geoOk;
            $p->save();
            $emp->update(['status' => 'present', 'in_t' => Shift::fmtMin($nowMin)]);
            $this->toast = $d['w_done_in'];
        } else {
            // guard: no clock-out within minutes of clocking in (duplicate taps /
            // double-fired GPS callbacks / accidental immediate outs)
            if ($nowMin - $p->in_min < Shift::MIN_OUT_GAP_MIN) {
                $this->clock = 'in';
                $this->clockInTime = Shift::fmtMin($p->in_min);
                $this->toast = $d['w_tooSoonOut'];

                return;
            }
            $this->clock = 'done';
            $p->out_min = $nowMin;
            $p->source = 'qr';
            $p->out_lat = $coords['lat'] ?? null;
            $p->out_lng = $coords['lng'] ?? null;
            $p->out_acc = $coords['acc'] ?? null;
            $p->out_geo_ok = $geoOk;
            $p->save();
            $emp->update(['status' => 'off', 'out_t' => Shift::fmtMin($nowMin)]);
            $this->toast = $d['w_done_out'];
        }
    }

    public function render()
    {
        $L = $this->dict();
        $team = Team::find($this->teamId);
        $company = $team ? Company::find($team->company_id) : null;
        $site = $company ? $company->site : null;
        $emp = $this->me();

        return view('livewire.scan-clock', [
            'L' => $L,
            'lang' => $this->lang,
            'teamName' => $team->name ?? '—',
            'teamColor' => $team->color ?? '#E85D2A',
            'companyName' => $company->name ?? '—',
            'siteName' => $site ? ($site->name.' · '.$site->city) : '',
            'emp' => $emp,
            'empName' => $emp ? ($this->lang === 'ko' && $emp->ko ? $emp->ko : $emp->first.' '.$emp->last) : null,
            'empInitials' => $emp ? strtoupper(mb_substr($emp->first, 0, 1).mb_substr($emp->last, 0, 1)) : '',
            'empRole' => $emp->role ?? '',
            'clockedIn' => $this->clock === 'in',
            'clockDone' => $this->clock === 'done',
            'clockLabel' => match ($this->clock) {
                'in' => $L['w_clockout'],
                'done' => $L['w_workDone'],
                default => $L['w_clockin'],
            },
            'statusLabel' => match ($this->clock) {
                'in' => $L['w_status_in'],
                'done' => $L['w_workDone'],
                default => $L['w_status_out'],
            },
            'toast' => $this->toast,
        ]);
    }
}
