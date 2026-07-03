@php
    $Ui = \App\Support\Ui::class;
    $b = $badge; $acc = $b['accColor']; $ext = $b['ext'];
    $done = $scanF === 'done';
@endphp
{{-- ============ BADGE REGISTRATION ============ --}}
<div>
    <div style="display: flex; align-items: center; gap: 18px; margin-bottom: 22px; padding: 14px 18px; background: #fff; border: 1px solid #E4E2DB; border-radius: 14px;">
        <span style="{{ $Ui::stepStyle($bstep==='front', $bstep!=='front') }}"><span style="width: 20px; height: 20px; border-radius: 50%; background: currentColor; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 11px;">1</span>{{ $L['b_step1'] }}</span>
        <span style="width: 24px; height: 1px; background: #E4E2DB;"></span>
        <span style="{{ $Ui::stepStyle($bstep==='back', $bstep==='assign') }}"><span style="width: 20px; height: 20px; border-radius: 50%; background: currentColor; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 11px;">2</span>{{ $L['b_step2'] }}</span>
        <span style="width: 24px; height: 1px; background: #E4E2DB;"></span>
        <span style="{{ $Ui::stepStyle($bstep==='assign', false) }}"><span style="width: 20px; height: 20px; border-radius: 50%; background: currentColor; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 11px;">3</span>{{ $L['b_step3'] }}</span>
    </div>

    {{-- STEP 1: FRONT --}}
    @if($bstep === 'front')
        <div style="display: grid; grid-template-columns: 380px 1fr; gap: 22px; align-items: start;">
            <div style="background: #16181D; border-radius: 18px; padding: 24px; color: #fff;">
                <div style="font-weight: 600; font-size: 15px; margin-bottom: 4px;">{{ $L['b_scanFront'] }}</div>
                <div style="font-size: 12.5px; color: rgba(255,255,255,0.55); margin-bottom: 18px;">{{ $L['b_frontHint'] }}</div>
                <div style="position: relative; aspect-ratio: 1.58; border-radius: 14px; overflow: hidden; background: #fff; border: 1.5px dashed rgba(255,255,255,0.2);">
                    <div style="position: absolute; inset: 0; padding: 16px; display: flex; flex-direction: column;">
                        <div style="text-align: center;"><div style="font-family: 'Space Grotesk'; font-size: 26px; font-weight: 700; color: #16181D; letter-spacing: 0.04em;">HOFFMAN</div><div style="font-size: 12px; font-weight: 700; color: #D9483B; margin-top: -2px;">Sonoran MEP</div></div>
                        <div style="display: flex; gap: 12px; margin-top: 12px; flex: 1;">
                            <div style="width: 74px; height: 92px; border-radius: 6px; background: #E9E7E0; flex-shrink: 0; display: flex; align-items: flex-end; justify-content: center; overflow: hidden; position: relative;"><svg width="74" height="80" viewBox="0 0 74 80"><circle cx="37" cy="30" r="17" fill="#B7B4AB"/><path d="M6 80c0-20 14-30 31-30s31 10 31 30z" fill="#B7B4AB"/></svg></div>
                            <div style="flex: 1; display: flex; flex-direction: column; gap: 6px; padding-top: 4px;">
                                <div><div style="font-size: 8px; color: #A7A49B; letter-spacing: 0.08em;">LAST NAME</div><div style="font-size: 13px; font-weight: 700; color: #16181D;">MARTÍNEZ</div></div>
                                <div><div style="font-size: 8px; color: #A7A49B; letter-spacing: 0.08em;">FIRST NAME</div><div style="font-size: 13px; font-weight: 700; color: #16181D;">CARLOS</div></div>
                                <div><div style="font-size: 8px; color: #A7A49B; letter-spacing: 0.08em;">ROLE</div><div style="font-size: 11px; font-weight: 600; color: #16181D;">ELECTRICIAN</div></div>
                            </div>
                        </div>
                        <div style="font-size: 8px; color: #A7A49B; text-align: right;">ISSUED 03/14/2026</div>
                    </div>
                    @if($scanF === 'scanning')
                        <div style="position: absolute; left: 4%; right: 4%; height: 3px; background: linear-gradient(90deg,transparent,#E85D2A,transparent); box-shadow: 0 0 14px 4px rgba(232,93,42,0.6); animation: ncscan 1.3s ease-in-out infinite alternate;"></div><div style="position: absolute; inset: 0; background: rgba(232,93,42,0.06);"></div>
                    @endif
                    @if($done)
                        <div style="position: absolute; top: 8px; right: 8px; width: 30px; height: 30px; border-radius: 50%; background: #1F9D6B; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 16px;">✓</div>
                    @endif
                </div>
                @if($scanF === 'idle')
                    <button wire:click="startScanF" style="width: 100%; margin-top: 18px; padding: 14px; border: none; border-radius: 12px; background: #E85D2A; color: #fff; font-size: 15px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 9px;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M3 12h18"/></svg>{{ $L['b_startScan'] }}</button>
                @elseif($scanF === 'scanning')
                    <div x-data x-init="setTimeout(() => $wire.finishScanF(), 2400)" style="margin-top: 18px; padding: 14px; border-radius: 12px; background: rgba(232,93,42,0.12); display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 14px; font-weight: 600;"><span style="width: 16px; height: 16px; border: 2px solid rgba(232,93,42,0.3); border-top-color: #E85D2A; border-radius: 50%; animation: ncspin 0.8s linear infinite; display: inline-block;"></span>{{ $L['b_scanning'] }}</div>
                @else
                    <div style="margin-top: 18px; display: flex; gap: 10px;"><button wire:click="rescanF" style="padding: 13px 16px; border: 1px solid rgba(255,255,255,0.2); border-radius: 11px; background: transparent; color: #fff; font-size: 13px; cursor: pointer;">{{ $L['b_rescan'] }}</button><button wire:click="toBack" style="flex: 1; padding: 13px; border: none; border-radius: 11px; background: #E85D2A; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['b_next'] }}</button></div>
                @endif
            </div>
            <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 18px; padding: 26px;">
                <div style="font-size: 12px; font-weight: 700; letter-spacing: 0.06em; color: #E85D2A; margin-bottom: 16px;">{{ $L['b_extracted'] }}</div>
                <div style="display: flex; gap: 20px;">
                    <div style="text-align: center;">
                        <div style="width: 96px; height: 116px; border-radius: 10px; background: #F1EFE9; border: 1.5px solid {{ $done ? '#1F9D6B' : '#E4E2DB' }}; overflow: hidden; display: flex; align-items: flex-end; justify-content: center;"><svg width="96" height="100" viewBox="0 0 96 100"><circle cx="48" cy="38" r="22" fill="{{ $done ? '#1F9D6B' : '#D8D5CD' }}"/><path d="M8 100c0-26 18-40 40-40s40 14 40 40z" fill="{{ $done ? '#1F9D6B' : '#D8D5CD' }}"/></svg></div>
                        <div style="font-size: 10.5px; color: #8A8880; margin-top: 8px;">{{ $L['b_faceCrop'] }}</div>
                    </div>
                    <div style="flex: 1; display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 14px; align-content: start;">
                        <label style="grid-column: span 2;"><span style="font-size: 12px; color: #8A8880;">{{ $L['b_company'] }} <span style="color: #D9483B;">●</span></span><input value="{{ $done ? $ext['company'] : '' }}" readonly style="{{ $Ui::ocrField($done) }}"/></label>
                        <label><span style="font-size: 12px; color: #8A8880;">{{ $L['b_lastName'] }}</span><input value="{{ $done ? $ext['last'] : '' }}" readonly style="{{ $Ui::ocrField($done) }}"/></label>
                        <label><span style="font-size: 12px; color: #8A8880;">{{ $L['b_firstName'] }}</span><input value="{{ $done ? $ext['first'] : '' }}" readonly style="{{ $Ui::ocrField($done) }}"/></label>
                        <label><span style="font-size: 12px; color: #8A8880;">{{ $L['b_role'] }}</span><input value="{{ $done ? $ext['role'] : '' }}" readonly style="{{ $Ui::ocrField($done) }}"/></label>
                        <label><span style="font-size: 12px; color: #8A8880;">{{ $L['b_issued'] }}</span><input value="{{ $done ? $ext['issued'] : '' }}" readonly style="{{ $Ui::ocrField($done) }}"/></label>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- STEP 2: BACK --}}
    @if($bstep === 'back')
        @php $bdone = $scanB === 'done'; @endphp
        <div style="display: grid; grid-template-columns: 380px 1fr; gap: 22px; align-items: start;">
            <div style="background: #16181D; border-radius: 18px; padding: 24px; color: #fff;">
                <div style="font-weight: 600; font-size: 15px; margin-bottom: 4px;">{{ $L['b_scanBack'] }}</div>
                <div style="font-size: 12.5px; color: rgba(255,255,255,0.55); margin-bottom: 18px;">{{ $L['b_backHint'] }}</div>
                <div style="position: relative; aspect-ratio: 1.58; border-radius: 14px; overflow: hidden; background: #23262D; border: 1.5px dashed rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center;">
                    <div style="position: absolute; inset: 16px; border-radius: 10px; background: #fff; padding: 14px; display: flex; flex-direction: column; justify-content: space-between;">
                        <div style="display: flex; justify-content: space-between; align-items: center;"><span style="font-size: 9px; font-weight: 700; color: #A7A49B; letter-spacing: 0.1em;">SITE ACCESS · REVERSE</span><span style="font-size: 8px; color: #B7B4AB;">HOFFMAN</span></div>
                        <div style="display: flex; align-items: flex-end; gap: 2px; height: 46px; justify-content: center;">@foreach([2,1,3,1,2,4,1,2,3,1,2,1,4,2,1,3] as $w)<span style="width:{{ $w }}px;height:100%;background:#16181D;"></span>@endforeach</div>
                        <div style="text-align: center; font-family: 'Space Grotesk'; font-size: 9px; letter-spacing: 0.2em; color: #5A5D64;">SITE-AZ-P21-CARD</div>
                    </div>
                    @if($scanB === 'scanning')
                        <div style="position: absolute; left: 4%; right: 4%; height: 3px; background: linear-gradient(90deg,transparent,#E85D2A,transparent); box-shadow: 0 0 14px 4px rgba(232,93,42,0.6); animation: ncscan 1.1s ease-in-out infinite alternate;"></div>
                    @endif
                    @if($bdone)
                        <div style="position: absolute; inset: 0; background: rgba(31,157,107,0.2); display: flex; align-items: center; justify-content: center;"><span style="width: 46px; height: 46px; border-radius: 50%; background: #1F9D6B; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 24px;">✓</span></div>
                    @endif
                </div>
                @if($scanB === 'idle')
                    <button wire:click="startScanB" style="width: 100%; margin-top: 18px; padding: 14px; border: none; border-radius: 12px; background: #E85D2A; color: #fff; font-size: 15px; font-weight: 600; cursor: pointer;">{{ $L['b_scanQr'] }}</button>
                @elseif($scanB === 'scanning')
                    <div x-data x-init="setTimeout(() => $wire.finishScanB(), 2200)" style="margin-top: 18px; padding: 14px; border-radius: 12px; background: rgba(232,93,42,0.12); display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 14px; font-weight: 600;"><span style="width: 16px; height: 16px; border: 2px solid rgba(232,93,42,0.3); border-top-color: #E85D2A; border-radius: 50%; animation: ncspin 0.8s linear infinite; display: inline-block;"></span>{{ $L['b_qrReading'] }}</div>
                @else
                    <div style="margin-top: 18px; display: flex; gap: 10px;"><button wire:click="backToFront" style="padding: 13px 16px; border: 1px solid rgba(255,255,255,0.2); border-radius: 11px; background: transparent; color: #fff; font-size: 13px; cursor: pointer;">{{ $L['b_back'] }}</button><button wire:click="toAssign" style="flex: 1; padding: 13px; border: none; border-radius: 11px; background: #E85D2A; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['b_toAssign'] }}</button></div>
                @endif
            </div>
            <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 18px; padding: 26px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; min-height: 240px;">
                <div style="width: 56px; height: 56px; border-radius: 50%; background: {{ $bdone ? '#E7F4EE' : '#F1EFE9' }}; display: flex; align-items: center; justify-content: center; margin-bottom: 14px;"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="{{ $bdone ? '#1F9D6B' : '#B7B4AB' }}" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2.5"/><path d="M7 9h10M7 13h6"/></svg></div>
                <div style="font-size: 15px; font-weight: 700; color: {{ $bdone ? '#1F9D6B' : '#B7B4AB' }};">{{ $bdone ? $L['b_qrDone'] : $L['b_scanBack'] }}</div>
                <div style="font-size: 12.5px; color: #A7A49B; margin-top: 10px; max-width: 260px; line-height: 1.5;">{{ $L['b_backNote'] }}</div>
            </div>
        </div>
    @endif

    {{-- STEP 3: ASSIGN --}}
    @if($bstep === 'assign')
        @php $ndone = $scanN === 'done'; @endphp
        <div style="max-width: 720px;">
            <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 18px; padding: 26px;">
                <div style="display: flex; align-items: center; gap: 16px; padding-bottom: 20px; border-bottom: 1px solid #F0EEE8;">
                    <div style="width: 56px; height: 68px; border-radius: 8px; background: #F1EFE9; overflow: hidden; display: flex; align-items: flex-end; justify-content: center;"><svg width="56" height="60" viewBox="0 0 56 60"><circle cx="28" cy="22" r="13" fill="#1F9D6B"/><path d="M4 60c0-16 11-24 24-24s24 8 24 24z" fill="#1F9D6B"/></svg></div>
                    <div><div style="font-size: 19px; font-weight: 700;">Carlos Martínez</div><div style="font-size: 13px; color: #8A8880;">Sonoran MEP · Electrician</div><div style="font-family: 'Space Grotesk'; font-size: 12px; color: #E85D2A; margin-top: 2px;">{{ $b['regEmpId'] }}</div></div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-top: 22px;">
                    <label><span style="font-size: 12.5px; color: #8A8880;">{{ $L['b_selectTeam'] }}</span><select wire:model="regTeam" style="width: 100%; margin-top: 6px; padding: 11px 13px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 14px; background: #fff; cursor: pointer;">@foreach($b['regTeamOptions'] as $o)<option value="{{ $o['id'] }}">{{ $o['label'] }}</option>@endforeach</select></label>
                    <label><span style="font-size: 12.5px; color: #8A8880;">{{ $L['b_rate'] }}</span><input placeholder="32.50" style="width: 100%; margin-top: 6px; padding: 11px 13px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 14px; outline: none;"/></label>
                    <label style="grid-column: span 2;"><span style="font-size: 12.5px; color: #8A8880;">{{ $L['b_type'] }}</span><select wire:change="setRegType($event.target.value)" style="width: 100%; margin-top: 6px; padding: 11px 13px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 14px; background: #fff; cursor: pointer;">@foreach($b['typeOptions'] as $o)<option value="{{ $o['id'] }}" @selected($o['id']===$regType)>{{ $o['label'] }}</option>@endforeach</select></label>
                    <div style="grid-column: span 2;"><span style="font-size: 12.5px; color: #8A8880;">{{ $L['b_access'] }}</span><div style="display: flex; gap: 8px; margin-top: 6px;"><button wire:click="setRegAccess('admin')" style="{{ $Ui::accessSeg2($regAccess==='admin', $acc['admin']) }}">{{ $L['access_admin'] }}</button><button wire:click="setRegAccess('manager')" style="{{ $Ui::accessSeg2($regAccess==='manager', $acc['manager']) }}">{{ $L['access_manager'] }}</button><button wire:click="setRegAccess('worker')" style="{{ $Ui::accessSeg2($regAccess==='worker', $acc['worker']) }}">{{ $L['access_worker'] }}</button></div></div>
                    <div style="grid-column: span 2; border: 1.5px solid {{ $ndone ? '#1F9D6B' : '#E4E2DB' }}; border-radius: 14px; padding: 16px 18px; background: {{ $ndone ? '#F1FAF5' : '#FAFAF8' }}; display: flex; align-items: center; gap: 16px;">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#E85D2A" stroke-width="1.8" stroke-linecap="round"><path d="M8 9a5 5 0 0 1 0 6M11.5 6.5a9 9 0 0 1 0 11M15 4a13 13 0 0 1 0 16"/></svg>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-size: 13.5px; font-weight: 600;">{{ $L['b_nfc'] }}</div>
                            @if($ndone)
                                <div style="font-size: 12px; color: #8A8880; margin-top: 3px;">{{ $L['b_uid'] }} <span style="font-family: 'Space Grotesk';">{{ $b['nfcUid'] }}</span> → <span style="font-family: 'Space Grotesk'; color: #E85D2A; font-weight: 600;">{{ $b['regEmpId'] }}</span></div>
                            @else
                                <div style="font-size: 12px; color: #8A8880; margin-top: 3px;">{{ $L['b_nfcHint'] }}</div>
                            @endif
                        </div>
                        @if($scanN === 'idle')
                            <button wire:click="startScanN" style="padding: 10px 18px; border: none; border-radius: 10px; background: #E85D2A; color: #fff; font-size: 13.5px; font-weight: 600; cursor: pointer; white-space: nowrap;">{{ $L['b_nfcRead'] }}</button>
                        @elseif($scanN === 'scanning')
                            <span x-data x-init="setTimeout(() => $wire.finishScanN(), 2000)" style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: #E85D2A; white-space: nowrap;"><span style="width: 15px; height: 15px; border: 2px solid rgba(232,93,42,0.3); border-top-color: #E85D2A; border-radius: 50%; animation: ncspin 0.8s linear infinite; display: inline-block;"></span>{{ $L['b_nfcReading'] }}</span>
                        @else
                            <span style="display: inline-flex; align-items: center; gap: 6px; color: #1F9D6B; font-weight: 600; font-size: 13.5px; white-space: nowrap;">✓ {{ $L['b_nfcDone'] }}</span>
                        @endif
                    </div>
                    <label><span style="font-size: 12.5px; color: #8A8880;">{{ $L['b_phone'] }}</span><input placeholder="(480) 555-0000" style="width: 100%; margin-top: 6px; padding: 11px 13px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 14px; outline: none;"/></label>
                    <label><span style="font-size: 12.5px; color: #8A8880;">{{ $L['b_email'] }}</span><input placeholder="name@nahshon.io" style="width: 100%; margin-top: 6px; padding: 11px 13px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 14px; outline: none;"/></label>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 26px;">
                    <button wire:click="backToBack" style="padding: 14px 20px; border: 1px solid #E4E2DB; border-radius: 12px; background: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['b_back'] }}</button>
                    <button wire:click="finishBadge" style="flex: 1; padding: 14px; border: none; border-radius: 12px; background: #E85D2A; color: #fff; font-size: 15px; font-weight: 600; cursor: pointer;">{{ $L['b_finish'] }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
