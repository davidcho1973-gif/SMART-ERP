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
 * The whole poster is a single SVG so print, PNG, and SVG downloads all match.
 */
#[Layout('components.layouts.app')]
class SignupPoster extends Component
{
    public bool $invalid = false;

    public string $siteName = '';

    public string $joinUrl = '';

    /** the full poster as one SVG (used for display, print, and downloads) */
    public string $posterSvg = '';

    /** filename base for downloads, e.g. "savannah-ga-signup" */
    public string $fileBase = 'signup';

    public function mount(string $token): void
    {
        $site = Site::where('join_token', $token)->first();
        if (! $site) {
            $this->invalid = true;

            return;
        }
        $this->siteName = trim($site->name.($site->city ? ' · '.$site->city : ''));
        $this->joinUrl = url('/join/'.$token);
        $this->fileBase = (Str::slug($site->name) ?: 'site').'-signup';
        $this->posterSvg = $this->buildPosterSvg();
    }

    /** Compose the entire poster (logo, title, QR, steps, url) as one SVG. */
    protected function buildPosterSvg(): string
    {
        $e = fn (string $s) => htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');

        // logo mark, inlined as a data URI so it renders inside the exported image
        $markPath = public_path('images/nahshon-mark.svg');
        $mark = is_file($markPath)
            ? 'data:image/svg+xml;base64,'.base64_encode((string) file_get_contents($markPath))
            : '';

        // QR nested as a sub-<svg> at a fixed box
        $qr = RealQr::svg($this->joinUrl, 240);
        $qr = str_replace('width="100%" height="100%"', 'x="120" y="196" width="240" height="240"', $qr);

        $site = $e($this->siteName);
        $url = $e($this->joinUrl);
        $pillW = min(360, max(120, 9 * mb_strlen($this->siteName) + 34));
        $pillX = (480 - $pillW) / 2;

        $steps = [
            ['1', 'Scan & pick your language', '스캔 후 언어 선택 · Escanea y elige idioma'],
            ['2', 'Enter your details + selfie', '본인 정보 + 셀피 · Tus datos + selfie'],
            ['3', 'We approve — then clock in', '승인 후 출퇴근 · Aprobación y listo'],
        ];
        $stepSvg = '';
        $y = 500;
        foreach ($steps as [$n, $en, $sub]) {
            $stepSvg .= '<circle cx="112" cy="'.($y - 4).'" r="12" fill="#16181D"/>'
                .'<text x="112" y="'.($y).'" text-anchor="middle" font-size="13" font-weight="700" fill="#fff">'.$n.'</text>'
                .'<text x="134" y="'.($y - 3).'" font-size="14" font-weight="700" fill="#16181D">'.$e($en).'</text>'
                .'<text x="134" y="'.($y + 12).'" font-size="11.5" fill="#8A8880">'.$e($sub).'</text>';
            $y += 42;
        }

        $ff = "'Space Grotesk', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif";

        return <<<SVG
<svg id="poster-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 480 700" width="480" height="700" font-family="$ff">
  <rect x="0" y="0" width="480" height="700" fill="#ffffff"/>
  <rect x="6" y="6" width="468" height="688" rx="10" fill="none" stroke="#ECEAE3"/>
  <image x="152" y="30" width="36" height="36" href="$mark"/>
  <text x="196" y="55" font-size="21" font-weight="700" fill="#16181D">NAHSHON <tspan fill="#E5403E">MEP</tspan></text>
  <rect x="$pillX" y="80" width="$pillW" height="24" rx="12" fill="#EAF3FF"/>
  <text x="240" y="96" text-anchor="middle" font-size="12" font-weight="700" letter-spacing="0.6" fill="#3B72E0">$site</text>
  <text x="240" y="148" text-anchor="middle" font-size="31" font-weight="700" fill="#16181D">Clock-In Sign-Up</text>
  <text x="240" y="173" text-anchor="middle" font-size="14" font-weight="600" fill="#3A3D44">현장 출퇴근 등록 · Registro de asistencia</text>
  <rect x="112" y="188" width="256" height="256" rx="14" fill="#fff" stroke="#ECEAE3"/>
  $qr
  <text x="240" y="474" text-anchor="middle" font-size="15" font-weight="700" fill="#16181D">Scan with your phone · 휴대폰으로 스캔</text>
  $stepSvg
  <line x1="40" y1="628" x2="440" y2="628" stroke="#E4E2DB" stroke-dasharray="3 3"/>
  <text x="240" y="648" text-anchor="middle" font-size="10" fill="#8A8880">$url</text>
  <text x="240" y="666" text-anchor="middle" font-size="10.5" fill="#A7A49B">Questions? Ask your site lead · 문의는 현장 팀장에게</text>
</svg>
SVG;
    }

    public function render()
    {
        return view('livewire.join-poster');
    }
}
