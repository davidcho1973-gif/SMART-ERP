<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'NAHSHON MEP · Workforce' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Sans+KR:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    {{-- In-app browsers (KakaoTalk etc.) block Google OAuth ("disallowed_useragent").
         Bounce those users out to the system browser so sign-in works. --}}
    <script>
        (function () {
            var ua = (navigator.userAgent || '').toLowerCase();
            var url = location.href;
            // KakaoTalk → hand the URL to the system default browser
            if (ua.indexOf('kakaotalk') !== -1) {
                location.href = 'kakaotalk://web/openExternal?url=' + encodeURIComponent(url);
                return;
            }
            // LINE → force external browser via query flag
            if (ua.indexOf('line/') !== -1) {
                location.href = url + (url.indexOf('?') > -1 ? '&' : '?') + 'openExternalBrowser=1';
                return;
            }
            // Other in-app webviews (Instagram, Facebook, Naver, Whale, Everytime…) can't be
            // auto-escaped — show a hint bar so the user opens it in Chrome/Safari themselves.
            var inApp = /(instagram|fbav|fban|fbios|naver|whale|snapchat|everytimeapp|kakaostory|daumapps|zumapp)/.test(ua)
                || / wv[;)]/.test(ua);
            if (inApp) {
                document.addEventListener('DOMContentLoaded', function () {
                    var bar = document.createElement('div');
                    bar.setAttribute('role', 'alert');
                    bar.style.cssText = 'position:fixed;left:0;right:0;bottom:0;z-index:99999;background:#16181D;color:#fff;padding:13px 15px;font-size:13px;line-height:1.5;display:flex;gap:12px;align-items:center;font-family:sans-serif;box-shadow:0 -6px 20px rgba(0,0,0,.3);';
                    var msg = document.createElement('span');
                    msg.style.cssText = 'flex:1;';
                    msg.textContent = '⚠️ 구글 로그인은 Chrome·Safari에서만 됩니다. 주소를 복사해 브라우저에서 열어주세요.';
                    var btn = document.createElement('button');
                    btn.textContent = '주소 복사';
                    btn.style.cssText = 'background:#E85D2A;color:#fff;border:none;border-radius:8px;padding:9px 13px;font-size:12.5px;font-weight:700;cursor:pointer;white-space:nowrap;';
                    btn.addEventListener('click', function () {
                        var done = function () { btn.textContent = '복사됨 ✓'; };
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(location.href).then(done, done);
                        } else { done(); }
                    });
                    bar.appendChild(msg);
                    bar.appendChild(btn);
                    document.body.appendChild(bar);
                });
            }
        })();
    </script>
    {{ $slot }}
    @livewireScripts
    <script>
        window.addEventListener('print-now', () => window.print());
    </script>
</body>
</html>
