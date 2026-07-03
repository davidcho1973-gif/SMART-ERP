<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Site;
use App\Models\Team;
use App\Support\Money;
use App\Support\Payroll;
use App\Support\Qr;
use App\Support\Shift;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class WorkforceApp extends Component
{
    // ---- primary navigation / UI state ----
    public string $screen = 'login';
    public string $role = 'admin';
    public string $lang = 'en';
    public string $dashLayout = 'A';
    public string $site = 'all';

    // ---- employees ----
    public ?int $selectedEmp = null;
    public string $empFilter = 'active';
    public string $teamFilter = 'all';
    public string $search = '';
    public ?int $deleteId = null;
    public ?int $terminateId = null;
    /** working copy of the selected employee's editable fields */
    public array $editForm = [];

    // ---- badge wizard ----
    public string $bstep = 'front';
    public string $scanF = 'idle';
    public string $scanB = 'idle';
    public string $scanN = 'idle';
    public string $regTeam = 't1';
    public string $regType = 'worker_local';
    public string $regAccess = 'worker';

    // ---- projects modals ----
    public bool $companyModal = false;
    public ?string $teamModal = null;
    public string $newCoName = '';
    public string $newCoSite = '';
    public string $newTeamName = '';
    public string $newTeamLead = '';

    // ---- attendance ----
    public string $qrMode = 'reader';
    public string $qrTeam = 't1';

    // ---- payroll ----
    public ?int $payDetail = null;
    public bool $payVoucher = false;
    public string $checkNo = '';
    public string $payDate = 'Jul 1, 2026';

    // ---- worker mobile ----
    public string $mobileTab = 'home';
    public string $clock = 'out';
    public string $clockInTime = '6:58 AM';
    public bool $earlyOpen = false;
    public string $earlyReasonVal = '';
    public string $earlyCustom = '';
    public bool $noLunchToday = false;
    /** per-date lunch overrides keyed by day label */
    public array $lunchOv = [];

    // ---- toast ----
    public ?string $toast = null;

    protected const NFC_UID = '04:73:AC:2F:19:B4:5E';

    // =================== helpers ===================

    /** Whole translation dictionary for the current language. */
    protected function dict(): array
    {
        return (array) trans('app', [], $this->lang);
    }

    /** Inline trilingual string. */
    protected function tl(string $en, string $es, string $ko): string
    {
        return $this->lang === 'ko' ? $ko : ($this->lang === 'es' ? $es : $en);
    }

    protected function nfcId(): string
    {
        $hex = preg_replace('/[^0-9A-Fa-f]/', '', self::NFC_UID);
        return 'N-' . strtoupper(substr($hex, -9));
    }

    protected function showToast(string $msg): void
    {
        $this->toast = $msg;
    }

    public function clearToast(): void
    {
        $this->toast = null;
    }

    // =================== navigation / auth ===================

    public function setRole(string $r): void
    {
        if ($r === 'worker') {
            $this->reset(['selectedEmp']);
            $this->role = 'worker';
            $this->screen = 'worker';
            $this->mobileTab = 'home';
            $this->lang = 'es';
        } elseif ($r === 'manager') {
            $this->role = 'manager';
            $this->screen = 'dashboard';
            $this->lang = 'ko';
        } else {
            $this->role = 'admin';
            $this->screen = 'dashboard';
            $this->lang = 'en';
        }
    }

    public function setLang(string $l): void
    {
        $this->lang = $l;
    }

    public function go(string $screen): void
    {
        $this->screen = $screen;
        $this->selectedEmp = null;
        $this->bstep = 'front';
        $this->scanF = $this->scanB = $this->scanN = 'idle';
    }

    public function setDash(string $l): void
    {
        $this->dashLayout = $l;
    }

    public function setMobileTab(string $k): void
    {
        $this->mobileTab = $k;
    }

    public function googleSignIn(): void
    {
        $this->role = 'admin';
        $this->screen = 'dashboard';
        $this->showToast($this->dict()['googleBtn']);
    }

    public function demo(string $role): void
    {
        $this->setRole($role);
    }

    public function logout(): void
    {
        $this->screen = 'login';
    }

    // =================== employees ===================

    public function setEmpFilter(string $f): void
    {
        $this->empFilter = $f;
    }

    public function setTeamFilter(string $t): void
    {
        $this->teamFilter = $t;
    }

    public function selectEmp(int $id): void
    {
        $e = Employee::find($id);
        if (! $e) {
            return;
        }
        $this->selectedEmp = $id;
        $this->editForm = [
            'first' => $e->first, 'last' => $e->last, 'company' => $e->company_id,
            'team' => $e->team_id, 'role' => $e->role, 'rate' => $e->rate,
            'type' => $e->type === 'manager' ? 'manager' : ($e->lang === 'ko' ? 'worker_ko' : 'worker_local'),
            'issued' => $e->issued, 'phone' => $e->phone, 'email' => $e->email,
            'nat' => $e->nat, 'access' => $e->access,
        ];
    }

    public function closeDetail(): void
    {
        $this->selectedEmp = null;
    }

    public function setFormAccess(string $lvl): void
    {
        $this->editForm['access'] = $lvl;
    }

    public function addWorker(): void
    {
        $this->screen = 'badge';
        $this->bstep = 'front';
        $this->scanF = $this->scanB = $this->scanN = 'idle';
    }

    public function saveEmp(): void
    {
        $e = Employee::find($this->selectedEmp);
        if ($e) {
            $type = $this->editForm['type'] ?? 'worker_local';
            $company = $this->editForm['company'] ?? $e->company_id;
            $e->update([
                'first' => $this->editForm['first'] ?? $e->first,
                'last' => $this->editForm['last'] ?? $e->last,
                'company_id' => $company,
                'site_id' => optional(Company::find($company))->site_id ?? $e->site_id,
                'team_id' => $this->editForm['team'] ?? $e->team_id,
                'role' => $this->editForm['role'] ?? $e->role,
                'rate' => (float) ($this->editForm['rate'] ?? $e->rate),
                'type' => $type === 'manager' ? 'manager' : 'worker',
                'lang' => $type === 'worker_local' ? 'es' : 'ko',
                'issued' => $this->editForm['issued'] ?? $e->issued,
                'phone' => $this->editForm['phone'] ?? $e->phone,
                'email' => $this->editForm['email'] ?? $e->email,
                'nat' => $this->editForm['nat'] ?? $e->nat,
                'access' => $this->editForm['access'] ?? $e->access,
            ]);
        }
        $this->selectedEmp = null;
        $this->showToast($this->dict()['e_save'] . ' ✓');
    }

    public function askDelete(int $id): void
    {
        $this->deleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->deleteId = null;
    }

    public function confirmDelete(): void
    {
        Employee::where('id', $this->deleteId)->delete();
        $this->deleteId = null;
        $this->selectedEmp = null;
        $this->showToast($this->dict()['e_delete'] . ' ✓');
    }

    public function askTerm(int $id): void
    {
        $this->terminateId = $id;
    }

    public function cancelTerm(): void
    {
        $this->terminateId = null;
    }

    public function confirmTerm(): void
    {
        Employee::where('id', $this->terminateId)->update([
            'emp' => 'terminated', 'term' => '07/01/2026', 'access' => 'worker', 'status' => 'off',
        ]);
        $this->terminateId = null;
        $this->selectedEmp = null;
        $this->showToast($this->dict()['e_terminate'] . ' ✓');
    }

    public function reactivate(int $id): void
    {
        Employee::where('id', $id)->update(['emp' => 'active', 'term' => null]);
        $this->showToast($this->dict()['e_reactivated']);
    }

    // =================== projects ===================

    public function openCompanyModal(): void
    {
        $this->companyModal = true;
        $this->newCoName = '';
        $this->newCoSite = '';
    }

    public function cancelCompany(): void
    {
        $this->companyModal = false;
    }

    public function saveCompany(): void
    {
        $name = trim($this->newCoName);
        $siteName = trim($this->newCoSite);
        if ($name === '' || $siteName === '') {
            return;
        }
        $site = Site::all()->first(function ($s) use ($siteName) {
            return strcasecmp($s->name . ' · ' . $s->city, $siteName) === 0
                || strcasecmp($s->name, $siteName) === 0;
        });
        if (! $site) {
            $site = Site::create(['id' => 's' . Str::random(6), 'name' => $siteName, 'city' => '', 'gc' => 'Hoffman', 'code' => '']);
        }
        Company::create(['id' => 'c' . Str::random(6), 'name' => $name, 'site_id' => $site->id]);
        $this->companyModal = false;
        $this->showToast(str_replace('+ ', '', $this->dict()['pj_create']) . ' ✓');
    }

    public function openTeamModal(string $companyId): void
    {
        $this->teamModal = $companyId;
        $this->newTeamName = '';
        $first = Employee::where('type', 'manager')->where('emp', 'active')->orderBy('id')->first();
        $this->newTeamLead = $first ? (string) $first->id : '';
    }

    public function cancelTeam(): void
    {
        $this->teamModal = null;
    }

    public function saveTeam(): void
    {
        if (trim($this->newTeamName) === '' || ! $this->teamModal) {
            return;
        }
        $cols = ['#3B72E0', '#1F9D6B', '#E85D2A', '#D9483B', '#8A5CF6', '#0EA5A0'];
        $count = Team::count();
        Team::create([
            'id' => 't' . Str::random(6),
            'name' => trim($this->newTeamName),
            'company_id' => $this->teamModal,
            'lead' => $this->newTeamLead !== '' ? (int) $this->newTeamLead : null,
            'color' => $cols[$count % count($cols)],
        ]);
        $this->teamModal = null;
        $this->showToast(str_replace('+ ', '', $this->dict()['pj_newTeam']) . ' ✓');
    }

    public function changeLead(string $teamId, string $leadId): void
    {
        Team::where('id', $teamId)->update(['lead' => (int) $leadId]);
    }

    // =================== badge wizard ===================

    public function startScanF(): void
    {
        $this->scanF = 'scanning';
    }

    public function finishScanF(): void
    {
        if ($this->scanF === 'scanning') {
            $this->scanF = 'done';
        }
    }

    public function rescanF(): void
    {
        $this->scanF = 'idle';
    }

    public function toBack(): void
    {
        $this->bstep = 'back';
    }

    public function toAssign(): void
    {
        $this->bstep = 'assign';
    }

    public function backToFront(): void
    {
        $this->bstep = 'front';
    }

    public function backToBack(): void
    {
        $this->bstep = 'back';
    }

    public function startScanB(): void
    {
        $this->scanB = 'scanning';
    }

    public function finishScanB(): void
    {
        if ($this->scanB === 'scanning') {
            $this->scanB = 'done';
        }
    }

    public function startScanN(): void
    {
        $this->scanN = 'scanning';
    }

    public function finishScanN(): void
    {
        if ($this->scanN === 'scanning') {
            $this->scanN = 'done';
        }
    }

    public function setRegType(string $v): void
    {
        $this->regType = $v;
        $this->regAccess = $v === 'manager' ? 'manager' : 'worker';
    }

    public function setRegAccess(string $lvl): void
    {
        $this->regAccess = $lvl;
    }

    public function finishBadge(): void
    {
        $team = Team::find($this->regTeam);
        $companyId = $team->company_id ?? 'c1';
        $siteId = optional(Company::find($companyId))->site_id ?? 's1';
        Employee::create([
            'emp_id' => $this->nfcId(),
            'first' => 'Carlos', 'last' => 'Martínez', 'nat' => 'Mexico', 'code' => 'MX',
            'team_id' => $this->regTeam, 'company_id' => $companyId, 'site_id' => $siteId,
            'role' => 'Electrician',
            'type' => $this->regType === 'manager' ? 'manager' : 'worker',
            'lang' => $this->regType === 'worker_local' ? 'es' : 'ko',
            'access' => $this->regAccess, 'rate' => 32.50, 'issued' => '03/14/2026',
            'phone' => '(480) 555-0500', 'email' => 'cmartinez2@nahshon.io',
            'status' => 'off', 'in_t' => '—', 'out_t' => '—', 'wh' => 0, 'emp' => 'active', 'term' => null,
        ]);
        $this->screen = 'employees';
        $this->bstep = 'front';
        $this->scanF = $this->scanB = $this->scanN = 'idle';
        $this->showToast($this->dict()['b_finish'] . ' ✓');
    }

    // =================== attendance ===================

    public function setQrMode(string $m): void
    {
        $this->qrMode = $m;
    }

    public function selectQrTeam(string $tid): void
    {
        $this->qrTeam = $tid;
    }

    public function manualPunch(int $id, string $dir): void
    {
        $e = Employee::find($id);
        if (! $e || $e->emp !== 'active') {
            return;
        }
        $now = now()->format('g:i A');
        if ($dir === 'in') {
            $e->update(['status' => 'present', 'in_t' => $now]);
        } else {
            $e->update(['status' => 'off', 'out_t' => $now]);
        }
        $label = $dir === 'in' ? $this->dict()['q_in'] : $this->dict()['q_out'];
        $this->showToast($e->first . ' ' . $e->last . ' · ' . $label);
    }

    public function exportPayroll(): void
    {
        $this->showToast($this->dict()['p_export'] . ' ✓');
    }

    // =================== payroll ===================

    public function openPayDetail(int $id): void
    {
        $this->payDetail = $id;
    }

    public function closePayDetail(): void
    {
        $this->payDetail = null;
    }

    public function openVoucher(): void
    {
        $this->payVoucher = true;
    }

    public function closeVoucher(): void
    {
        $this->payVoucher = false;
    }

    public function printVoucher(): void
    {
        if (trim($this->checkNo) === '') {
            $this->showToast($this->dict()['pv_needCheck']);
            return;
        }
        $this->dispatch('print-now');
        $this->showToast($this->dict()['pv_paidToast']);
    }

    // =================== worker mobile ===================

    public function doClock(): void
    {
        $me = Employee::find(106); // worker mobile "me" = Carlos
        if ($this->clock === 'out') {
            $this->clock = 'in';
            $this->clockInTime = now()->format('g:i A');
            $me?->update(['status' => 'present', 'in_t' => $this->clockInTime]);
            $this->showToast($this->dict()['w_done_in']);
        } else {
            $this->clock = 'out';
            $me?->update(['status' => 'off', 'out_t' => now()->format('g:i A')]);
            $this->showToast($this->dict()['w_done_out']);
        }
    }

    public function toggleNoLunch(): void
    {
        $this->noLunchToday = ! $this->noLunchToday;
        $d = $this->dict();
        $this->showToast($this->noLunchToday ? $d['lunchSkipToast'] : $d['lunchKeptToast']);
    }

    public function toggleLunchRow(string $dayKey, bool $seedNoLunch): void
    {
        $cur = array_key_exists($dayKey, $this->lunchOv) ? $this->lunchOv[$dayKey] : $seedNoLunch;
        $this->lunchOv[$dayKey] = ! $cur;
        $d = $this->dict();
        $this->showToast(! $cur ? $d['lunchSkipToast'] : $d['lunchKeptToast']);
    }

    public function openEarly(): void
    {
        $this->earlyOpen = true;
        $this->earlyReasonVal = $this->dict()['w_reasons'][0];
        $this->earlyCustom = '';
    }

    public function closeEarly(): void
    {
        $this->earlyOpen = false;
    }

    public function submitEarly(): void
    {
        $d = $this->dict();
        $reason = $this->earlyReasonVal === '__custom__'
            ? (trim($this->earlyCustom) ?: $d['w_earlyOther'])
            : $this->earlyReasonVal;
        $this->clock = 'out';
        $this->earlyOpen = false;
        $this->showToast($d['w_earlyDone'] . ' · ' . $reason);
    }

    public function printQr(): void
    {
        $this->dispatch('print-now');
    }

    // =================== render ===================

    public function render()
    {
        $vm = \App\Support\ViewModel::build($this->state());

        return view('livewire.workforce-app', $vm);
    }

    // ---- accessors used by the view-model builder ----
    public function state(): array
    {
        return [
            'screen' => $this->screen, 'role' => $this->role, 'lang' => $this->lang,
            'dashLayout' => $this->dashLayout, 'site' => $this->site,
            'selectedEmp' => $this->selectedEmp, 'empFilter' => $this->empFilter,
            'teamFilter' => $this->teamFilter, 'search' => $this->search,
            'deleteId' => $this->deleteId, 'terminateId' => $this->terminateId,
            'editForm' => $this->editForm,
            'bstep' => $this->bstep, 'scanF' => $this->scanF, 'scanB' => $this->scanB, 'scanN' => $this->scanN,
            'regTeam' => $this->regTeam, 'regType' => $this->regType, 'regAccess' => $this->regAccess,
            'companyModal' => $this->companyModal, 'teamModal' => $this->teamModal,
            'newCoName' => $this->newCoName, 'newCoSite' => $this->newCoSite,
            'newTeamName' => $this->newTeamName, 'newTeamLead' => $this->newTeamLead,
            'qrMode' => $this->qrMode, 'qrTeam' => $this->qrTeam,
            'payDetail' => $this->payDetail, 'payVoucher' => $this->payVoucher,
            'checkNo' => $this->checkNo, 'payDate' => $this->payDate,
            'mobileTab' => $this->mobileTab, 'clock' => $this->clock, 'clockInTime' => $this->clockInTime,
            'earlyOpen' => $this->earlyOpen, 'earlyReasonVal' => $this->earlyReasonVal, 'earlyCustom' => $this->earlyCustom,
            'noLunchToday' => $this->noLunchToday, 'lunchOv' => $this->lunchOv,
            'toast' => $this->toast,
            'nfcUid' => self::NFC_UID, 'nfcId' => $this->nfcId(),
        ];
    }

    public function tlPublic(string $en, string $es, string $ko): string
    {
        return $this->tl($en, $es, $ko);
    }
}
