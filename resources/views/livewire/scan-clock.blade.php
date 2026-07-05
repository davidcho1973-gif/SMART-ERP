@php
    $Ui = \App\Support\Ui::class;
    $clockDone = $clockDone ?? false;
    $clockBtnStyle = $clockDone
        ? 'width:100%;padding:20px;border:none;border-radius:20px;background:#8A8880;color:rgba(255,255,255,0.85);font-size:19px;font-weight:700;cursor:not-allowed;opacity:0.7;'
        : ($clockedIn
            ? 'width:100%;padding:20px;border:none;border-radius:20px;background:#D9483B;color:#fff;font-size:19px;font-weight:700;cursor:pointer;box-shadow:0 10px 24px rgba(217,72,59,0.3);'
            : 'width:100%;padding:20px;border:none;border-radius:20px;background:#1F9D6B;color:#fff;font-size:19px;font-weight:700;cursor:pointer;box-shadow:0 10px 24px rgba(31,157,107,0.35);');
    $pillBg = $clockDone ? 'rgba(255,255,255,0.14)' : ($clockedIn ? 'rgba(74,222,128,0.15)' : 'rgba(255,255,255,0.1)');
    $pillColor = $clockDone ? 'rgba(255,255,255,0.85)' : ($clockedIn ? '#4ADE80' : 'rgba(255,255,255,0.7)');
@endphp
<div style="min-height: 100vh; background: linear-gradient(180deg,#EDECE7,#E6E4DD); display: flex; align-items: flex-start; justify-content: center; padding: 24px 16px;">
    <div style="width: 420px; max-width: 100%;">
        {{-- header --}}
        <div style="display: flex; align-items: center; gap: 10px; padding: 4px 4px 18px;">
            <span style="display: inline-flex; width: 34px; height: 34px; border-radius: 9px; background: #E85D2A; align-items: center; justify-content: center; font-family: 'Space Grotesk'; font-size: 18px; font-weight: 700; color: #fff;">N</span>
            <div style="flex: 1;"><div style="font-family: 'Space Grotesk'; font-weight: 700; font-size: 15px; line-height: 1;">NAHSHON MEP</div><div style="font-size: 11px; color: #8A8880;">{{ $siteName }}</div></div>
            <div style="display: flex; gap: 2px; padding: 3px; background: #EAE8E1; border-radius: 8px;">
                <button wire:click="setLang('es')" style="{{ $Ui::mLang($lang==='es') }}">ES</button>
                <button wire:click="setLang('en')" style="{{ $Ui::mLang($lang==='en') }}">EN</button>
                <button wire:click="setLang('ko')" style="{{ $Ui::mLang($lang==='ko') }}">KO</button>
            </div>
        </div>

        {{-- crew context --}}
        <div style="display: flex; align-items: center; gap: 10px; background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; padding: 14px 16px; margin-bottom: 14px;">
            <span style="width: 12px; height: 12px; border-radius: 4px; background: {{ $teamColor }};"></span>
            <div><div style="font-size: 15px; font-weight: 700;">{{ $teamName }}</div><div style="font-size: 12px; color: #8A8880;">{{ $companyName }}</div></div>
        </div>

        @if($emp)
            {{-- clock card --}}
            <div style="background: #16181D; border-radius: 24px; padding: 26px; color: #fff; text-align: center;">
                <div style="display: flex; align-items: center; gap: 12px; text-align: left; margin-bottom: 18px;">
                    <span style="display: inline-flex; width: 46px; height: 46px; border-radius: 50%; background: {{ $teamColor }}; align-items: center; justify-content: center; font-size: 15px; font-weight: 700; font-family: 'Space Grotesk';">{{ $empInitials }}</span>
                    <div><div style="font-size: 12.5px; color: rgba(255,255,255,0.55);">{{ $L['w_hi'] }}</div><div style="font-size: 17px; font-weight: 700;">{{ $empName }}</div></div>
                </div>
                <div style="display: inline-flex; align-items: center; gap: 7px; font-size: 12.5px; background: {{ $pillBg }}; color: {{ $pillColor }}; padding: 6px 14px; border-radius: 20px; font-weight: 600;"><span style="width: 8px; height: 8px; border-radius: 50%; background: {{ $pillColor }};"></span>{{ $statusLabel }}</div>
                <div style="font-family: 'Space Grotesk'; font-size: 46px; font-weight: 700; margin-top: 14px;">{{ now()->format('g:i A') }}</div>
                <div style="font-size: 12.5px; color: rgba(255,255,255,0.5);">{{ $teamName }} · {{ $empRole }}</div>
                @if($clockedIn)
                    <div style="margin-top: 10px; font-size: 12.5px; color: #4ADE80;">{{ $L['w_since'] }} {{ $clockInTime }}</div>
                @endif
                <div style="margin-top: 22px;">
                    @if($clockDone)
                        <button type="button" disabled style="{{ $clockBtnStyle }}">{{ $clockLabel }}</button>
                    @else
                        {{-- capture GPS when tapped; clock in/out proceeds even if permission is denied (coords → null) --}}
                        <button type="button" x-data
                            @click="
                                $el.disabled = true;
                                const go = (la, ln, ac) => $wire.doClock(la, ln, ac);
                                if (navigator.geolocation) {
                                    navigator.geolocation.getCurrentPosition(
                                        p => go(p.coords.latitude, p.coords.longitude, p.coords.accuracy),
                                        () => go(null, null, null),
                                        { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 }
                                    );
                                } else { go(null, null, null); }
                            "
                            style="{{ $clockBtnStyle }}">{{ $clockLabel }}</button>
                    @endif
                </div>
            </div>
            <div style="text-align: center; font-size: 11.5px; color: #A7A49B; margin-top: 14px; line-height: 1.5;">{{ $L['q_sited'] }}</div>
        @else
            <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 20px; padding: 30px; text-align: center;">
                <div style="width: 52px; height: 52px; border-radius: 50%; background: #FBF1DF; display: flex; align-items: center; justify-content: center; margin: 0 auto 14px;"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#E8A33D" stroke-width="2"><circle cx="12" cy="8" r="3.4"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/></svg></div>
                <div style="font-size: 15px; font-weight: 600; line-height: 1.5;">{{ $L['sc_noWorker'] }}</div>
                <a href="/" style="display: inline-block; margin-top: 16px; padding: 11px 20px; border-radius: 11px; background: #16181D; color: #fff; font-size: 14px; font-weight: 600; text-decoration: none;">{{ $L['sc_home'] }}</a>
            </div>
        @endif
    </div>

    @if($toast)
        <div wire:key="toast-{{ $toast }}" x-data x-init="setTimeout(() => $wire.clearToast(), 2400)"
             style="position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); z-index: 100; background: #16181D; color: #fff; padding: 13px 22px; border-radius: 12px; font-size: 14px; font-weight: 500; box-shadow: 0 12px 30px rgba(0,0,0,0.3); display: flex; align-items: center; gap: 10px;">
            <span style="color: #4ADE80;">✓</span>{{ $toast }}
        </div>
    @endif
</div>
