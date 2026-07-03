@php $Ui = \App\Support\Ui::class; @endphp
{{-- ============ ATTENDANCE / QR ============ --}}
<div>
    <div style="display: flex; gap: 14px; margin-bottom: 20px;">
        <button wire:click="setQrMode('reader')" style="{{ $Ui::tile($qrMode==='reader') }}"><div style="display: flex; align-items: center; gap: 10px;"><span style="width: 34px; height: 34px; border-radius: 9px; background: #FDF0EA; display: inline-flex; align-items: center; justify-content: center;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#E85D2A" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3M20 20v.01M17 20v.01M20 17v.01"/></svg></span><span style="font-size: 14px; font-weight: 600;">{{ $L['q_reader'] }}</span></div><div style="font-size: 12px; color: #8A8880; margin-top: 8px; line-height: 1.4;">{{ $L['q_readerd'] }}</div></button>
        <button wire:click="setQrMode('site')" style="{{ $Ui::tile($qrMode==='site') }}"><div style="display: flex; align-items: center; gap: 10px;"><span style="width: 34px; height: 34px; border-radius: 9px; background: #E9F1FB; display: inline-flex; align-items: center; justify-content: center;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3B72E0" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="3"/><path d="M11 18h2"/></svg></span><span style="font-size: 14px; font-weight: 600;">{{ $L['q_site'] }}</span></div><div style="font-size: 12px; color: #8A8880; margin-top: 8px; line-height: 1.4;">{{ $L['q_sited'] }}</div></button>
        <button wire:click="setQrMode('manual')" style="{{ $Ui::tile($qrMode==='manual') }}"><div style="display: flex; align-items: center; gap: 10px;"><span style="width: 34px; height: 34px; border-radius: 9px; background: #E7F4EE; display: inline-flex; align-items: center; justify-content: center;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1F9D6B" stroke-width="2"><path d="M9 11l3 3 8-8"/><path d="M20 12v6a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h9"/></svg></span><span style="font-size: 14px; font-weight: 600;">{{ $L['q_manual'] }}</span></div><div style="font-size: 12px; color: #8A8880; margin-top: 8px; line-height: 1.4;">{{ $L['q_manuald'] }}</div></button>
    </div>

    @if($qrMode === 'manual')
        <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; overflow: hidden;">
            @foreach($att['qrManualRows'] as $e)
                <div style="display: flex; align-items: center; gap: 14px; padding: 13px 20px; border-bottom: 1px solid #F2F0EA;">
                    <span style="display: inline-flex; width: 34px; height: 34px; border-radius: 50%; background: {{ $e['teamColor'] }}; color: #fff; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; font-family: 'Space Grotesk';">{{ $e['initials'] }}</span>
                    <span style="flex: 1;"><span style="font-weight: 600; font-size: 14px;">{{ $e['name'] }}</span><span style="display: block; font-size: 12px; color: #A7A49B;">{{ $e['teamName'] }} · {{ $e['role'] }}</span></span>
                    <span style="font-size: 12px; font-weight: 600; color: {{ $e['statusColor'] }}; background: {{ $e['statusBg'] }}; padding: 4px 9px; border-radius: 7px;">{{ $e['statusLabel'] }} {{ $e['inT'] }}</span>
                    <button wire:click="manualPunch({{ $e['id'] }}, 'in')" style="padding: 7px 14px; border: none; border-radius: 8px; background: #1F9D6B; color: #fff; font-size: 12.5px; font-weight: 600; cursor: pointer;">{{ $L['q_in'] }}</button>
                    <button wire:click="manualPunch({{ $e['id'] }}, 'out')" style="padding: 7px 14px; border: 1px solid #E4E2DB; border-radius: 8px; background: #fff; color: #5A5D64; font-size: 12.5px; font-weight: 600; cursor: pointer;">{{ $L['q_out'] }}</button>
                </div>
            @endforeach
        </div>
    @else
        <div style="display: grid; grid-template-columns: 360px 1fr; gap: 20px; align-items: start;">
            <div style="background: #16181D; border-radius: 18px; padding: 28px; color: #fff; text-align: center;">
                <div style="font-size: 13px; color: rgba(255,255,255,0.6); margin-bottom: 4px;">{{ $L['q_teamQr'] }}</div>
                <div style="font-size: 12px; color: #E85D2A; margin-bottom: 18px;">{{ $att['selQr']['company'] }}</div>
                <div style="width: 210px; height: 210px; margin: 0 auto; background: #fff; border-radius: 16px; padding: 16px;">{!! $att['teamQrSvg'] !!}</div>
                <div style="margin-top: 16px;"><div style="font-size: 16px; font-weight: 700;">{{ $att['selQr']['team'] }}</div><div style="font-size: 12.5px; color: rgba(255,255,255,0.55); margin-top: 2px;">{{ $L['pj_lead'] }} · {{ $att['selQr']['lead'] }}</div></div>
                <button wire:click="printQr" style="margin-top: 18px; width: 100%; padding: 12px; border: none; border-radius: 11px; background: #E85D2A; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2M6 14h12v7H6z"/></svg>{{ $L['q_print'] }}</button>
            </div>
            <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 18px; padding: 22px;">
                <div style="font-weight: 600; font-size: 15px; margin-bottom: 4px;">{{ $L['q_teamList'] }}</div>
                @foreach($att['qrGroups'] as $g)
                    <div style="margin-top: 14px;">
                        <div style="font-size: 12px; font-weight: 700; color: #8A8880;">{{ $g['company'] }}</div>
                        @foreach($g['teams'] as $t)
                            <div wire:click="selectQrTeam('{{ $t['id'] }}')" style="{{ $Ui::qrRow($t['active']) }}">
                                <span style="width: 10px; height: 10px; border-radius: 3px; background: {{ $t['color'] }}; flex-shrink: 0;"></span>
                                <span style="flex: 1;"><span style="font-weight: 600; font-size: 13.5px;">{{ $t['name'] }}</span><span style="display: block; font-size: 12px; color: #A7A49B;">{{ $L['pj_lead'] }} · {{ $t['lead'] }}</span></span>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#C7C4BB" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3M20 20v.01M17 20v.01M20 17v.01"/></svg>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
