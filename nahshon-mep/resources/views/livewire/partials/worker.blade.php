@php
    $Ui = \App\Support\Ui::class;
    $Icon = \App\Support\Icons::class;
    $w = $worker['me'];
    $clockedIn = $clock === 'in';
    $clockBtnStyle = $clockedIn
        ? 'width:100%;padding:20px;border:none;border-radius:20px;background:#D9483B;color:#fff;font-size:19px;font-weight:700;cursor:pointer;box-shadow:0 10px 24px rgba(217,72,59,0.3);'
        : 'width:100%;padding:20px;border:none;border-radius:20px;background:#1F9D6B;color:#fff;font-size:19px;font-weight:700;cursor:pointer;box-shadow:0 10px 24px rgba(31,157,107,0.35);';
    $statusPillBg = $clockedIn ? 'rgba(74,222,128,0.15)' : 'rgba(255,255,255,0.1)';
    $statusPillColor = $clockedIn ? '#4ADE80' : 'rgba(255,255,255,0.7)';
    $noLunchRowStyle = 'display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:12px;padding:12px 14px;border-radius:14px;background:'.($noLunchToday?'rgba(74,222,128,0.12)':'rgba(255,255,255,0.06)').';border:1px solid '.($noLunchToday?'rgba(74,222,128,0.4)':'rgba(255,255,255,0.14)').';';
    $noLunchSwStyle = 'width:44px;height:26px;border-radius:20px;border:none;cursor:pointer;position:relative;flex-shrink:0;transition:background .15s;background:'.($noLunchToday?'#4ADE80':'rgba(255,255,255,0.25)').';';
    $noLunchKnobStyle = 'position:absolute;top:3px;left:'.($noLunchToday?'21px':'3px').';width:20px;height:20px;border-radius:50%;background:#fff;transition:left .15s;';
    $earlyIsCustom = $earlyReasonVal === '__custom__';
