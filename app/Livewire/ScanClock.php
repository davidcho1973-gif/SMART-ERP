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
        if (! $emp) {
            return;
        }
        $p = $this->todayPunch($emp->id);
        $d = $this->dict();
        // one clock-in + one clock-out per day. Once both are recorded the day is
        // locked (punch-based, not UI state) — a re-scan can't reopen or overwrite it.
        if ($this->dayLocked($p)) {
            $this->clock = 'done';
            $this->toast = $d['w_workDone'];

            return;
        }
        $nowMin = (int) now()->format('H') * 60 + (int) now()->format('i');
        [, $geoOk] = Geo::verifySite($this->scannedSite(), $lat, $lng);
        $coords = $lat !== null && $lng !== null && $lat !== '' && $lng !== ''
            ? ['lat' => (float) $lat, 'lng' => (float) $lng, 'acc' => $acc !== null && $acc !== '' ? (float) $acc : null]
            : null;
        if ($p->in_min === null) {
            $this->clock = 'in';
            $this->clockInTime = Shift::fmtMin($nowMin);
            $p->in_min = $nowMin;
            $p->out_min = null;
            $p->source = 'qr';
            $p->in_lat = $coords['lat'] ?? null;
            $p->in_lng = $coords['lng'] ?? null;
            $p->in_acc = $coords['acc'] ?? null;
            $p->in_geo_ok = $geoOk;
            $p->save();
            $emp->update(['status' => 'present', 'in_t' => Shift::fmtMin($nowMin)]);
            $this->toast = $d['w_done_in'];
        } else {
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
