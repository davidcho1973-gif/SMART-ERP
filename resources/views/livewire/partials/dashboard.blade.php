@php $d = $dash; @endphp
{{-- ============ DASHBOARD · Command (A) + site cards (C) ============ --}}
<div style="display: flex; flex-direction: column; gap: 16px;">

    {{-- ---- KPI strip (auto-wraps on mobile, never clipped) ---- --}}
    <div class="wf-kpis" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 14px;">
        {{-- on site now (hero) --}}
        <div style="background: #16181D; color: #fff; border-radius: 16px; padding: 18px;">
            <div style="font-size: 12px; color: rgba(255,255,255,0.6);">{{ $L['d_onsite'] }}</div>
            <div style="font-family: 'Space Grotesk'; font-size: 32px; font-weight: 700; margin-top: 4px; line-height: 1;">{{ $d['onsite'] }}<span style="font-size: 16px; color: rgba(255,255,255,0.4);"> / {{ $d['totalActive'] }}</span></div>
            <div style="display: flex; align-items: center; gap: 8px; margin-top: 12px;"><span style="flex: 1; height: 6px; background: rgba(255,255,255,0.15); border-radius: 4px; overflow: hidden;"><span style="display: block; height: 100%; width: {{ $d['rate'] }}%; background: #E85D2A; border-radius: 4px;"></span></span><span style="font-family: 'Space Grotesk'; font-size: 12.5px; font-weight: 600;">{{ $d['rate'] }}%</span></div>
        </div>
        {{-- present · late · absent --}}
        <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; padding: 18px;">
            <div style="font-size: 12px; color: #8A8880;">{{ $L['d_present'] }} · {{ $L['d_late'] }} · {{ $L['d_absent'] }}</div>
            <div style="display: flex; align-items: baseline; gap: 12px; margin-top: 8px; font-family: 'Space Grotesk'; font-size: 30px; font-weight: 700;">
                <span style="color: #1F9D6B;">{{ $d['cnt']['present'] }}</span><span style="color: #E8A33D;">{{ $d['cnt']['late'] }}</span><span style="color: #D9483B;">{{ $d['cnt']['absent'] }}</span>
            </div>
            <div style="font-size: 11px; color: #A7A49B; margin-top: 6px;">{{ $d['cnt']['off'] }} {{ $L['d_off'] }}</div>
        </div>
        {{-- GPS off-site --}}
        <div style="background: #fff; border: 1px solid {{ $d['offCount'] > 0 ? '#F3D9CB' : '#E4E2DB' }}; border-radius: 16px; padding: 18px;">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 6px;"><span style="font-size: 12px; color: #8A8880;">{{ $L['d_offToday'] }}</span><span style="font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 6px; background: #FBE9E7; color: #C0522B;">GPS</span></div>
            <div style="font-family: 'Space Grotesk'; font-size: 30px; font-weight: 700; margin-top: 8px; color: {{ $d['offCount'] > 0 ? '#C0522B' : '#16181D' }};">{{ $d['offCount'] }}</div>
            <div style="font-size: 11px; color: #A7A49B; margin-top: 6px;">{{ $L['d_outRadius'] }}</div>
        </div>
        {{-- correction requests --}}
        <div style="background: #fff; border: 1px solid {{ $d['pendCount'] > 0 ? '#F3E2C2' : '#E4E2DB' }}; border-radius: 16px; padding: 18px;">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 6px;"><span style="font-size: 12px; color: #8A8880;">{{ $L['d_corrTitle'] }}</span>@if($d['pendCount'] > 0)<span style="font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 6px; background: #FBF1DF; color: #8A6A2E;">{{ $L['d_review'] }}</span>@endif</div>
            <div style="font-family: 'Space Grotesk'; font-size: 30px; font-weight: 700; margin-top: 8px; color: {{ $d['pendCount'] > 0 ? '#8A6A2E' : '#16181D' }};">{{ $d['pendCount'] }}</div>
            <div style="font-size: 11px; color: #A7A49B; margin-top: 6px;">{{ $L['d_awaiting'] }}</div>
        </div>
        {{-- est payroll --}}
        <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; padding: 18px;">
            <div style="font-size: 12px; color: #8A8880;">{{ $L['d_payroll'] }}</div>
            <div style="font-family: 'Space Grotesk'; font-size: 24px; font-weight: 700; margin-top: 8px;">{{ $d['periodPay'] }}</div>
            <div style="font-size: 11px; color: #A7A49B; margin-top: 6px;">{{ $d['payPeriod'] }}</div>
        </div>
    </div>

    {{-- ---- per-site cards ---- --}}
    @if(!empty($d['siteCards']))
        <div>
            <div style="font-size: 12.5px; font-weight: 700; color: #8A8880; margin: 2px 2px 10px;">{{ $L['d_sitesTitle'] }}</div>
            <div class="wf-sitecards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 14px;">
                @foreach($d['siteCards'] as $c)
                    @php $rc = $c['pct'] >= 80 ? '#1F9D6B' : ($c['pct'] >= 50 ? '#E8A33D' : '#D9483B'); @endphp
                    <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; overflow: hidden;">
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 15px 16px; border-bottom: 1px solid #F2F0EA;">
                            <div style="min-width: 0;"><div style="font-size: 14.5px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $c['name'] }}</div><div style="font-size: 11.5px; color: #8A8880; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $c['city'] ?: '—' }}</div></div>
                            <div style="position: relative; width: 46px; height: 46px; border-radius: 50%; flex-shrink: 0; background: conic-gradient({{ $rc }} {{ $c['pct'] }}%, #EFECE6 0); display: grid; place-items: center;">
                                <span style="position: absolute; width: 36px; height: 36px; border-radius: 50%; background: #fff;"></span>
                                <span style="position: relative; font-family: 'Space Grotesk'; font-size: 11px; font-weight: 700;">{{ $c['pct'] }}%</span>
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1px; background: #F2F0EA;">
                            <div style="background: #fff; padding: 11px 16px;"><div style="font-size: 10px; text-transform: uppercase; letter-spacing: .04em; color: #A7A49B; font-weight: 700;">{{ $L['d_onsite'] }}</div><div style="font-family: 'Space Grotesk'; font-size: 18px; font-weight: 700;">{{ $c['onsite'] }}/{{ $c['total'] }}</div></div>
                            <div style="background: #fff; padding: 11px 16px;"><div style="font-size: 10px; text-transform: uppercase; letter-spacing: .04em; color: #A7A49B; font-weight: 700;">{{ $L['ts_offsite'] }}</div><div style="font-family: 'Space Grotesk'; font-size: 18px; font-weight: 700; color: {{ $c['off'] > 0 ? '#C0522B' : '#16181D' }};">{{ $c['off'] }}</div></div>
                            <div style="background: #fff; padding: 11px 16px;"><div style="font-size: 10px; text-transform: uppercase; letter-spacing: .04em; color: #A7A49B; font-weight: 700;">{{ $L['d_crews'] }}</div><div style="font-family: 'Space Grotesk'; font-size: 18px; font-weight: 700;">{{ $c['crews'] }}</div></div>
                            <div style="background: #fff; padding: 11px 16px;"><div style="font-size: 10px; text-transform: uppercase; letter-spacing: .04em; color: #A7A49B; font-weight: 700;">{{ $L['d_payroll'] }}</div><div style="font-family: 'Space Grotesk'; font-size: 16px; font-weight: 700;">{{ $c['pay'] }}</div></div>
                        </div>
                        @if($c['off'] > 0 || $c['fixes'] > 0)
                            <div style="display: flex; flex-wrap: wrap; gap: 6px; padding: 11px 16px;">
                                @if($c['off'] > 0)<span style="font-size: 10.5px; font-weight: 700; padding: 3px 8px; border-radius: 7px; background: #FBE9E7; color: #C0522B;">{{ $c['off'] }} {{ $L['ts_offsite'] }}</span>@endif
                                @if($c['fixes'] > 0)<span style="font-size: 10.5px; font-weight: 700; padding: 3px 8px; border-radius: 7px; background: #FBF1DF; color: #8A6A2E;">{{ $c['fixes'] }} {{ $L['d_reqFix'] }}</span>@endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ---- attendance by crew  +  needs attention ---- --}}
    <div class="wf-2col" style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 16px;">
        <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; padding: 22px;">
            <div style="font-weight: 700; font-size: 15px;">{{ $L['d_byteam'] }}</div>
            {{-- one colour per status, used on every chip below --}}
            <div style="display: flex; flex-wrap: wrap; gap: 9px 12px; margin: 10px 0 6px; padding-bottom: 13px; border-bottom: 1px solid #F0EEE8;">
                @foreach([['#1F9D6B','st_working'],['#5A5D64','st_done'],['#E8A33D','st_early'],['#D9483B','st_absentee'],['#3B72E0','st_leave'],['#B7B4AB','st_missing'],['#A7A49B','st_resigned']] as [$cc,$k])
                    <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 11px; color: #5A5D64; font-weight: 600;"><span style="width: 8px; height: 8px; border-radius: 50%; background: {{ $cc }};"></span>{{ $L[$k] }}</span>
                @endforeach
            </div>
            @forelse($d['companyStats'] as $co)
                <div style="margin-top: 15px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; padding-bottom: 7px; border-bottom: 2px solid #EFECE6;">
                        <span style="font-size: 13.5px; font-weight: 800; color: #16181D;">{{ $co['company'] }}</span>
                        <span style="font-family: 'Space Grotesk'; font-size: 12px; font-weight: 700; color: #1F9D6B;">{{ $co['present'] }} {{ $L['st_working'] }} / {{ $co['total'] }}</span>
                    </div>
                    @foreach($co['teams'] as $tm)
                        <div style="margin-top: 11px;">
                            {{-- team header + colour-count tally: read the crew at a glance --}}
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="width: 9px; height: 9px; border-radius: 3px; background: {{ $tm['color'] }}; flex-shrink: 0;"></span>
                                <span style="font-size: 13px; font-weight: 700;">{{ $tm['name'] }}</span>
                                <span style="display: flex; gap: 5px; margin-left: auto; flex-wrap: wrap; justify-content: flex-end;">
                                    @foreach([['working','#E7F4EE','#1F9D6B'],['leave','#E9F1FB','#3B72E0'],['early','#FBF1DF','#C17A1A'],['unexcused','#FBE9E7','#C0392B'],['absent','#FBE9E7','#C0392B'],['missing','#FBEDE7','#C0522B']] as [$sk,$bg,$fg])
                                        @if(($tm['tally'][$sk] ?? 0) > 0)<span style="display: inline-flex; align-items: center; gap: 3px; font-size: 11px; font-weight: 700; font-family: 'Space Grotesk'; padding: 2px 7px; border-radius: 7px; background: {{ $bg }}; color: {{ $fg }};">● {{ $tm['tally'][$sk] }}</span>@endif
                                    @endforeach
                                </span>
                            </div>
                            @foreach($tm['workers'] as $w)
                                @php $st = $w['status']; @endphp
                                <div style="display: flex; align-items: center; gap: 10px; padding: 8px 4px 8px 17px; border-bottom: 1px solid #F5F3EE;">
                                    <span style="width: 27px; height: 27px; border-radius: 50%; background: {{ $st['key']==='terminated' ? '#C7C4BB' : $tm['color'] }}; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 10.5px; font-weight: 700; font-family: 'Space Grotesk'; flex-shrink: 0;">{{ $w['initials'] }}</span>
                                    <span style="flex: 1; min-width: 0;">
                                        <span style="font-size: 13px; font-weight: 600; {{ $st['key']==='terminated' ? 'color:#A7A49B;text-decoration:line-through;text-decoration-color:#D8D5CD;' : '' }}">{{ $w['name'] }}</span>@if($w['pendingResign'] && $st['key']!=='terminated')<span style="margin-left: 6px; font-size: 10px; font-weight: 700; color: #8A6A2E; background: #FBF1DF; padding: 1px 6px; border-radius: 5px;">{{ $L['st_resignPending'] }}</span>@endif
                                        @if($st['detail'])<span style="display: block; font-size: 11px; margin-top: 1px; color: {{ in_array($st['key'],['early','unexcused','absent','missing','leave']) ? $st['color'] : '#9A968C' }};">{{ $st['detail'] }}</span>@endif
                                    </span>
                                    <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 11.5px; font-weight: 700; padding: 3px 10px; border-radius: 999px; white-space: nowrap; flex-shrink: 0; background: {{ $st['bg'] }}; color: {{ $st['color'] }};"><span style="width: 6px; height: 6px; border-radius: 50%; background: {{ $st['dot'] }};"></span>{{ $st['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @empty
                <div style="padding: 24px 0; text-align: center; color: #A7A49B; font-size: 13px;">—</div>
            @endforelse
        </div>

        {{-- needs attention: corrections to approve + GPS off-site --}}
        <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; padding: 22px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                <div style="font-weight: 700; font-size: 15px;">{{ $L['d_attn'] }}</div>
                @php $attn = $d['pendCount'] + $d['offCount'] + count($d['repeatNoShow']); @endphp
                @if($attn > 0)
                    <button wire:click="go('attendance')" style="font-size: 11.5px; font-weight: 700; color: #C0522B; background: #FBE9E7; border: none; padding: 3px 10px; border-radius: 999px; cursor: pointer;">{{ $attn }} · {{ $L['d_review'] }}</button>
                @endif
            </div>
            @if($attn === 0)
                <div style="display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 30px 0; color: #A7A49B; text-align: center;">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#8FBFA6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg>
                    <span style="font-size: 12.5px;">{{ $L['d_allClear'] }}</span>
                </div>
            @else
                @foreach($d['repeatNoShow'] as $r)
                    {{-- repeat no-call-no-show (무단결근 N회) → resignation review --}}
                    <div style="display: flex; align-items: center; gap: 10px; padding: 11px 0; border-top: 1px solid #F2F0EA;">
                        <span style="font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 6px; background: #FBE0DC; color: #B02A1B; flex-shrink: 0;">{{ $L['d_noShow'] }}</span>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $r['name'] }}</div>
                            <div style="font-size: 11.5px; color: #C0392B;">{{ $L['d_noShowMsg'] }} · <b>{{ $r['count'] }}{{ $L['d_noShowTimes'] }}</b> ({{ $d['noShowThreshold'] }}{{ $L['d_noShowTimes'] }}+)</div>
                        </div>
                    </div>
                @endforeach
                @foreach($d['pendList'] as $p)
                    <div style="display: flex; align-items: center; gap: 10px; padding: 11px 0; border-top: 1px solid #F2F0EA;">
                        <span style="font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 6px; background: #FBF1DF; color: #8A6A2E; flex-shrink: 0;">{{ $p['isDelete'] ? $L['d_delReq'] : $L['d_reqFix'] }}</span>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $p['name'] }} · {{ $p['date'] }}</div>
                            <div style="font-size: 11.5px; color: #8A8880; font-family: 'Space Grotesk';">{{ $p['isDelete'] ? '—' : $p['reqIn'].' – '.$p['reqOut'] }} · {{ $p['team'] }}</div>
                        </div>
                    </div>
                @endforeach
                @foreach($d['offList'] as $o)
                    <div style="display: flex; align-items: center; gap: 10px; padding: 11px 0; border-top: 1px solid #F2F0EA;">
                        <span style="font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 6px; background: #FBE9E7; color: #C0522B; flex-shrink: 0;">{{ $L['ts_offsite'] }}</span>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $o['name'] }}</div>
                            <div style="font-size: 11.5px; color: #8A8880;">@if($o['dist'])<b style="color: #C0522B;">{{ $o['dist'] }}</b> {{ $L['d_outside'] }} @endif{{ $o['site'] }} · {{ $o['time'] }}</div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    {{-- ---- recent activity ---- --}}
    <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; padding: 22px;">
        <div style="font-weight: 700; font-size: 15px; margin-bottom: 8px;">{{ $L['d_recent'] }}</div>
        @foreach($d['recent'] as $r)
            <div style="display: flex; align-items: center; gap: 11px; padding: 10px 0; border-bottom: 1px solid #F0EEE8;">
                <span style="width: 8px; height: 8px; border-radius: 50%; background: {{ $r['c'] }}; flex-shrink: 0;"></span>
                <span style="flex: 1; min-width: 0; font-size: 13px; color: #3A3D44; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $r['txt'] }}</span>
                <span style="font-family: 'Space Grotesk'; font-size: 11px; color: #A7A49B; background: #F4F3EF; padding: 2px 7px; border-radius: 6px; flex-shrink: 0;">{{ $r['tag'] }}</span>
            </div>
        @endforeach
    </div>
</div>
