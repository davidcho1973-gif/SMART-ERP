<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'NAHSHON MEP · Workforce' }}</title>

    {{-- PWA: installable home-screen app --}}
    <meta name="theme-color" content="#16181D">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="NAHSHON">
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

    {{-- PWA install: service worker + one-tap "add to home screen" --}}
    <script>
        (function () {
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function () {
                    navigator.serviceWorker.register('/sw.js').catch(function () {});
                });
            }

            // already running as an installed app → nothing to offer
            var standalone = window.matchMedia('(display-mode: standalone)').matches
                || window.navigator.standalone === true;
            if (standalone) return;

            var DISMISS_KEY = 'nc_install_dismissed';
            function dismissed() { try { return localStorage.getItem(DISMISS_KEY) === '1'; } catch (e) { return false; } }
            function remember() { try { localStorage.setItem(DISMISS_KEY, '1'); } catch (e) {} }

            function makeBar(html) {
                var bar = document.createElement('div');
                bar.id = 'nc-install-bar';
                bar.style.cssText = 'position:fixed;left:12px;right:12px;bottom:16px;z-index:99998;background:#16181D;color:#fff;padding:13px 15px;border-radius:14px;font-size:13.5px;line-height:1.5;display:flex;gap:12px;align-items:center;font-family:sans-serif;box-shadow:0 12px 34px rgba(0,0,0,.35);';
                bar.innerHTML = html;
                return bar;
            }
            function removeBar() { var b = document.getElementById('nc-install-bar'); if (b) b.remove(); }

            // --- Android / Chrome: capture the native prompt, offer a one-tap button ---
            var deferredPrompt = null;
            window.addEventListener('beforeinstallprompt', function (e) {
                e.preventDefault();
                deferredPrompt = e;
                if (dismissed()) return;
                document.addEventListener('DOMContentLoaded', showAndroidBar);
                if (document.readyState !== 'loading') showAndroidBar();
            });
            function showAndroidBar() {
                if (document.getElementById('nc-install-bar') || !deferredPrompt) return;
                var bar = makeBar('<span style="width:34px;height:34px;border-radius:9px;background:#E85D2A;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-family:sans-serif;flex-shrink:0;">N</span><span style="flex:1;">홈 화면에 앱으로 추가할까요?</span>');
                var install = document.createElement('button');
                install.textContent = '설치';
                install.style.cssText = 'background:#E85D2A;color:#fff;border:none;border-radius:9px;padding:9px 15px;font-size:13px;font-weight:700;cursor:pointer;';
                install.addEventListener('click', function () {
                    removeBar();
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.finally(function () { deferredPrompt = null; });
                });
                var close = document.createElement('button');
                close.setAttribute('aria-label', 'close');
                close.textContent = '✕';
                close.style.cssText = 'background:transparent;color:rgba(255,255,255,.5);border:none;font-size:15px;cursor:pointer;padding:2px 4px;';
                close.addEventListener('click', function () { removeBar(); remember(); });
                bar.appendChild(install);
                bar.appendChild(close);
                document.body.appendChild(bar);
            }
            window.addEventListener('appinstalled', function () { removeBar(); remember(); });

            // --- iOS Safari: no prompt API → show a short how-to once ---
            var ua = navigator.userAgent || '';
            var isIOS = /iphone|ipad|ipod/i.test(ua);
            var isSafari = /safari/i.test(ua) && !/crios|fxios|edgios/i.test(ua);
            if (isIOS && isSafari && !dismissed()) {
                document.addEventListener('DOMContentLoaded', function () {
                    if (document.getElementById('nc-install-bar')) return;
                    var bar = makeBar('<span style="flex:1;">홈 화면에 추가: 하단 <b>공유</b> 버튼 → <b>“홈 화면에 추가”</b></span>');
                    var close = document.createElement('button');
                    close.textContent = '✕';
                    close.style.cssText = 'background:transparent;color:rgba(255,255,255,.5);border:none;font-size:15px;cursor:pointer;padding:2px 4px;';
                    close.addEventListener('click', function () { removeBar(); remember(); });
                    bar.appendChild(close);
                    document.body.appendChild(bar);
                });
            }
        })();
    </script>
</body>
</html>
