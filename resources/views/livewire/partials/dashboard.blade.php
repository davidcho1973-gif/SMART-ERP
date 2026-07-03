@php $d = $dash; @endphp
{{-- ============ DASHBOARD (layout A) ============ --}}
<div>
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px;">
        <div style="background: #16181D; color: #fff; border-radius: 16px; padding: 20px;">
            <div style="font-size: 12.5px; color: rgba(255,255,255,0.6);">{{ $L['d_onsite'] }}</div>
            <div style="font-family: 'Space Grotesk'; font-size: 40px; font-weight: 700; margin-top: 6px; line-height: 1;">{{ $d['onsite'] }}<span style="font-size: 18px; color: rgba(255,255,255,0.4);"> / {{ $d['totalActive'] }}</span></div>
            <div style="display: flex; align-items: center; gap: 6px; margin-top: 12px;"><span style="flex: 1; height: 6px; background: rgba(255,255,255,0.15); border-radius: 4px; overflow: hidden;"><span style="display: block; height: 100%; width: {{ $d['rate'] }}%; background: #E85D2A; border-radius: 4px;"></span></span><span style="font-family: 'Space Grotesk'; font-size: 13px; font-weight: 600;">{{ $d['rate'] }}%</span></div>
        </div>
        <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; padding: 20px;">
            <div style="font-size: 12.5px; color: #8A8880;">{{ $L['d_present'] }} · {{ $L['d_late'] }}</div>
            <div style="display: flex; align-items: baseline; gap: 14px; margin-top: 8px;"><span style="font-family: 'Space Grotesk'; font-size: 34px; font-weight: 700; color: #1F9D6B;">{{ $d['cnt']['present'] }}</span><span style="font-family: 'Space Grotesk'; font-size: 34px; font-weight: 700; color: #E8A33D;">{{ $d['cnt']['late'] }}</span></div>
            <div style="font-size: 11.5px; color: #A7A49B; margin-top: 6px;">{{ $L['d_present'] }} / {{ $L['d_late'] }}</div>
        </div>
        <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; padding: 20px;">
            <div style="font-size: 12.5px; color: #8A8880;">{{ $L['d_payroll'] }}</div>
            <div style="font-family: 'Space Grotesk'; font-size: 26px; font-weight: 700; margin-top: 8px;">{{ $d['periodPay'] }}</div>
            <div style="font-size: 11.5px; color: #A7A49B; margin-top: 6px;">{{ $d['payPeriod'] }}</div>
        </div>
        <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; padding: 20px;">
            <div style="font-size: 12.5px; color: #8A8880;">{{ $L['d_avgh'] }}</div>
            <div style="font-family: 'Space Grotesk'; font-size: 34px; font-weight: 700; margin-top: 8px;">{{ $d['avgH'] }}<span style="font-size: 16px; color: #A7A49B;">h</span></div>
            <div style="font-size: 11.5px; color: #A7A49B; margin-top: 6px;">{{ $L['d_perWeek'] }}</div>
        </div>
    </div>
    <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 16px; margin-top: 16px;">
        <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; padding: 22px;">
            <div style="font-weight: 600; font-size: 15px; margin-bottom: 16px;">{{ $L['d_byteam'] }}</div>
            @foreach($d['teamStats'] as $tm)
                <div style="display: flex; align-items: center; gap: 14px; padding: 11px 0; border-bottom: 1px solid #F0EEE8;">
                    <span style="width: 10px; height: 10px; border-radius: 3px; background: {{ $tm['color'] }};"></span>
                    <span style="width: 150px; font-size: 13px;"><span style="font-weight: 500;">{{ $tm['name'] }}</span><span style="display: block; font-size: 11px; color: #A7A49B;">{{ $tm['company'] }}</span></span>
                    <span style="flex: 1; height: 8px; background: #F0EEE8; border-radius: 5px; overflow: hidden;"><span style="display: block; height: 100%; width: {{ $tm['pct'] }}%; background: {{ $tm['color'] }}; border-radius: 5px;"></span></span>
                    <span style="font-family: 'Space Grotesk'; font-size: 13px; font-weight: 600; width: 52px; text-align: right;">{{ $tm['present'] }}/{{ $tm['total'] }}</span>
                </div>
            @endforeach
        </div>
        <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; padding: 22px;">
            <div style="font-weight: 600; font-size: 15px; margin-bottom: 14px;">{{ $L['d_recent'] }}</div>
            @foreach($d['recent'] as $r)
                <div style="display: flex; align-items: center; gap: 11px; padding: 10px 0; border-bottom: 1px solid #F0EEE8;">
                    <span style="width: 8px; height: 8px; border-radius: 50%; background: {{ $r['c'] }}; flex-shrink: 0;"></span>
                    <span style="flex: 1; font-size: 13px; color: #3A3D44;">{{ $r['txt'] }}</span>
                    <span style="font-family: 'Space Grotesk'; font-size: 11px; color: #A7A49B; background: #F4F3EF; padding: 2px 7px; border-radius: 6px;">{{ $r['tag'] }}</span>
                </div>
            @endforeach
        </div>
    </div>
</div>
