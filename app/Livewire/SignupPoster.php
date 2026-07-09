<?php

namespace App\Livewire;

use App\Models\Site;
use App\Support\RealQr;
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
    }

    public function render()
    {
        return view('livewire.join-poster');
    }
}
