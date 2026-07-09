@php
    $Ui = \App\Support\Ui::class;
    $Icon = \App\Support\Icons::class;
@endphp
<div style="min-height: 100vh; background: linear-gradient(180deg,#EDECE7,#E6E4DD); color: #16181D;">

    {{-- unread poller + KakaoTalk-style "new message" chime (Web Audio, no asset).
         Paused while the voice-report composer is open so the 7s re-render never
         disturbs the live mic (which would replay the browser's listening tone) or chimes. --}}
    @if(! $isLogin && ! $reportOpen)
        <div wire:poll.7s="pollComms" x-data="{
            ctx: null,
            ping() {
                try {
                    this.ctx = this.ctx || new (window.AudioContext || window.webkitAudioContext)();
                    if (this.ctx.state === 'suspended') this.ctx.resume();
                    const t0 = this.ctx.currentTime;
                    [[988, 0], [1319, 0.12]].forEach(([f, dt]) => {
                        const o = this.ctx.createOscillator(), g = this.ctx.createGain();
                        o.type = 'sine'; o.frequency.value = f;
                        o.connect(g); g.connect(this.ctx.destination);
                        g.gain.setValueAtTime(0.0001, t0 + dt);
                        g.gain.exponentialRampToValueAtTime(0.3, t0 + dt + 0.02);
                        g.gain.exponentialRampToValueAtTime(0.0001, t0 + dt + 0.2);
                        o.start(t0 + dt); o.stop(t0 + dt + 0.22);
                    });
                } catch (e) {}
            }
        }" x-on:comms-ping.window="ping()" style="position: absolute; width: 0; height: 0; overflow: hidden;"></div>
    @endif

    {{-- ===== DEMO CONTROL BAR ===== --}}
    <div class="wf-topbar" style="position: sticky; top: 0; z-index: 50; display: flex; align-items: center; gap: 16px; padding: 8px 18px; background: rgba(22,24,29,0.96); color: #fff; backdrop-filter: blur(8px); flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 9px; font-weight: 700; letter-spacing: 0.02em;">
            <span style="display: inline-flex; width: 26px; height: 26px; border-radius: 7px; background: #E85D2A; align-items: center; justify-content: center; font-family: 'Space Grotesk'; font-size: 15px; font-weight: 700;">N</span>
            <span style="font-family: 'Space Grotesk'; font-size: 15px;">NAHSHON MEP</span>
            <span class="wf-tagline" style="font-size: 11px; opacity: 0.5; font-weight: 400;">{{ $L['tagline'] }}</span>
            @if(! $isLogin && $isDesktopApp)
                <span class="wf-topdate" style="display: inline-flex; align-items: center; gap: 7px; margin-left: 8px; font-size: 12px; font-weight: 500; color: rgba(255,255,255,0.78); background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.13); padding: 5px 12px; border-radius: 8px;"><span style="width: 6px; height: 6px; border-radius: 50%; background: #4ADE80;"></span>{{ $today }}</span>
            @endif
        </div>
        <div style="flex: 1;"></div>

        {{-- role display: 본사 어드민 switches personas; everyone else sees a single
             static badge of their own granted role (현장 팀장 / 작업자) --}}
        @if(! $isLogin && count($viewSwitch) >= 1)
        <div style="display: flex; align-items: center; gap: 6px;">
            @if($viewSwitchable)
                <span style="font-size: 11px; opacity: 0.55; margin-right: 2px;">{{ $L['viewAs'] }}</span>
                @foreach($viewSwitch as $v)
                    <button wire:click="viewAs('{{ $v['role'] }}')" style="{{ $Ui::tab($v['active']) }}">{{ $v['label'] }}</button>
                @endforeach
            @else
                <span style="{{ $Ui::tab(true) }}">{{ $viewSwitch[0]['label'] }}</span>
            @endif
        </div>
        @endif

        @if(! $isLogin && $authName)
        <div style="display: flex; align-items: center; gap: 10px; border-left: 1px solid rgba(255,255,255,0.15); padding-left: 12px;">
            <span style="font-size: 12px; opacity: 0.8;">{{ $authName }}</span>
            <button wire:click="logout" style="{{ $Ui::tab(false) }}">{{ $L['a_logout'] }}</button>
        </div>
        @elseif($isDemo && ! $isLogin)
        <button wire:click="logout" style="{{ $Ui::tab(false) }}">{{ $L['a_logout'] }}</button>
        @endif
        <div style="display: flex; align-items: center; gap: 3px; border-left: 1px solid rgba(255,255,255,0.15); padding-left: 12px;">
            <button wire:click="setLang('en')" style="{{ $Ui::langBtn($lang==='en') }}">EN</button>
            <button wire:click="setLang('es')" style="{{ $Ui::langBtn($lang==='es') }}">ES</button>
            <button wire:click="setLang('ko')" style="{{ $Ui::langBtn($lang==='ko') }}">KO</button>
        </div>

        {{-- notification bell — top bar, right of the language switcher --}}
        @if(! $isLogin && $isDesktopApp && $comms)
            @php $bell = $comms['bell']; @endphp
            <div style="position: relative;">
                <button wire:click="toggleBell" title="{{ $comms['labels']['bellTitle'] }}" style="position: relative; display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.16); border-radius: 9px; cursor: pointer; color: #fff;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                    @if($bell['count'] > 0)
                        <span style="position: absolute; top: -6px; right: -6px; min-width: 17px; height: 17px; padding: 0 4px; border-radius: 9px; background: #E85D2A; color: #fff; font-size: 10.5px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; border: 2px solid #16181D;">{{ $bell['count'] > 99 ? '99+' : $bell['count'] }}</span>
                    @endif
                </button>
                @if($bellOpen)
                    <div x-data x-on:click.outside="$wire.set('bellOpen', false)"
                         style="position: absolute; top: 44px; right: 0; z-index: 70; width: 330px; background: #fff; border: 1px solid #E4E2DB; border-radius: 14px; box-shadow: 0 18px 40px rgba(0,0,0,0.25); overflow: hidden; color: #16181D;">
                        <div style="padding: 13px 16px; border-bottom: 1px solid #F0EEE8; display: flex; align-items: center; justify-content: space-between;">
                            <span style="font-family: 'Space Grotesk'; font-weight: 700; font-size: 14px;">{{ $comms['labels']['bellTitle'] }}</span>
                            @if($bell['count'] > 0)<span style="font-size: 11px; font-weight: 700; color: #E85D2A;">{{ $bell['count'] }}</span>@endif
                        </div>
                        <div style="max-height: 360px; overflow-y: auto;">
                            @forelse($bell['items'] as $it)
                                <button wire:click="openFromBell({{ $it['channelId'] }})" wire:key="bell-{{ $it['channelId'] }}"
                                    style="display: flex; align-items: center; gap: 11px; width: 100%; text-align: left; padding: 11px 16px; border: none; border-bottom: 1px solid #F5F3EE; background: transparent; cursor: pointer;"
                                    onmouseover="this.style.background='#FBFAF7'" onmouseout="this.style.background='transparent'">
                                    <span style="display: inline-flex; width: 34px; height: 34px; border-radius: 10px; background: {{ $it['color'] }}; color: #fff; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; flex-shrink: 0;">{{ mb_substr($it['name'], 0, 1) }}</span>
                                    <span style="flex: 1; min-width: 0;">
                                        <span style="display: flex; gap: 6px;"><span style="flex: 1; font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $it['name'] }}</span><span style="font-size: 10.5px; color: #B7B4AB;">{{ $it['time'] }}</span></span>
                                        <span style="display: block; font-size: 12px; color: #8A8880; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px;">{{ $it['preview'] ?: '—' }}</span>
                                    </span>
                                    <span style="flex-shrink: 0; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 9px; background: #E85D2A; color: #fff; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center;">{{ $it['unread'] }}</span>
                                </button>
                            @empty
                                <div style="padding: 30px 16px; text-align: center; color: #B7B4AB; font-size: 13px;">{{ $comms['labels']['bellEmpty'] }}</div>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>
        @endif
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
