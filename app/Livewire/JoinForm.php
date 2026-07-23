<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Public self-service sign-up opened from a printed site QR (/join/{token}).
 * No auth. A remote worker enters their own details + a selfie + a password;
 * this creates a PENDING employee that an approver must activate before the
 * person can log in or clock. Trilingual (en/es/ko).
 */
#[Layout('components.layouts.app')]
class JoinForm extends Component
{
    public ?string $token = null;

    public string $lang = 'en';

    public string $first = '';

    public string $last = '';

    public string $phone = '';

    public string $email = '';

    public string $trade = '';

    public string $password = '';

    public string $passwordConfirm = '';

    /** base64 selfie captured on the device (optional but encouraged) */
    public string $selfie = '';

    public bool $submitted = false;

    public bool $invalid = false;

    public string $siteName = '';

    protected ?Site $site = null;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->site = Site::where('join_token', $token)->first();
        if (! $this->site) {
            $this->invalid = true;

            return;
        }
        $this->siteName = trim($this->site->name.($this->site->city ? ' · '.$this->site->city : ''));
    }

    public function setLang(string $l): void
    {
        $this->lang = in_array($l, ['en', 'es', 'ko'], true) ? $l : 'en';
    }

    protected function dict(): array
    {
        return (array) trans('app', [], $this->lang);
    }

    public function submit(): void
    {
        $d = $this->dict();
        $this->site = $this->site ?: Site::where('join_token', $this->token)->first();
        if (! $this->site) {
            $this->invalid = true;

            return;
        }
        // a handful of sign-ups per minute per device — blunt anti-spam
        $rl = 'join:'.request()->ip();
        if (RateLimiter::tooManyAttempts($rl, 5)) {
            $this->addError('email', $d['j_tooMany']);

            return;
        }

        $first = trim($this->first);
        $last = trim($this->last);
        $email = mb_strtolower(trim($this->email));
        if ($first === '' && $last === '') {
            $this->addError('first', $d['j_needName']);

            return;
        }
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addError('email', $d['j_needEmail']);

            return;
        }
        if (strlen($this->password) < 8) {
            $this->addError('password', $d['j_pwShort']);

            return;
        }
        if ($this->password !== $this->passwordConfirm) {
            $this->addError('passwordConfirm', $d['j_pwMismatch']);

            return;
        }
        // no double sign-ups: email or phone already on file (employee or user)
        $phone = trim($this->phone);
        $dup = Employee::where('email', $email)
            ->orWhere(fn ($q) => $phone !== '' ? $q->where('phone', $phone) : $q->whereRaw('1=0'))
            ->exists() || User::where('email', $email)->exists();
        if ($dup) {
            $this->addError('email', $d['j_dup']);

            return;
        }

        RateLimiter::hit($rl, 60);
        $company = Company::where('site_id', $this->site->id)->first();
        // Backstop: the selfie is downscaled client-side, but never trust the client —
        // only store a real, reasonably-sized data-URI image; drop anything else so an
        // oversized/garbled value can't overflow the column and 500 the sign-up.
        $selfie = $this->selfie;
        $selfie = (is_string($selfie) && str_starts_with($selfie, 'data:image/') && strlen($selfie) <= 200_000)
            ? $selfie : null;
        Employee::create([
            'emp_id' => $this->pendingId(),
            'first' => $first, 'last' => $last,
            'nat' => '', 'code' => '',
            'team_id' => null,                       // approver assigns the crew
            'company_id' => $company?->id,
            'site_id' => $this->site->id,            // geofence anchor for clock-in
            'role' => trim($this->trade),
            'type' => 'worker', 'pay_type' => 'hourly', 'access' => 'worker',
            'lang' => $this->lang,
            'rate' => 0,
            'phone' => $phone, 'email' => $email,
            'badge_photo' => $selfie,
            'join_password' => Hash::make($this->password),
            'status' => 'off', 'in_t' => '—', 'out_t' => '—', 'wh' => 0,
            'emp' => 'pending', 'term' => null, 'activated_at' => null,
        ]);

        $this->reset(['first', 'last', 'phone', 'email', 'trade', 'password', 'passwordConfirm', 'selfie']);
        $this->submitted = true;
    }

    protected function pendingId(): string
    {
        do {
            $id = 'PEND-'.strtoupper(Str::random(6));
        } while (Employee::where('emp_id', $id)->exists());

        return $id;
    }

    public function render()
    {
        return view('livewire.join-form', ['L' => $this->dict()]);
    }
}
