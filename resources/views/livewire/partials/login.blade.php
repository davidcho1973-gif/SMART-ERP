{{-- ===== LOGIN ===== --}}
<div style="min-height: calc(100vh - 44px); display: grid; grid-template-columns: 1.1fr 0.9fr;">
    <div style="position: relative; background: #16181D; color: #fff; padding: 56px 60px; display: flex; flex-direction: column; justify-content: space-between; overflow: hidden;">
        <div style="position: absolute; inset: 0; background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.06) 1px, transparent 0); background-size: 22px 22px;"></div>
        <div style="position: absolute; right: -80px; top: -60px; width: 320px; height: 320px; border-radius: 50%; background: radial-gradient(circle, rgba(232,93,42,0.35), transparent 65%);"></div>
        <div style="position: relative; display: flex; align-items: center; gap: 11px;">
            <span style="display: inline-flex; width: 38px; height: 38px; border-radius: 10px; background: #E85D2A; align-items: center; justify-content: center; font-family: 'Space Grotesk'; font-size: 22px; font-weight: 700;">N</span>
            <span style="font-family: 'Space Grotesk'; font-size: 20px; font-weight: 700;">NAHSHON MEP</span>
        </div>
        <div style="position: relative;">
            <div style="font-size: 13px; letter-spacing: 0.28em; color: #E85D2A; font-weight: 600; margin-bottom: 18px;">{{ $L['loginKicker'] }}</div>
            <h1 style="font-family: 'Space Grotesk'; font-size: 44px; line-height: 1.08; font-weight: 700; letter-spacing: -0.02em; white-space: pre-line;">{{ $L['loginTitle'] }}</h1>
            <p style="margin-top: 20px; font-size: 15px; line-height: 1.7; color: rgba(255,255,255,0.62); max-width: 420px;">{{ $L['loginSub'] }}</p>
            <div style="display: flex; gap: 30px; margin-top: 40px;">
                <div><div style="font-family: 'Space Grotesk'; font-size: 30px; font-weight: 700; color: #E85D2A;">{{ $stat_workers }}</div><div style="font-size: 12px; color: rgba(255,255,255,0.5); margin-top: 2px;">{{ $L['statWorkers'] }}</div></div>
                <div><div style="font-family: 'Space Grotesk'; font-size: 30px; font-weight: 700;">3</div><div style="font-size: 12px; color: rgba(255,255,255,0.5); margin-top: 2px;">{{ $L['statSites'] }}</div></div>
                <div><div style="font-family: 'Space Grotesk'; font-size: 30px; font-weight: 700;">3</div><div style="font-size: 12px; color: rgba(255,255,255,0.5); margin-top: 2px;">{{ $L['statCompanies'] }}</div></div>
            </div>
        </div>
        <div style="position: relative; font-size: 12px; color: rgba(255,255,255,0.4);">© 2026 NAHSHON MEP · Multi-Site Workforce · Phoenix, AZ (MST)</div>
    </div>

    <div style="display: flex; flex-direction: column; justify-content: center; padding: 56px 60px; background: #fff;">
        <div style="max-width: 380px; width: 100%; margin: 0 auto;">
            <h2 style="font-family: 'Space Grotesk'; font-size: 27px; font-weight: 700;">{{ $L['signInTitle'] }}</h2>
            <p style="color: #6B6E76; margin-top: 8px; font-size: 14px; line-height: 1.6;">{{ $L['signInSub'] }}</p>
            @if($googleEnabled)
            <a href="/auth/google/redirect" style="margin-top: 30px; width: 100%; display: flex; align-items: center; justify-content: center; gap: 11px; padding: 14px; border: 1.5px solid #E4E2DB; border-radius: 12px; background: #fff; font-size: 15px; font-weight: 600; color: #16181D; cursor: pointer; text-decoration: none;">
                <svg width="19" height="19" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9 3.6l6.7-6.7C35.9 2.6 30.4 0 24 0 14.6 0 6.5 5.4 2.6 13.2l7.8 6.1C12.2 13.6 17.6 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.5 3-2.2 5.5-4.7 7.2l7.3 5.7c4.3-4 6.8-9.9 6.8-17.4z"/><path fill="#FBBC05" d="M10.4 28.7c-.5-1.4-.8-2.9-.8-4.7s.3-3.3.8-4.7l-7.8-6.1C.9 16.5 0 20.1 0 24s.9 7.5 2.6 10.8l7.8-6.1z"/><path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.3-5.7c-2 1.4-4.7 2.3-8.6 2.3-6.4 0-11.8-4.1-13.6-9.8l-7.8 6.1C6.5 42.6 14.6 48 24 48z"/></svg>
                {{ $L['googleBtn'] }}
            </a>
            @elseif($isDemo)
            <button wire:click="googleSignIn" style="margin-top: 30px; width: 100%; display: flex; align-items: center; justify-content: center; gap: 11px; padding: 14px; border: 1.5px solid #E4E2DB; border-radius: 12px; background: #fff; font-size: 15px; font-weight: 600; color: #16181D; cursor: pointer;">
                <svg width="19" height="19" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9 3.6l6.7-6.7C35.9 2.6 30.4 0 24 0 14.6 0 6.5 5.4 2.6 13.2l7.8 6.1C12.2 13.6 17.6 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.5 3-2.2 5.5-4.7 7.2l7.3 5.7c4.3-4 6.8-9.9 6.8-17.4z"/><path fill="#FBBC05" d="M10.4 28.7c-.5-1.4-.8-2.9-.8-4.7s.3-3.3.8-4.7l-7.8-6.1C.9 16.5 0 20.1 0 24s.9 7.5 2.6 10.8l7.8-6.1z"/><path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.3-5.7c-2 1.4-4.7 2.3-8.6 2.3-6.4 0-11.8-4.1-13.6-9.8l-7.8 6.1C6.5 42.6 14.6 48 24 48z"/></svg>
                {{ $L['googleBtn'] }}
            </button>
            @endif

            {{-- password sign-in --}}
            <form wire:submit="login" style="margin-top: {{ $googleEnabled || $isDemo ? '18px' : '30px' }};">
                <label style="display: block; margin-bottom: 12px;"><span style="font-size: 12.5px; color: #8A8880;">{{ $L['a_email'] }}</span>
                    <input type="email" wire:model="loginEmail" autocomplete="username" style="width: 100%; margin-top: 5px; padding: 12px 14px; border: 1px solid #E4E2DB; border-radius: 11px; font-size: 14.5px; outline: none;"/></label>
                <label style="display: block;"><span style="font-size: 12.5px; color: #8A8880;">{{ $L['a_password'] }}</span>
                    <input type="password" wire:model="loginPassword" autocomplete="current-password" style="width: 100%; margin-top: 5px; padding: 12px 14px; border: 1px solid #E4E2DB; border-radius: 11px; font-size: 14.5px; outline: none;"/></label>
                <button type="submit" style="width: 100%; margin-top: 16px; padding: 14px; border: none; border-radius: 12px; background: #16181D; color: #fff; font-size: 15px; font-weight: 600; cursor: pointer;">{{ $L['a_login'] }}</button>
            </form>

            @if($isDemo)
            <div style="display: flex; align-items: center; gap: 12px; margin: 24px 0; color: #A7A49B; font-size: 12px;"><span style="flex: 1; height: 1px; background: #E4E2DB;"></span>{{ $L['orDemo'] }}<span style="flex: 1; height: 1px; background: #E4E2DB;"></span></div>
            <div style="display: flex; flex-direction: column; gap: 9px;">
                <button wire:click="demo('admin')" style="display: flex; align-items: center; justify-content: space-between; padding: 13px 16px; border: 1px solid #E4E2DB; border-radius: 11px; background: #FAFAF8; cursor: pointer; text-align: left;">
                    <span><span style="font-weight: 600; font-size: 14px;">{{ $L['roleAdmin'] }}</span><span style="display: block; font-size: 12px; color: #8A8880;">{{ $L['adminDesc'] }}</span></span><span style="font-family: 'Space Grotesk'; color: #E85D2A;">→</span>
                </button>
                <button wire:click="demo('manager')" style="display: flex; align-items: center; justify-content: space-between; padding: 13px 16px; border: 1px solid #E4E2DB; border-radius: 11px; background: #FAFAF8; cursor: pointer; text-align: left;">
                    <span><span style="font-weight: 600; font-size: 14px;">{{ $L['roleManager'] }}</span><span style="display: block; font-size: 12px; color: #8A8880;">{{ $L['managerDesc'] }}</span></span><span style="font-family: 'Space Grotesk'; color: #E85D2A;">→</span>
                </button>
                <button wire:click="demo('worker')" style="display: flex; align-items: center; justify-content: space-between; padding: 13px 16px; border: 1px solid #E4E2DB; border-radius: 11px; background: #FAFAF8; cursor: pointer; text-align: left;">
                    <span><span style="font-weight: 600; font-size: 14px;">{{ $L['roleWorker'] }}</span><span style="display: block; font-size: 12px; color: #8A8880;">{{ $L['workerDesc'] }}</span></span><span style="font-family: 'Space Grotesk'; color: #E85D2A;">→</span>
                </button>
            </div>
            @endif
        </div>
    </div>
</div>