@endphp
{{-- ===== WORKER MOBILE ===== --}}
<div style="min-height: calc(100vh - 44px); display: flex; align-items: flex-start; justify-content: center; padding: 30px 16px;">
    <div style="width: 390px; max-width: 100%; background: #000; border-radius: 44px; padding: 11px; box-shadow: 0 30px 70px rgba(0,0,0,0.35);">
        <div style="position: relative; background: #F4F3EF; border-radius: 34px; overflow: hidden; height: 800px; display: flex; flex-direction: column;">
            <div style="position: absolute; top: 0; left: 50%; transform: translateX(-50%); width: 130px; height: 26px; background: #000; border-radius: 0 0 16px 16px; z-index: 30;"></div>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 24px 6px; font-size: 12px; font-weight: 600; color: #16181D;">
                <span style="font-family: 'Space Grotesk';">9:41</span>
                <span style="display: flex; gap: 5px; align-items: center;"><svg width="15" height="11" viewBox="0 0 18 12" fill="#16181D"><rect x="0" y="7" width="3" height="5" rx="1"/><rect x="5" y="4" width="3" height="8" rx="1"/><rect x="10" y="1.5" width="3" height="10.5" rx="1"/><rect x="15" y="0" width="3" height="12" rx="1" opacity=".3"/></svg><svg width="18" height="11" viewBox="0 0 24 16" fill="none" stroke="#16181D" stroke-width="1.6"><rect x="1" y="3" width="19" height="10" rx="3"/><rect x="3" y="5" width="13" height="6" rx="1" fill="#16181D" stroke="none"/><path d="M22 6v4"/></svg></span>
            </div>
            <div style="flex: 1; overflow: auto; padding-bottom: 78px;">
                @if($mobileTab === 'home')
                    <div style="padding: 8px 20px 20px;">
                        <div style="display: flex; align-items: center; gap: 12px; padding: 8px 0 18px;">
                            <span style="display: inline-flex; width: 46px; height: 46px; border-radius: 50%; background: {{ $w['teamColor'] }}; color: #fff; align-items: center; justify-content: center; font-size: 15px; font-weight: 700; font-family: 'Space Grotesk';">{{ $w['initials'] }}</span>
                            <div style="flex: 1;"><div style="font-size: 12.5px; color: #8A8880;">{{ $L['w_hi'] }}</div><div style="font-size: 17px; font-weight: 700;">{{ $w['name'] }}</div></div>
                            <div style="display: flex; gap: 2px; padding: 3px; background: #EAE8E1; border-radius: 8px;"><button wire:click="setLang('es')" style="{{ $Ui::mLang($lang==='es') }}">ES</button><button wire:click="setLang('en')" style="{{ $Ui::mLang($lang==='en') }}">EN</button><button wire:click="setLang('ko')" style="{{ $Ui::mLang($lang==='ko') }}">KO</button></div>
                        </div>
                        <div style="background: #16181D; border-radius: 22px; padding: 24px; color: #fff; text-align: center;">
                            <div style="display: inline-flex; align-items: center; gap: 7px; font-size: 12.5px; background: {{ $statusPillBg }}; color: {{ $statusPillColor }}; padding: 6px 14px; border-radius: 20px; font-weight: 600;"><span style="width: 8px; height: 8px; border-radius: 50%; background: {{ $statusPillColor }};"></span>{{ $clockedIn ? $L['w_status_in'] : $L['w_status_out'] }}</div>
                            <div style="font-family: 'Space Grotesk'; font-size: 46px; font-weight: 700; margin-top: 16px;">9:41 AM</div>
                            <div style="font-size: 12.5px; color: rgba(255,255,255,0.5);">{{ $w['teamName'] }} · {{ $w['role'] }}</div>
                            @if($clockedIn)
                                <div style="margin-top: 10px; font-size: 12.5px; color: #4ADE80;">{{ $L['w_since'] }} {{ $clockInTime }}</div>
                            @endif
                            <div style="margin-top: 20px;"><button wire:click="doClock" style="{{ $clockBtnStyle }}">{{ $clockedIn ? $L['w_clockout'] : $L['w_clockin'] }}</button></div>
                            @if($clockedIn)
                                <div style="{{ $noLunchRowStyle }}">
                                    <div style="text-align: left;"><div style="font-size: 13.5px; font-weight: 600; color: #fff;">{{ $L['w_noLunch'] }}</div><div style="font-size: 11px; color: rgba(255,255,255,0.5);">{{ $L['w_noLunchHint'] }}</div></div>
                                    <button wire:click="toggleNoLunch" style="{{ $noLunchSwStyle }}"><span style="{{ $noLunchKnobStyle }}"></span></button>
                                </div>
                            @endif
                            <button wire:click="openEarly" style="margin-top: 10px; width: 100%; padding: 13px; border: 1px solid rgba(255,255,255,0.22); border-radius: 16px; background: transparent; color: #F4C168; font-size: 15px; font-weight: 600; cursor: pointer;">{{ $L['w_early'] }}</button>
                        </div>
                        @if($earlyOpen)
                            <div style="margin-top: 16px; background: #fff; border: 1px solid #E4E2DB; border-radius: 18px; padding: 18px;">
                                <div style="font-size: 14px; font-weight: 700; margin-bottom: 12px;">{{ $L['w_earlyTitle'] }}</div>
                                <select wire:model.live="earlyReasonVal" style="width: 100%; padding: 11px 13px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 14px; background: #fff; color: #16181D; cursor: pointer;">@foreach($worker['reasonOptions'] as $o)<option value="{{ $o['value'] }}">{{ $o['label'] }}</option>@endforeach</select>
                                @if($earlyIsCustom)
                                    <input wire:model="earlyCustom" placeholder="{{ $L['w_earlyPh'] }}" style="width: 100%; margin-top: 10px; padding: 11px 13px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 14px; outline: none;"/>
                                @endif
                                <div style="display: flex; gap: 10px; margin-top: 14px;">
                                    <button wire:click="closeEarly" style="flex: 1; padding: 12px; border: 1px solid #E4E2DB; border-radius: 11px; background: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['w_cancel'] }}</button>
                                    <button wire:click="submitEarly" style="flex: 1; padding: 12px; border: none; border-radius: 11px; background: #E8A33D; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['w_earlyConfirm'] }}</button>
                                </div>
                            </div>
                        @endif
                        <div style="margin-top: 16px; background: #fff; border: 1px solid #E4E2DB; border-radius: 18px; padding: 18px; text-align: center;">
                            <div style="font-size: 12.5px; color: #8A8880; margin-bottom: 12px;">{{ $L['w_myqr'] }}</div>
                            <div style="width: 150px; height: 150px; margin: 0 auto; background: #fff; border: 1px solid #F0EEE8; border-radius: 12px; padding: 10px;">{!! $worker['qrSvg'] !!}</div>
                            <div style="font-family: 'Space Grotesk'; font-size: 12px; color: #E85D2A; margin-top: 10px;">{{ $w['empId'] }}</div>
                            <div style="font-size: 11.5px; color: #A7A49B; margin-top: 2px;">{{ $L['w_qrhint'] }}</div>
                        </div>
                    </div>
                @elseif($mobileTab === 'work')
                    <div style="padding: 12px 20px 20px;">
                        <div style="font-size: 18px; font-weight: 700; margin: 6px 0 16px;">{{ $L['w_tab_work'] }}</div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; padding: 16px;"><div style="font-size: 12px; color: #8A8880;">{{ $L['w_reg'] }} · {{ $L['w_ot'] }}</div><div style="font-family: 'Space Grotesk'; font-size: 26px; font-weight: 700; margin-top: 2px;">{{ $w['reg'] }}<span style="font-size: 15px; color: #1F9D6B;">+{{ $w['ot'] }}</span></div></div>
                            <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; padding: 16px;"><div style="font-size: 12px; color: #8A8880;">{{ $L['w_hours'] }}</div><div style="font-family: 'Space Grotesk'; font-size: 26px; font-weight: 700; margin-top: 2px;">{{ $w['hours'] }}h</div></div>
                        </div>
                        <div style="font-size: 13px; font-weight: 600; color: #8A8880; margin: 20px 0 8px;">{{ $L['w_recent'] }}</div>
                        <div style="font-size: 11px; color: #A7A49B; margin-bottom: 10px; line-height: 1.4;">{{ $worker['ruleNote'] }}</div>
                        <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; overflow: hidden;">
                            @foreach($worker['punchLog'] as $p)
                                <div style="padding: 12px 16px; border-bottom: 1px solid #F2F0EA;">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <span style="width: 62px; flex-shrink: 0;"><span style="display: block; font-size: 13.5px; font-weight: 600;">{{ $p['d'] }}</span><span style="display: block; font-size: 10.5px; color: #A7A49B;">{{ $p['dow'] }}</span></span>
                                        <span style="flex: 1; display: flex; gap: 8px; font-family: 'Space Grotesk'; font-size: 12.5px;"><span style="color: #1F9D6B;">↓ {{ $p['inFmt'] }}</span><span style="color: #8A8880;">↑ {{ $p['outFmt'] }}</span></span>
                                        <span style="font-family: 'Space Grotesk'; font-size: 13px; font-weight: 700; background: #F4F3EF; padding: 3px 10px; border-radius: 8px;">{{ $p['h'] }}</span>
                                    </div>
                                    @if($p['adjusted'])
                                        <div style="font-size: 10.5px; color: #B7B4AB; font-family: 'Space Grotesk'; margin: 5px 0 0 74px;">{{ $p['rawNote'] }}</div>
                                    @endif
                                    <div style="display: flex; flex-wrap: wrap; gap: 5px; margin: 7px 0 0 74px; align-items: center;">
                                        @foreach($p['chips'] as $c)
                                            <span style="font-size: 10px; font-weight: 600; padding: 2px 8px; border-radius: 6px; background: {{ $c['bg'] }}; color: {{ $c['color'] }};">{{ $c['label'] }}</span>
                                        @endforeach
                                        <button wire:click="toggleLunchRow('{{ $p['d'] }}', {{ $p['seedNoLunch'] ? 'true' : 'false' }})" style="{{ $Ui::lunchToggle($p['lunchIsNo']) }}">{{ $p['lunchToggleLabel'] }}</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @elseif($mobileTab === 'pay')
                    <div style="padding: 12px 20px 20px;">
                        <div style="font-size: 18px; font-weight: 700; margin: 6px 0 16px;">{{ $L['w_payslip'] }}</div>
                        <div style="background: linear-gradient(135deg,#E85D2A,#C74A20); border-radius: 20px; padding: 22px; color: #fff;">
                            <div style="font-size: 12.5px; opacity: 0.85;">{{ $L['w_est'] }} · Jun 15 – 28</div>
                            <div style="font-family: 'Space Grotesk'; font-size: 34px; font-weight: 700; margin-top: 6px;">{{ $w['net'] }}</div>
                        </div>
                        <div style="margin-top: 16px; background: #fff; border: 1px solid #E4E2DB; border-radius: 18px; padding: 18px;">
                            <div style="display: flex; justify-content: space-between; padding: 9px 0; border-bottom: 1px solid #F2F0EA;"><span style="font-size: 13.5px; color: #5A5D64;">{{ $L['p_rate'] }} {{ $w['rate'] }} · {{ $w['reg'] }}h {{ $L['w_reg'] }}</span><span style="font-family: 'Space Grotesk'; font-size: 13.5px; font-weight: 600;">{{ $w['gross'] }}</span></div>
                            <div style="display: flex; justify-content: space-between; padding: 9px 0; border-bottom: 1px solid #F2F0EA;"><span style="font-size: 13.5px; color: #5A5D64;">{{ $L['w_ot'] }} ×1.5 · {{ $w['ot'] }}h</span><span style="font-family: 'Space Grotesk'; font-size: 13.5px; font-weight: 600; color: #1F9D6B;">✓</span></div>
                            <div style="display: flex; justify-content: space-between; padding: 12px 0 2px;"><span style="font-size: 14px; font-weight: 700;">{{ $L['w_net'] }}</span><span style="font-family: 'Space Grotesk'; font-size: 17px; font-weight: 700; color: #E85D2A;">{{ $w['net'] }}</span></div>
                        </div>
                    </div>
                @elseif($mobileTab === 'me')
                    <div style="padding: 12px 20px 20px;">
                        <div style="text-align: center; padding: 14px 0 20px;">
                            <span style="display: inline-flex; width: 72px; height: 72px; border-radius: 50%; background: {{ $w['teamColor'] }}; color: #fff; align-items: center; justify-content: center; font-size: 24px; font-weight: 700; font-family: 'Space Grotesk';">{{ $w['initials'] }}</span>
                            <div style="font-size: 19px; font-weight: 700; margin-top: 12px;">{{ $w['name'] }}</div>
                            <div style="font-size: 13px; color: #8A8880;">{{ $w['teamName'] }} · {{ $w['role'] }}</div>
                        </div>
                        <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 18px; padding: 6px 18px;">
                            <div style="display: flex; justify-content: space-between; padding: 13px 0; border-bottom: 1px solid #F2F0EA;"><span style="font-size: 13px; color: #8A8880;">{{ $L['w_id'] }}</span><span style="font-size: 13px; font-weight: 600; font-family: 'Space Grotesk';">{{ $w['empId'] }}</span></div>
                            <div style="display: flex; justify-content: space-between; padding: 13px 0; border-bottom: 1px solid #F2F0EA;"><span style="font-size: 13px; color: #8A8880;">{{ $L['w_company'] }}</span><span style="font-size: 13.5px; font-weight: 500;">{{ $w['company'] }}</span></div>
                            <div style="display: flex; justify-content: space-between; padding: 13px 0; border-bottom: 1px solid #F2F0EA;"><span style="font-size: 13px; color: #8A8880;">{{ $L['e_access'] }}</span><span style="font-size: 13.5px; font-weight: 500;">{{ $w['access'] }}</span></div>
                            <div style="display: flex; justify-content: space-between; padding: 13px 0; border-bottom: 1px solid #F2F0EA;"><span style="font-size: 13px; color: #8A8880;">{{ $L['b_phone'] }}</span><span style="font-size: 13.5px; font-weight: 500;">{{ $w['phone'] }}</span></div>
                            <div style="display: flex; justify-content: space-between; padding: 13px 0;"><span style="font-size: 13px; color: #8A8880;">{{ $L['b_issued'] }}</span><span style="font-size: 13.5px; font-weight: 500;">{{ $w['issued'] }}</span></div>
                        </div>
                        <button wire:click="logout" style="width: 100%; margin-top: 16px; padding: 13px; border: 1px solid #E4E2DB; border-radius: 12px; background: #fff; font-size: 14px; font-weight: 600; color: #D9483B; cursor: pointer;">Logout</button>
                    </div>
                @endif
            </div>
            <div style="position: absolute; bottom: 0; left: 0; right: 0; display: flex; background: rgba(255,255,255,0.94); backdrop-filter: blur(10px); border-top: 1px solid #E4E2DB; padding: 8px 6px 22px;">
                @foreach($mobileTabs as $tab)
                    <button wire:click="setMobileTab('{{ $tab['key'] }}')" style="flex:1;display:flex;flex-direction:column;align-items:center;gap:0;padding:6px 0;border:none;background:transparent;cursor:pointer;color:{{ $tab['active'] ? '#E85D2A' : '#9AA0A6' }};"><span>{!! $Icon::mobile($tab['key']) !!}</span><span style="font-size: 10.5px; margin-top: 2px;">{{ $tab['label'] }}</span></button>
                @endforeach
            </div>
        </div>
    </div>
</div>
