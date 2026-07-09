<div style="min-height: 100vh; background: #E7E5DF; font-family: 'Space Grotesk', ui-sans-serif, system-ui, sans-serif; display: flex; flex-direction: column; align-items: center; padding: 24px 16px 60px;">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
            .poster-sheet { box-shadow: none !important; border: none !important; margin: 0 !important; }
            @page { margin: 12mm; }
        }
    </style>

    @if($invalid)
        <div style="margin-top: 60px; color: #8A8880;">Invalid or expired link.</div>
    @else
        {{-- controls (hidden when printing) --}}
        <div class="no-print" style="display: flex; gap: 10px; align-items: center; margin-bottom: 18px; flex-wrap: wrap; justify-content: center;">
            <button onclick="window.print()" style="padding: 11px 20px; border: none; border-radius: 11px; background: #16181D; color: #fff; font-size: 14px; font-weight: 700; cursor: pointer;">🖨️ 인쇄 / Print</button>
            <button onclick="downloadQr('png')" style="padding: 11px 18px; border: none; border-radius: 11px; background: #E85D2A; color: #fff; font-size: 14px; font-weight: 700; cursor: pointer;">⬇️ QR PNG</button>
            <button onclick="downloadQr('svg')" style="padding: 11px 18px; border: 1px solid #D8D5CD; border-radius: 11px; background: #fff; color: #5A5D64; font-size: 14px; font-weight: 600; cursor: pointer;">⬇️ QR SVG</button>
            <a href="{{ url('/') }}" style="padding: 11px 18px; border: 1px solid #D8D5CD; border-radius: 11px; background: #fff; color: #5A5D64; font-size: 14px; font-weight: 600; text-decoration: none;">← 앱으로</a>
        </div>
        <script>
            function downloadQr(kind) {
                const src = document.querySelector('#qrbox svg');
                if (!src) return;
                const svg = src.cloneNode(true);
                svg.setAttribute('width', '1024');
                svg.setAttribute('height', '1024');
                svg.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
                const xml = new XMLSerializer().serializeToString(svg);
                const base = @js($fileBase);
                if (kind === 'svg') {
                    const blob = new Blob([xml], { type: 'image/svg+xml' });
                    const a = document.createElement('a');
                    a.href = URL.createObjectURL(blob); a.download = base + '.svg'; a.click();
                    URL.revokeObjectURL(a.href);
                    return;
                }
                // PNG: render the SVG onto a white-padded canvas
                const img = new Image();
                img.onload = function () {
                    const S = 1024, pad = 64;
                    const c = document.createElement('canvas'); c.width = S; c.height = S;
                    const ctx = c.getContext('2d');
                    ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, S, S);
                    ctx.drawImage(img, pad, pad, S - 2 * pad, S - 2 * pad);
                    const a = document.createElement('a');
                    a.href = c.toDataURL('image/png'); a.download = base + '.png'; a.click();
                };
                img.src = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(xml)));
            }
        </script>

        {{-- the poster --}}
        <div class="poster-sheet" style="width: 460px; max-width: 100%; background: #fff; border-radius: 8px; box-shadow: 0 14px 44px rgba(0,0,0,0.16); padding: 40px 38px 34px; text-align: center; color: #16181D;">
            <div style="display: flex; align-items: center; justify-content: center; gap: 11px; margin-bottom: 6px;">
                <img src="{{ asset('images/nahshon-mark.svg') }}" alt="NAHSHON MEP" style="width: 40px; height: 40px; display: block;"/>
                <span style="font-family: 'Space Grotesk'; font-size: 22px; font-weight: 700;">NAHSHON <span style="color: #E5403E;">MEP</span></span>
            </div>
            <div style="display: inline-block; margin-top: 12px; font-size: 12px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #3B72E0; background: #EAF3FF; padding: 5px 14px; border-radius: 20px;">{{ $siteName }}</div>

            <h1 style="font-size: 30px; margin: 18px 0 3px; letter-spacing: -0.01em;">Clock-In Sign-Up</h1>
            <div style="font-size: 15px; color: #3A3D44; font-weight: 600;">현장 출퇴근 등록 · Registro de asistencia</div>

            <div id="qrbox" style="width: 260px; height: 260px; margin: 22px auto 10px; background: #fff; border: 1px solid #ECEAE3; border-radius: 16px; padding: 16px;">
                {!! $qrSvg !!}
            </div>
            <div style="font-size: 15px; font-weight: 700;">📷 Scan with your phone <span style="color: #8A8880; font-weight: 500;">· 휴대폰으로 스캔</span></div>

            <div style="text-align: left; max-width: 320px; margin: 22px auto 0; display: flex; flex-direction: column; gap: 11px;">
                @foreach([
                    ['1', 'Scan & pick your language', '스캔 후 언어 선택 · Escanea y elige idioma'],
                    ['2', 'Enter your details + selfie', '본인 정보 + 셀피 · Tus datos + selfie'],
                    ['3', 'We approve — then clock in', '승인 후 출퇴근 · Aprobación y listo'],
                ] as [$n, $en, $sub])
                    <div style="display: flex; gap: 12px; align-items: flex-start;">
                        <span style="flex-shrink: 0; width: 22px; height: 22px; border-radius: 50%; background: #16181D; color: #fff; font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center;">{{ $n }}</span>
                        <div style="font-size: 13.5px;"><div style="font-weight: 700;">{{ $en }}</div><div style="color: #8A8880; font-size: 12px;">{{ $sub }}</div></div>
                    </div>
                @endforeach
            </div>

            <div style="margin-top: 22px; padding-top: 16px; border-top: 1px dashed #E4E2DB; font-size: 11.5px; color: #8A8880; word-break: break-all;">
                {{ $joinUrl }}<br>
                <span style="color: #A7A49B;">Questions? Ask your site lead · 문의는 현장 팀장에게</span>
            </div>
        </div>
    @endif
</div>
