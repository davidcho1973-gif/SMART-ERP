<div style="min-height: 100vh; background: #E7E5DF; font-family: 'Space Grotesk', ui-sans-serif, system-ui, sans-serif; display: flex; flex-direction: column; align-items: center; padding: 24px 16px 60px;">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
            .poster-sheet { box-shadow: none !important; margin: 0 !important; }
            @page { margin: 10mm; }
        }
        .poster-sheet svg { display: block; width: 100%; height: auto; }
    </style>

    @if($invalid)
        <div style="margin-top: 60px; color: #8A8880;">Invalid or expired link.</div>
    @else
        {{-- controls (hidden when printing) --}}
        <div class="no-print" style="display: flex; gap: 10px; align-items: center; margin-bottom: 18px; flex-wrap: wrap; justify-content: center;">
            <button onclick="window.print()" style="padding: 11px 20px; border: none; border-radius: 11px; background: #16181D; color: #fff; font-size: 14px; font-weight: 700; cursor: pointer;">🖨️ 인쇄 / Print</button>
            <button onclick="downloadPoster('png')" style="padding: 11px 18px; border: none; border-radius: 11px; background: #E85D2A; color: #fff; font-size: 14px; font-weight: 700; cursor: pointer;">⬇️ 포스터 PNG</button>
            <button onclick="downloadPoster('svg')" style="padding: 11px 18px; border: 1px solid #D8D5CD; border-radius: 11px; background: #fff; color: #5A5D64; font-size: 14px; font-weight: 600; cursor: pointer;">⬇️ 포스터 SVG</button>
            <a href="{{ url('/') }}" style="padding: 11px 18px; border: 1px solid #D8D5CD; border-radius: 11px; background: #fff; color: #5A5D64; font-size: 14px; font-weight: 600; text-decoration: none;">← 앱으로</a>
        </div>

        {{-- the poster IS the SVG — so print, PNG and SVG downloads are identical --}}
        <div class="poster-sheet" style="width: 480px; max-width: 100%; background: #fff; border-radius: 8px; box-shadow: 0 14px 44px rgba(0,0,0,0.16);">
            {!! $posterSvg !!}
        </div>

        <script>
            function posterMarkup() {
                const svg = document.querySelector('#poster-svg').cloneNode(true);
                svg.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
                svg.setAttribute('width', '480');
                svg.setAttribute('height', '700');
                return new XMLSerializer().serializeToString(svg);
            }
            function saveAs(href, name) {
                const a = document.createElement('a');
                a.href = href; a.download = name; document.body.appendChild(a); a.click(); a.remove();
            }
            function downloadPoster(kind) {
                const xml = posterMarkup();
                const base = @js($fileBase) + '-poster';
                if (kind === 'svg') {
                    const blob = new Blob([xml], { type: 'image/svg+xml' });
                    const u = URL.createObjectURL(blob); saveAs(u, base + '.svg'); URL.revokeObjectURL(u);
                    return;
                }
                const img = new Image();
                img.onload = function () {
                    const scale = 2, W = 480 * scale, H = 700 * scale;
                    const c = document.createElement('canvas'); c.width = W; c.height = H;
                    const ctx = c.getContext('2d');
                    ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, W, H);
                    ctx.drawImage(img, 0, 0, W, H);
                    saveAs(c.toDataURL('image/png'), base + '.png');
                };
                img.src = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(xml)));
            }
        </script>
    @endif
</div>
