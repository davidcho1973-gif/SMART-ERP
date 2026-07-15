<?php

namespace App\Livewire;

use App\Models\Employee;
use App\Models\Equipment;
use App\Models\Site;
use App\Support\Access;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Landing page opened when someone scans a piece of equipment's printed QR with
 * their phone (route /e/{token}, auth-gated). A field user with equipment.checkout
 * can deploy the unit to their current site or return it, right from the scan —
 * no desktop needed. Every action is logged as an equipment event.
 */
#[Layout('components.layouts.app')]
class EquipScan extends Component
{
    public string $token = '';

    public ?int $equipId = null;

    public string $lang = 'es';

    public ?string $toast = null;

    public function mount(string $token): void
    {
        $this->token = $token;
        $e = Equipment::where('qr_token', $token)->first();
        $this->equipId = $e?->id;

        $emp = $this->me();
        $this->lang = $emp?->lang ?: (Auth::user()->access === 'admin' ? 'en' : 'ko');
    }

    protected function me(): ?Employee
    {
        $eid = Auth::user()->employee_id ?? null;

        return $eid ? Employee::find($eid) : null;
    }

    protected function roles(): array
    {
        $user = Auth::user();
        $roles = [$user->access];
        if ($user->employee_id && ($e = Employee::find($user->employee_id))) {
            $roles[] = $e->role;
            foreach (Access::leadsTeams($e) as $_) {
                $roles[] = 'crew_lead';
                break;
            }
        }

        return $roles;
    }

    protected function canCheckout(): bool
    {
        return Access::allows($this->roles(), 'equipment.checkout');
    }

    protected function tl(string $en, string $es, string $ko): string
    {
        return $this->lang === 'ko' ? $ko : ($this->lang === 'en' ? $en : $es);
    }

    public function setLang(string $l): void
    {
        $this->lang = in_array($l, ['en', 'es', 'ko'], true) ? $l : 'es';
    }

    public function clearToast(): void
    {
        $this->toast = null;
    }

    /** Deploy the scanned unit to the scanner's current site, holding it themselves. */
    public function checkout(): void
    {
        $e = $this->equipId ? Equipment::find($this->equipId) : null;
        if (! $e || ! $this->canCheckout()) {
            return;
        }
        if (RateLimiter::tooManyAttempts('equipscan:'.$e->id, 8)) {
            $this->toast = $this->tl('Too many attempts — wait a moment.', 'Demasiados intentos.', '요청이 많아요 — 잠시 후 다시 시도해 주세요.');

            return;
        }
        RateLimiter::hit('equipscan:'.$e->id, 60);

        $emp = $this->me();
        $site = $emp?->site_id ?: $e->site_id ?: Site::query()->value('id');
        if (! $site) {
            $this->toast = $this->tl('No site to assign.', 'Sin obra para asignar.', '배치할 현장이 없어요.');

            return;
        }
        $e->update(['status' => 'out', 'site_id' => $site, 'holder_id' => $emp?->id]);
        $e->events()->create(['type' => 'checkout', 'site_id' => $site, 'employee_id' => $emp?->id, 'at' => now(), 'meter' => $e->meter, 'note' => 'QR']);
        $this->toast = $this->tl('Checked out to your site.', 'Asignado a tu obra.', '내 현장으로 배치했어요.');
    }

    /** Return the scanned unit to available (check-in). */
    public function checkin(): void
    {
        $e = $this->equipId ? Equipment::find($this->equipId) : null;
        if (! $e || ! $this->canCheckout()) {
            return;
        }
        $emp = $this->me();
        $e->update(['status' => 'available', 'holder_id' => null]);
        $e->events()->create(['type' => 'checkin', 'site_id' => $e->site_id, 'employee_id' => $emp?->id, 'at' => now(), 'meter' => $e->meter, 'note' => 'QR']);
        $this->toast = $this->tl('Checked in.', 'Devuelto.', '입고 처리했어요.');
    }

    public function render()
    {
        $e = $this->equipId ? Equipment::with(['photos', 'holder', 'site'])->find($this->equipId) : null;

        $statusMeta = [
            'available' => ['name' => $this->tl('Available', 'Disponible', '보유'), 'color' => '#1F9D6B', 'bg' => '#E7F5EF'],
            'out' => ['name' => $this->tl('On site', 'En obra', '현장 배치'), 'color' => '#3B72E0', 'bg' => '#E7EEFB'],
            'maintenance' => ['name' => $this->tl('Maintenance', 'Mantenimiento', '정비 중'), 'color' => '#C98A1E', 'bg' => '#FBF1DE'],
            'returned' => ['name' => $this->tl('Returned', 'Devuelto', '반납'), 'color' => '#8A8880', 'bg' => '#F1F0EC'],
            'disposed' => ['name' => $this->tl('Disposed', 'Baja', '폐기'), 'color' => '#8A8880', 'bg' => '#F1F0EC'],
        ];

        $cover = null;
        if ($e) {
            foreach (['main', 'side', 'plate'] as $k) {
                $p = $e->photos->firstWhere('kind', $k);
                if ($p && $p->isImage()) {
                    $cover = url('/accounting/equip-photo/'.$p->id);
                    break;
                }
            }
        }

        return view('livewire.equip-scan', [
            'lang' => $this->lang,
            'equip' => $e,
            'cover' => $cover,
            'st' => $e ? ($statusMeta[$e->status] ?? $statusMeta['available']) : null,
            'holderName' => $e?->holder?->displayName($this->lang),
            'siteName' => $e?->site?->name,
            'isRented' => (bool) $e?->isRented(),
            'canCheckout' => $this->canCheckout(),
            'meLabel' => $this->me()?->displayName($this->lang),
            't' => [
                'notFound' => $this->tl('Equipment not found', 'Equipo no encontrado', '장비를 찾을 수 없어요'),
                'notFoundSub' => $this->tl('This QR does not match any registered equipment.', 'Este QR no corresponde a ningún equipo.', '이 QR에 해당하는 등록 장비가 없어요.'),
                'serial' => $this->tl('Serial', 'Serie', '시리얼'),
                'tag' => $this->tl('Asset tag', 'Etiqueta', '자산번호'),
                'site' => $this->tl('Site', 'Obra', '현장'),
                'holder' => $this->tl('Holder', 'Responsable', '담당'),
                'meter' => $this->tl('Meter', 'Medidor', '계기'),
                'checkout' => $this->tl('Check out to my site', 'Asignar a mi obra', '내 현장으로 배치'),
                'checkin' => $this->tl('Check in (return)', 'Devolver', '입고 처리'),
                'noPerm' => $this->tl('You can view this unit, but only field leads and office roles can move it.', 'Solo líderes y roles de oficina pueden moverlo.', '조회만 가능해요. 배치·입고는 반장·사무직만 할 수 있어요.'),
                'scanBy' => $this->tl('Scanning as', 'Escaneando como', '스캔 사용자'),
            ],
        ]);
    }
}
