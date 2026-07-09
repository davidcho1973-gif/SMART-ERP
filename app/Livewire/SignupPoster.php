<?php

namespace App\Livewire;

use App\Models\Site;
use App\Support\RealQr;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Printable sign-up QR poster for a site. An admin opens /join/{token}/poster,
 * prints it, and posts it at the remote site so workers can self-register.
 */
#[Layout('components.layouts.app')]
class SignupPoster extends Component
{
    public bool $invalid = false;

    public string $siteName = '';

    public string $qrSvg = '';

    public string $joinUrl = '';

    /** filename base for QR downloads, e.g. "savannah-ga-signup-qr" */
    public string $fileBase = 'signup-qr';

    public function mount(string $token): void
    {
        $site = Site::where('join_token', $token)->first();
        if (! $site) {
            $this->invalid = true;

            return;
        }
        $this->siteName = trim($site->name.($site->city ? ' · '.$site->city : ''));
        $this->joinUrl = url('/join/'.$token);
        $this->qrSvg = RealQr::svg($this->joinUrl, 460);
        $this->fileBase = (Str::slug($site->name) ?: 'site').'-signup-qr';
    }

    public function render()
    {
        return view('livewire.join-poster');
    }
}
