{{-- ===== LOGIN (simple centered card) ===== --}}
<div style="min-height: calc(100vh - 44px); display: flex; align-items: center; justify-content: center; padding: 28px 16px; background: linear-gradient(180deg,#EDECE7,#E6E4DD);">
    <div style="width: 100%; max-width: 400px; background: #fff; border: 1px solid #E7E4DC; border-radius: 22px; padding: 34px 26px; box-shadow: 0 24px 60px rgba(22,24,29,0.08);">

        {{-- logo --}}
        <div style="display: flex; flex-direction: column; align-items: center; gap: 12px; margin-bottom: 22px;">
            <img src="{{ asset('images/nahshon-mark.svg') }}" alt="NAHSHON MEP" style="width: 52px; height: 52px; display: block;"/>
            <span style="font-family: 'Space Grotesk'; font-size: 18px; font-weight: 700; letter-spacing: 0.01em;">NAHSHON <span style="color: #E5403E;">MEP</span></span>
        </div>

        <h2 style="font-family: 'Space Grotesk'; font-size: 24px; font-weight: 700; text-align: center;">{{ $L['signInTitle'] }}</h2>
        <p style="color: #6B6E76; margin-top: 6px; font-size: 13.5px; line-height: 1.6; text-align: center;">{{ $L['a_signinWith'] }}</p>

        {{-- Google (always available) --}}
        <a href="/auth/google/redirect" style="margin-top: 24px; width: 100%; display: flex; align-items: center; justify-content: center; gap: 11px; padding: 14px; border: 1.5px solid #E4E2DB; border-radius: 13px; background: #fff; font-size: 15px; font-weight: 600; color: #16181D; cursor: pointer; text-decoration: none;">
            <svg width="19" height="19" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9 3.6l6.7-6.7C35.9 2.6 30.4 0 24 0 14.6 0 6.5 5.4 2.6 13.2l7.8 6.1C12.2 13.6 17.6 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.5 3-2.2 5.5-4.7 7.2l7.3 5.7c4.3-4 6.8-9.9 6.8-17.4z"/><path fill="#FBBC05" d="M10.4 28.7c-.5-1.4-.8-2.9-.8-4.7s.3-3.3.8-4.7l-7.8-6.1C.9 16.5 0 20.1 0 24s.9 7.5 2.6 10.8l7.8-6.1z"/><path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.3-5.7c-2 1.4-4.7 2.3-8.6 2.3-6.4 0-11.8-4.1-13.6-9.8l-7.8 6.1C6.5 42.6 14.6 48 24 48z"/></svg>
            {{ $L['googleBtn'] }}
        </a>

        {{-- divider --}}
        <div style="display: flex; align-items: center; gap: 12px; margin: 20px 0; color: #A7A49B; font-size: 12px;"><span style="flex: 1; height: 1px; background: #EAE7E0;"></span>or<span style="flex: 1; height: 1px; background: #EAE7E0;"></span></div>

        {{-- password --}}
        <form wire:submit="login">
            <label style="display: block; margin-bottom: 12px;"><span style="font-size: 12.5px; color: #8A8880;">{{ $L['a_email'] }}</span>
                <input type="email" wire:model="loginEmail" autocomplete="username" style="width: 100%; margin-top: 5px; padding: 12px 14px; border: 1px solid #E4E2DB; border-radius: 12px; font-size: 14.5px; outline: none;"/></label>
            <label style="display: block;"><span style="font-size: 12.5px; color: #8A8880;">{{ $L['a_password'] }}</span>
                <input type="password" wire:model="loginPassword" autocomplete="current-password" style="width: 100%; margin-top: 5px; padding: 12px 14px; border: 1px solid #E4E2DB; border-radius: 12px; font-size: 14.5px; outline: none;"/></label>
            <button type="submit" style="width: 100%; margin-top: 18px; padding: 14px; border: none; border-radius: 13px; background: #16181D; color: #fff; font-size: 15px; font-weight: 600; cursor: pointer;">{{ $L['a_login'] }}</button>
        </form>

        @if($isDemo)
            <div style="display: flex; align-items: center; gap: 12px; margin: 20px 0 12px; color: #A7A49B; font-size: 12px;"><span style="flex: 1; height: 1px; background: #EAE7E0;"></span>{{ $L['orDemo'] }}<span style="flex: 1; height: 1px; background: #EAE7E0;"></span></div>
            <div style="display: flex; gap: 8px;">
                <button wire:click="demo('admin')" style="flex: 1; padding: 10px; border: 1px solid #E4E2DB; border-radius: 10px; background: #FAFAF8; font-size: 12.5px; font-weight: 600; cursor: pointer;">{{ $L['roleAdmin'] }}</button>
                <button wire:click="demo('manager')" style="flex: 1; padding: 10px; border: 1px solid #E4E2DB; border-radius: 10px; background: #FAFAF8; font-size: 12.5px; font-weight: 600; cursor: pointer;">{{ $L['roleManager'] }}</button>
                <button wire:click="demo('worker')" style="flex: 1; padding: 10px; border: 1px solid #E4E2DB; border-radius: 10px; background: #FAFAF8; font-size: 12.5px; font-weight: 600; cursor: pointer;">{{ $L['roleWorker'] }}</button>
            </div>
        @endif
    </div>
</div>
