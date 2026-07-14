@php
    $Ui = \App\Support\Ui::class;
    $Icon = \App\Support\Icons::class;
@endphp
{{-- ===== DESKTOP APP ===== --}}
<div class="wf-shell" style="display: flex; min-height: calc(100vh - 44px);">
    <aside class="wf-sidebar" style="width: 244px; flex-shrink: 0; background: #16181D; color: #fff; padding: 22px 14px; display: flex; flex-direction: column; gap: 4px;">
        <div style="display: flex; align-items: center; gap: 10px; padding: 4px 10px 18px;">
            <img src="{{ asset('images/nahshon-mark.svg') }}" alt="NAHSHON MEP" style="width: 32px; height: 32px; display: block;"/>
            <div><div style="font-family: 'Space Grotesk'; font-weight: 700; font-size: 15px; line-height: 1;">NAHSHON <span style="color: #E5403E;">MEP</span></div><div style="font-size: 10px; color: rgba(255,255,255,0.4); letter-spacing: 0.08em; margin-top: 3px;">AUTOMATION · CONTROLS</div></div>
        </div>
        @foreach($nav as $item)
            <button wire:click="go('{{ $item['key'] }}')" style="{{ $Ui::navItem($item['active']) }}">
                <span style="display: inline-flex; width: 20px; justify-content: center; flex-shrink: 0;">{!! $Icon::nav($item['key']) !!}</span>
                <span style="white-space: nowrap;">{{ $item['label'] }}</span>
                @if(($item['unread'] ?? 0) > 0)
                    <span style="margin-left: auto; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 9px; background: #E85D2A; color: #fff; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center;">{{ $item['unread'] > 99 ? '99+' : $item['unread'] }}</span>
                @endif
            </button>
        @endforeach
        <div style="flex: 1;"></div>
        <div style="padding: 12px 10px; border-top: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; gap: 10px;">
            <span style="display: inline-flex; width: 34px; height: 34px; border-radius: 50%; background: {{ $me['color'] }}; color: #fff; align-items: center; justify-content: center; font-weight: 600; font-size: 13px;">{{ $me['initials'] }}</span>
            <div style="flex: 1; min-width: 0;"><div style="font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $me['name'] }}</div><div style="font-size: 11px; color: rgba(255,255,255,0.45);">{{ $me['role'] }}</div></div>
            <button wire:click="logout" title="logout" style="background: transparent; border: none; color: rgba(255,255,255,0.5); cursor: pointer; padding: 4px;"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg></button>
        </div>
    </aside>

    <main class="wf-main" style="flex: 1; min-width: 0; display: flex; flex-direction: column;">
        <div class="wf-header {{ $screen === 'comms' ? 'wf-header--comms' : '' }}" style="display: flex; align-items: center; gap: 16px; padding: 14px 30px; border-bottom: 1px solid #E1DFD8; background: rgba(255,255,255,0.7); backdrop-filter: blur(6px); position: sticky; top: 44px; z-index: 20;">
            <div><h1 class="wf-title" style="font-family: 'Space Grotesk'; font-size: 20px; font-weight: 700;">{{ $pageTitle }}</h1><div style="font-size: 12.5px; color: #8A8880;">{{ $pageSub }}</div></div>
            <div style="flex: 1;"></div>
            <div class="wf-hdr-ctl" style="display: flex; align-items: center; gap: 8px; padding: 6px 8px 6px 14px; background: #fff; border: 1px solid #E4E2DB; border-radius: 10px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#8A8880" stroke-width="2"><path d="M12 21s-7-5.7-7-11a7 7 0 0 1 14 0c0 5.3-7 11-7 11z"/><circle cx="12" cy="10" r="2.4"/></svg>
                <select wire:model.live="site" style="border: none; outline: none; background: transparent; font-size: 13px; font-weight: 600; color: #16181D; cursor: pointer;">
                    @foreach($siteOptions as $o)
                        <option value="{{ $o['id'] }}">{{ $o['label'] }}</option>
                    @endforeach
                </select>
            </div>
            @if(($deskClock['show'] ?? false))
                <div class="wf-hdr-ctl" style="display: flex; align-items: center; gap: 10px; padding: 5px 6px 5px 13px; background: #fff; border: 1px solid #E4E2DB; border-radius: 10px;">
                    <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; color: {{ $deskClock['isIn'] ? '#1F9D6B' : '#8A8880' }};">
                        <span style="width: 7px; height: 7px; border-radius: 50%; background: {{ $deskClock['isIn'] ? '#1F9D6B' : '#C4C1B8' }};"></span>{{ $deskClock['statusLabel'] }}@if($deskClock['since']) · {{ $deskClock['sinceWord'] }} {{ $deskClock['since'] }}@endif
                    </span>
                    @if(($deskClock['isDone'] ?? false))
                        <button type="button" disabled style="padding: 7px 14px; border: none; border-radius: 8px; background: #8A8880; color: rgba(255,255,255,0.85); font-size: 12.5px; font-weight: 600; cursor: not-allowed; opacity: 0.7;">{{ $deskClock['btnLabel'] }}</button>
                    @else
                        {{-- capture GPS on click (same as the mobile app) so a desk clock-in
                             is geofence-verified; proceeds even if permission is denied (coords → null) --}}
                        <button type="button" x-data="{ busy: false }" :disabled="busy" :style="busy ? { opacity: '0.6' } : {}"
                            @click="
                                if (busy) return;
                                busy = true;
                                const go = (la, ln, ac) => $wire.doDeskClock(la, ln, ac).finally(() => busy = false);
                                if (navigator.geolocation) {
                                    navigator.geolocation.getCurrentPosition(
                                        p => go(p.coords.latitude, p.coords.longitude, p.coords.accuracy),
                                        () => go(null, null, null),
                                        { enableHighAccuracy: false, timeout: 12000, maximumAge: 60000 }
                                    );
                                } else { go(null, null, null); }
                            "
                            style="padding: 8px 16px; border: none; border-radius: 8px; background: {{ $deskClock['isIn'] ? 'linear-gradient(180deg,#E25A4C,#D9483B)' : 'linear-gradient(180deg,#23B27C,#1F9D6B)' }}; color: #fff; font-size: 12.5px; font-weight: 700; cursor: pointer; box-shadow: 0 4px 12px {{ $deskClock['isIn'] ? 'rgba(217,72,59,0.3)' : 'rgba(31,157,107,0.35)' }};">{{ $deskClock['btnLabel'] }}</button>
                    @endif
                </div>
            @endif

            {{-- self-report status (휴가·퇴사·결근) for admins/staff, same as the mobile app --}}
            @if($deskClock['show'] ?? false)
                <div x-data="{ open: false }" @click.outside="open = false" style="position: relative;">
                    <button @click="open = !open" type="button" style="display: flex; align-items: center; gap: 7px; background: #fff; border: 1px solid #E4E2DB; border-radius: 10px; padding: 8px 13px; font-size: 12.5px; font-weight: 700; color: #C0641F; cursor: pointer;">
                        <span>🗓️</span>{{ $L['w_st_title'] }}
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div x-show="open" x-transition style="position: absolute; top: calc(100% + 6px); right: 0; z-index: 40; width: 300px; background: #fff; border: 1px solid #E4E2DB; border-radius: 14px; box-shadow: 0 12px 30px rgba(22,24,29,0.14); padding: 12px;" x-cloak>
                        <div style="font-size: 11px; color: #A7A49B; padding: 2px 4px 10px;">{{ $L['w_st_sub'] }}</div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
                            @unless($deskClock['isIn'] ?? false)
                                <button @click="open = false" wire:click="openStatusSheet('absent')" style="display: flex; flex-direction: column; align-items: center; gap: 5px; padding: 11px 6px; border-radius: 12px; border: 1.5px solid #F3CFC9; background: #FDF6F5; cursor: pointer; font-size: 12px; font-weight: 700; color: #16181D;">
                                    <span style="width: 28px; height: 28px; border-radius: 8px; background: #FBE9E7; display: flex; align-items: center; justify-content: center;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#C0392B" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg></span>{{ $L['w_st_absent'] }}
                                </button>
                            @endunless
                            <button @click="open = false" wire:click="openStatusSheet('leave')" style="display: flex; flex-direction: column; align-items: center; gap: 5px; padding: 11px 6px; border-radius: 12px; border: 1.5px solid #CBDBF5; background: #F6F9FE; cursor: pointer; font-size: 12px; font-weight: 700; color: #16181D;">
                                <span style="width: 28px; height: 28px; border-radius: 8px; background: #E9F1FB; display: flex; align-items: center; justify-content: center;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#3B72E0" stroke-width="2"><path d="M3 8h18M7 3v3M17 3v3M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/></svg></span>{{ $L['w_st_leave'] }}
                            </button>
                            <button @click="open = false" wire:click="openStatusSheet('resign')" style="display: flex; flex-direction: column; align-items: center; gap: 5px; padding: 11px 6px; border-radius: 12px; border: 1.5px solid #DDD9CF; background: #FAFAF8; cursor: pointer; font-size: 12px; font-weight: 700; color: #16181D;">
                                <span style="width: 28px; height: 28px; border-radius: 8px; background: #ECEBE6; display: flex; align-items: center; justify-content: center;"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6B6E76" stroke-width="2"><path d="M16 17l5-5-5-5M21 12H9M13 21H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7"/></svg></span>{{ $L['w_st_resign'] }}
                            </button>
                        </div>
                    </div>
                </div>
            @endif
            {{-- bell and date now live in the top bar (next to the language switcher / logo) --}}
        </div>
        @include('livewire.partials.status-sheets')

        <div class="wf-content" style="flex: 1; padding: 26px 30px; overflow: auto;">
            @if($screen === 'dashboard')
                @include('livewire.partials.dashboard')
            @elseif($screen === 'comms')
                @include('livewire.partials.comms')
            @elseif($screen === 'projects')
                @include('livewire.partials.projects')
            @elseif($screen === 'employees')
                @include('livewire.partials.employees')
            @elseif($screen === 'badge')
                @include('livewire.partials.badge')
            @elseif($screen === 'attendance')
                @include('livewire.partials.attendance')
            @elseif($screen === 'payroll')
                @include('livewire.partials.payroll')
            @elseif($screen === 'accounting')
                @include('livewire.partials.accounting')
            @endif
        </div>
    </main>

    {{-- mobile bottom tab menu (admin / site-manager) — shown only under 820px --}}
    <nav class="wf-bottomnav">
        @foreach($nav as $item)
            <button wire:click="go('{{ $item['key'] }}')" class="{{ $item['active'] ? 'active' : '' }}" style="position: relative;">
                <span class="wf-navicon">{!! $Icon::nav($item['key']) !!}</span>
                <span class="wf-navlabel">{{ $item['label'] }}</span>
                @if(($item['unread'] ?? 0) > 0)
                    <span style="position: absolute; top: 2px; right: 50%; margin-right: -20px; min-width: 16px; height: 16px; padding: 0 4px; border-radius: 8px; background: #E85D2A; color: #fff; font-size: 10px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center;">{{ $item['unread'] > 99 ? '99+' : $item['unread'] }}</span>
                @endif
            </button>
        @endforeach
    </nav>
</div>
