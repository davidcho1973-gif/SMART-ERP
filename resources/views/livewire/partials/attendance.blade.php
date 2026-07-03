@php $Ui = \App\Support\Ui::class; @endphp
{{-- ============ ATTENDANCE ============ --}}
@php $ts = $att['timesheet']; @endphp
<div>
    {{-- view toggle: daily records table | QR tools --}}
    <div style="display: flex; gap: 8px; margin-bottom: 20px;">
        <button wire:click="setAttView('records')" style="padding: 9px 18px; border-radius: 10px; border: 1px solid {{ $att['view']==='records' ? '#16181D' : '#E4E2DB' }}; background: {{ $att['view']==='records' ? '#16181D' : '#fff' }}; color: {{ $att['view']==='records' ? '#fff' : '#5A5D64' }}; font-size: 13.5px; font-weight: 600; cursor: pointer;">{{ $L['ts_records'] }}</button>
        <button wire:click="setAttView('qr')" style="padding: 9px 18px; border-radius: 10px; border: 1px solid {{ $att['view']==='qr' ? '#16181D' : '#E4E2DB' }}; background: {{ $att['view']==='qr' ? '#16181D' : '#fff' }}; color: {{ $att['view']==='qr' ? '#fff' : '#5A5D64' }}; font-size: 13.5px; font-weight: 600; cursor: pointer;">{{ $L['ts_qr'] }}</button>
    </div>

    @if($att['view'] === 'records')
        {{-- date picker + daily summary --}}
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-bottom: 16px;">
            <label style="display: flex; align-items: center; gap: 9px; background: #fff; border: 1px solid #E4E2DB; border-radius: 10px; padding: 9px 13px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#8A8880" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                <input type="date" wire:model.live="attDate" style="border: none; outline: none; background: transparent; font-size: 13.5px; font-weight: 600; color: #16181D; cursor: pointer;"/>
            </label>
            <div style="flex: 1;"></div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <span style="background: #fff; border: 1px solid #E4E2DB; border-radius: 10px; padding: 9px 13px; font-size: 12.5px; color: #5A5D64;">{{ $L['ts_present'] }} <b style="color:#16181D;">{{ $ts['present'] }}</b> / {{ $ts['count'] }}</span>
                <span style="background: #fff; border: 1px solid #E4E2DB; border-radius: 10px; padding: 9px 13px; font-size: 12.5px; color: #5A5D64;">{{ $L['ts_reg'] }} <b style="color:#16181D;">{{ $ts['regTotal'] }}</b></span>
                <span style="background: #FDF0EA; border: 1px solid #F3D9CB; border-radius: 10px; padding: 9px 13px; font-size: 12.5px; color: #C05621;">{{ $L['ts_ot'] }} <b>{{ $ts['otTotal'] }}</b></span>
            </div>
        </div>

        <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; min-width: 780px; font-size: 13px;">
                <thead>
                    <tr style="text-align: left; color: #8A8880; font-size: 11px; text-transform: uppercase; letter-spacing: 0.03em; background: #FAFAF8;">
                        <th style="padding: 12px 14px; font-weight: 600;">{{ $L['ts_company'] }}</th>
                        <th style="padding: 12px 14px; font-weight: 600;">{{ $L['ts_team'] }}</th>
                        <th style="padding: 12px 14px; font-weight: 600;">{{ $L['ts_name'] }}</th>
                        <th style="padding: 12px 14px; font-weight: 600;">{{ $L['ts_actIn'] }}</th>
                        <th style="padding: 12px 14px; font-weight: 600;">{{ $L['ts_actOut'] }}</th>
                        <th style="padding: 12px 14px; font-weight: 600;">{{ $L['ts_paidIn'] }}</th>
                        <th style="padding: 12px 14px; font-weight: 600;">{{ $L['ts_paidOut'] }}</th>
                        <th style="padding: 12px 14px; font-weight: 600; text-align: right;">{{ $L['ts_reg'] }}</th>
                        <th style="padding: 12px 14px; font-weight: 600; text-align: right;">{{ $L['ts_ot'] }}</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($ts['rows'] as $r)
                    <tr style="border-top: 1px solid #F2F0EA;">
                        <td style="padding: 11px 14px; color: #5A5D64;">{{ $r['company'] }}</td>
                        <td style="padding: 11px 14px;"><span style="display: inline-flex; align-items: center; gap: 7px;"><span style="width: 9px; height: 9px; border-radius: 3px; background: {{ $r['teamColor'] }};"></span>{{ $r['team'] }}</span></td>
                        <td style="padding: 11px 14px; font-weight: 600;">{{ $r['name'] }}</td>
                        <td style="padding: 11px 14px; font-family: 'Space Grotesk';">{{ $r['actIn'] }}</td>
                        <td style="padding: 11px 14px; font-family: 'Space Grotesk';">@if($r['onDuty'])<span style="color: #1F9D6B; font-weight: 600;">{{ $L['ts_onduty'] }}</span>@else{{ $r['actOut'] }}@endif</td>
                        <td style="padding: 11px 14px; font-family: 'Space Grotesk'; color: #8A8880;">{{ $r['paidIn'] }}</td>
                        <td style="padding: 11px 14px; font-family: 'Space Grotesk'; color: #8A8880;">{{ $r['paidOut'] }}</td>
                        <td style="padding: 11px 14px; text-align: right; font-family: 'Space Grotesk'; font-weight: 600;">{{ $r['reg'] }}</td>
                        <td style="padding: 11px 14px; text-align: right; font-family: 'Space Grotesk'; font-weight: 600; color: {{ $r['ot'] !== '—' ? '#C05621' : '#C7C4BB' }};">{{ $r['ot'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" style="padding: 34px; text-align: center; color: #A7A49B; font-size: 13.5px;">{{ $L['ts_none'] }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="font-size: 11.5px; color: #A7A49B; margin-top: 12px; line-height: 1.5;">{{ $L['ts_note'] }}</div>

    @else
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
    @endif
</div>
