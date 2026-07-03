<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Punch;
use App\Models\Team;
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
            if ($p->exists && $p->in_min !== null && $p->out_min === null) {
                $this->clock = 'in';
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

    public function doClock(): void
    {
        $emp = $this->me();
        if (! $emp) {
            return;
        }
        $nowMin = (int) now()->format('H') * 60 + (int) now()->format('i');
        $p = $this->todayPunch($emp->id);
        $d = $this->dict();
        if ($this->clock === 'out') {
            $this->clock = 'in';
            $this->clockInTime = Shift::fmtMin($nowMin);
            $p->in_min = $p->in_min ?? $nowMin;
            $p->out_min = null;
            $p->source = 'qr';
            $p->save();
            $emp->update(['status' => 'present', 'in_t' => Shift::fmtMin($p->in_min)]);
            $this->toast = $d['w_done_in'];
        } else {
            $this->clock = 'out';
            $p->out_min = $nowMin;
            $p->source = 'qr';
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
            'siteName' => $site ? ($site->name . ' · ' . $site->city) : '',
            'emp' => $emp,
            'empName' => $emp ? ($this->lang === 'ko' && $emp->ko ? $emp->ko : $emp->first . ' ' . $emp->last) : null,
            'empInitials' => $emp ? strtoupper(mb_substr($emp->first, 0, 1) . mb_substr($emp->last, 0, 1)) : '',
            'empRole' => $emp->role ?? '',
            'clockedIn' => $this->clock === 'in',
            'clockLabel' => $this->clock === 'out' ? $L['w_clockin'] : $L['w_clockout'],
            'statusLabel' => $this->clock === 'in' ? $L['w_status_in'] : $L['w_status_out'],
            'toast' => $this->toast,
        ]);
    }
}
