@php
    $Ui = \App\Support\Ui::class;
    $Icon = \App\Support\Icons::class;
@endphp
<div style="min-height: 100vh; background: linear-gradient(180deg,#EDECE7,#E6E4DD); color: #16181D;">

    {{-- ===== DEMO CONTROL BAR ===== --}}
    <div style="position: sticky; top: 0; z-index: 50; display: flex; align-items: center; gap: 16px; padding: 8px 18px; background: rgba(22,24,29,0.96); color: #fff; backdrop-filter: blur(8px); flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 9px; font-weight: 700; letter-spacing: 0.02em;">
            <span style="display: inline-flex; width: 26px; height: 26px; border-radius: 7px; background: #E85D2A; align-items: center; justify-content: center; font-family: 'Space Grotesk'; font-size: 15px; font-weight: 700;">N</span>
            <span style="font-family: 'Space Grotesk'; font-size: 15px;">NAHSHON MEP</span>
            <span style="font-size: 11px; opacity: 0.5; font-weight: 400;">{{ $L['tagline'] }}</span>
        </div>
        <div style="flex: 1;"></div>
        <div style="display: flex; align-items: center; gap: 6px;">
            <span style="font-size: 11px; opacity: 0.55; margin-right: 2px;">{{ $L['roleSwitch'] }}</span>
            <button wire:click="setRole('admin')" style="{{ $Ui::tab($role==='admin') }}">{{ $L['roleAdmin'] }}</button>
            <button wire:click="setRole('manager')" style="{{ $Ui::tab($role==='manager') }}">{{ $L['roleManager'] }}</button>
            <button wire:click="setRole('worker')" style="{{ $Ui::tab($role==='worker') }}">{{ $L['roleWorker'] }}</button>
        </div>
        <div style="display: flex; align-items: center; gap: 3px; border-left: 1px solid rgba(255,255,255,0.15); padding-left: 12px;">
            <button wire:click="setLang('en')" style="{{ $Ui::langBtn($lang==='en') }}">EN</button>
            <button wire:click="setLang('es')" style="{{ $Ui::langBtn($lang==='es') }}">ES</button>
            <button wire:click="setLang('ko')" style="{{ $Ui::langBtn($lang==='ko') }}">KO</button>
        </div>
    </div>

    @if($isLogin)
        @include('livewire.partials.login')
    @endif

    @if($isDesktopApp)
        @include('livewire.partials.desktop')
    @endif

    @if($isWorker)
        @include('livewire.partials.worker')
    @endif

    {{-- hidden printable site-QR card --}}
    <div id="qr-print" style="position: fixed; left: -99999px; top: 0; z-index: 200; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #fff;">
        <div style="width: 360px; text-align: center; padding: 34px; border: 2px solid #16181D; border-radius: 18px;">
            <div style="font-family: 'Space Grotesk'; font-size: 22px; font-weight: 700;">{{ $qrPrint['company'] }}</div>
            <div style="font-size: 16px; color: #E85D2A; font-weight: 600; margin-top: 3px;">{{ $qrPrint['team'] }}</div>
            <div style="font-size: 13.5px; color: #5A5D64; margin-top: 3px;">{{ $qrPrint['leadWord'] }} · {{ $qrPrint['lead'] }}</div>
            <div style="width: 230px; height: 230px; margin: 22px auto 0;">{!! $qrPrint['svg'] !!}</div>
            <div style="font-family: 'Space Grotesk'; font-size: 11px; color: #8A8880; margin-top: 14px; letter-spacing: 0.14em;">NAHSHON MEP · SITE ACCESS</div>
        </div>
    </div>

    @if($toast)
        <div wire:key="toast-{{ $toast }}" x-data x-init="setTimeout(() => $wire.clearToast(), 2400)"
             style="position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); z-index: 100; background: #16181D; color: #fff; padding: 13px 22px; border-radius: 12px; font-size: 14px; font-weight: 500; box-shadow: 0 12px 30px rgba(0,0,0,0.3); animation: nctoast 0.25s ease; display: flex; align-items: center; gap: 10px;">
            <span style="color: #4ADE80;">✓</span>{{ $toast }}
        </div>
    @endif
</div>
