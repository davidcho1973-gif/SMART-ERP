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
        <div class="wf-badge-cols" style="display: grid; grid-template-columns: 380px 1fr; gap: 22px; align-items: start;">
            <div style="background: #16181D; border-radius: 18px; padding: 24px; color: #fff;">
                <div style="font-weight: 600; font-size: 15px; margin-bottom: 4px;">{{ $L['b_scanFront'] }}</div>
                <div style="font-size: 12.5px; color: rgba(255,255,255,0.55); margin-bottom: 18px;">{{ $L['b_frontHint'] }}</div>
                <div style="position: relative; aspect-ratio: 1.58; border-radius: 14px; overflow: hidden; background: #fff; border: 1.5px dashed rgba(255,255,255,0.2);">
                    @if($badgePhoto)
                        <img src="{{ $badgePhoto->temporaryUrl() }}" style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; background: #16181D;" alt="badge"/>
                        @if($scanF === 'idle')
                        <label style="position: absolute; top: 8px; left: 8px; display: inline-flex; align-items: center; gap: 5px; padding: 5px 10px; border-radius: 8px; background: rgba(22,24,29,0.75); color: #fff; font-size: 11.5px; font-weight: 600; cursor: pointer; backdrop-filter: blur(4px);">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>{{ $L['b_photoChange'] }}
                            <input type="file" wire:model="badgePhoto" accept="image/*" capture="environment" style="display: none;"/>
                        </label>
                        @endif
                    @else
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
                    @endif
                    @if($scanF === 'scanning')
                        <div style="position: absolute; left: 4%; right: 4%; height: 3px; background: linear-gradient(90deg,transparent,#E85D2A,transparent); box-shadow: 0 0 14px 4px rgba(232,93,42,0.6); animation: ncscan 1.3s ease-in-out infinite alternate;"></div><div style="position: absolute; inset: 0; background: rgba(232,93,42,0.06);"></div>
                    @endif
                    @if($done)
                        <div style="position: absolute; top: 8px; right: 8px; width: 30px; height: 30px; border-radius: 50%; background: #1F9D6B; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 16px;">✓</div>
                    @endif
                </div>
                @if($scanF === 'idle')
                    @if(! $badgePhoto)
                        <label style="display: block; width: 100%; margin-top: 18px; padding: 14px; border: none; border-radius: 12px; background: #E85D2A; color: #fff; font-size: 15px; font-weight: 600; cursor: pointer; text-align: center;">
                            <span wire:loading.remove wire:target="badgePhoto" style="display: flex; align-items: center; justify-content: center; gap: 9px;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>{{ $L['b_photoPick'] }}</span>
                            <span wire:loading.flex wire:target="badgePhoto" style="align-items: center; justify-content: center; gap: 9px;"><span style="width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.35); border-top-color: #fff; border-radius: 50%; animation: ncspin 0.8s linear infinite; display: inline-block;"></span>{{ $L['b_photoLoading'] }}</span>
                            <input type="file" wire:model="badgePhoto" accept="image/*" capture="environment" style="display: none;"/>
                        </label>
                    @else
                        <button wire:click="analyzeBadge" wire:loading.attr="disabled" wire:target="analyzeBadge" style="width: 100%; margin-top: 18px; padding: 14px; border: none; border-radius: 12px; background: #E85D2A; color: #fff; font-size: 15px; font-weight: 600; cursor: pointer;">
                            <span wire:loading.remove wire:target="analyzeBadge" style="display: flex; align-items: center; justify-content: center; gap: 9px;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M3 12h18"/></svg>{{ $L['b_startScan'] }}</span>
                            <span wire:loading.flex wire:target="analyzeBadge" style="align-items: center; justify-content: center; gap: 9px;"><span style="width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.35); border-top-color: #fff; border-radius: 50%; animation: ncspin 0.8s linear infinite; display: inline-block;"></span>{{ $L['b_scanning'] }}</span>
                        </button>
                    @endif
                @elseif($scanF === 'scanning')
                    <div x-data x-init="setTimeout(() => $wire.finishScanF(), 2400)" style="margin-top: 18px; padding: 14px; border-radius: 12px; background: rgba(232,93,42,0.12); display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 14px; font-weight: 600;"><span style="width: 16px; height: 16px; border: 2px solid rgba(232,93,42,0.3); border-top-color: #E85D2A; border-radius: 50%; animation: ncspin 0.8s linear infinite; display: inline-block;"></span>{{ $L['b_scanning'] }}</div>
                @else
                    <div style="margin-top: 18px; display: flex; gap: 10px;"><button wire:click="rescanF" style="padding: 13px 16px; border: 1px solid rgba(255,255,255,0.2); border-radius: 11px; background: transparent; color: #fff; font-size: 13px; cursor: pointer;">{{ $L['b_rescan'] }}</button><button wire:click="toBack" style="flex: 1; padding: 13px; border: none; border-radius: 11px; background: #E85D2A; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['b_next'] }}</button></div>
                @endif
            </div>
            <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 18px; padding: 24px;">
                <div style="font-size: 12px; font-weight: 700; letter-spacing: 0.06em; color: #E85D2A; margin-bottom: 16px;">{{ $L['b_extracted'] }}</div>
                @if($b['faceCrop'])
                    <div style="width: 100%; aspect-ratio: 1.58; border-radius: 12px; border: 1px solid #E4E2DB; margin-bottom: 18px; {{ $b['faceCrop'] }}"></div>
                    <div style="font-size: 10.5px; color: #A7A49B; text-align: center; margin-top: -10px; margin-bottom: 16px;">{{ $L['b_faceCrop'] }}</div>
                @endif
                <div class="wf-badge-fields" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px 16px;">
                    <label style="grid-column: span 2;"><span style="font-size: 12px; color: #8A8880;">{{ $L['b_company'] }} <span style="color: #D9483B;">●</span></span><input wire:model.blur="regCoName" style="{{ $Ui::ocrField($done) }}"/></label>
                    <label><span style="font-size: 12px; color: #8A8880;">{{ $L['b_lastName'] }}</span><input wire:model.blur="regLast" style="{{ $Ui::ocrField($done) }}"/></label>
                    <label><span style="font-size: 12px; color: #8A8880;">{{ $L['b_firstName'] }}</span><input wire:model.blur="regFirst" style="{{ $Ui::ocrField($done) }}"/></label>
                    <label style="grid-column: span 2;"><span style="font-size: 12px; color: #8A8880;">{{ $L['b_role'] }}</span><input wire:model.blur="regRoleTitle" style="{{ $Ui::ocrField($done) }}"/></label>
                    <label style="grid-column: span 2;"><span style="font-size: 12px; color: #8A8880;">{{ $L['b_issued'] }}</span><input wire:model.blur="regIssued" style="{{ $Ui::ocrField($done) }}"/></label>
                </div>
            </div>
        </div>
    @endif

    {{-- STEP 2: BACK — QR photo analysis (mirrors the front step) --}}
    @if($bstep === 'back')
        @php $bdone = $scanB === 'done'; @endphp
        <div class="wf-badge-cols" style="display: grid; grid-template-columns: 380px 1fr; gap: 22px; align-items: start;">
            <div style="background: #16181D; border-radius: 18px; padding: 24px; color: #fff;">
                <div style="font-weight: 600; font-size: 15px; margin-bottom: 4px;">{{ $L['b_scanBack'] }}</div>
                <div style="font-size: 12.5px; color: rgba(255,255,255,0.55); margin-bottom: 18px;">{{ $L['b_backHint'] }}</div>
                <div style="position: relative; aspect-ratio: 1.58; border-radius: 14px; overflow: hidden; background: #23262D; border: 1.5px dashed rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center;">
                    @if($backQrPhoto)
                        <img src="{{ $backQrPhoto->temporaryUrl() }}" style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; background: #16181D;" alt="badge back"/>
                        @if(! $bdone)
                        <label style="position: absolute; top: 8px; left: 8px; display: inline-flex; align-items: center; gap: 5px; padding: 5px 10px; border-radius: 8px; background: rgba(22,24,29,0.75); color: #fff; font-size: 11.5px; font-weight: 600; cursor: pointer; backdrop-filter: blur(4px);">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>{{ $L['b_photoChange'] }}
                            <input type="file" wire:model="backQrPhoto" accept="image/*" capture="environment" style="display: none;"/>
                        </label>
                        @endif
                    @else
                        <div style="text-align: center; color: rgba(255,255,255,0.4);"><svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3M20 20v.01M17 20v.01M20 17v.01"/></svg></div>
                    @endif
                    @if($bdone)
                        <div style="position: absolute; top: 8px; right: 8px; width: 30px; height: 30px; border-radius: 50%; background: #1F9D6B; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 16px;">✓</div>
                    @endif
                </div>

                @if(! $bdone)
                    @if(! $backQrPhoto)
                        <label style="display: block; width: 100%; margin-top: 18px; padding: 14px; border: none; border-radius: 12px; background: #E85D2A; color: #fff; font-size: 15px; font-weight: 600; cursor: pointer; text-align: center;">
                            <span wire:loading.remove wire:target="backQrPhoto" style="display: flex; align-items: center; justify-content: center; gap: 9px;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>{{ $L['b_qrPick'] }}</span>
                            <span wire:loading.flex wire:target="backQrPhoto" style="align-items: center; justify-content: center; gap: 9px;"><span style="width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.35); border-top-color: #fff; border-radius: 50%; animation: ncspin 0.8s linear infinite; display: inline-block;"></span>{{ $L['b_photoLoading'] }}</span>
                            <input type="file" wire:model="backQrPhoto" accept="image/*" capture="environment" style="display: none;"/>
                        </label>
                    @else
                        <button wire:click="analyzeBackQr" wire:loading.attr="disabled" wire:target="analyzeBackQr" style="width: 100%; margin-top: 18px; padding: 14px; border: none; border-radius: 12px; background: #E85D2A; color: #fff; font-size: 15px; font-weight: 600; cursor: pointer;">
                            <span wire:loading.remove wire:target="analyzeBackQr" style="display: flex; align-items: center; justify-content: center; gap: 9px;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3M20 20v.01M17 20v.01M20 17v.01"/></svg>{{ $L['b_qrAnalyze'] }}</span>
                            <span wire:loading.flex wire:target="analyzeBackQr" style="align-items: center; justify-content: center; gap: 9px;"><span style="width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.35); border-top-color: #fff; border-radius: 50%; animation: ncspin 0.8s linear infinite; display: inline-block;"></span>{{ $L['b_qrAi'] }}</span>
                        </button>
                    @endif
                    <button wire:click="toggleBackManual" style="display: block; width: 100%; margin-top: 12px; padding: 4px; border: none; background: transparent; color: rgba(255,255,255,0.45); font-size: 12px; cursor: pointer; text-decoration: underline;">{{ $L['b_qrManual'] }}</button>
                    @if($backManual)
                        <div style="margin-top: 12px;">
                            <input wire:model.live.debounce.500ms="backQrValue" placeholder="00102810" style="width: 100%; padding: 10px 12px; border: 1px solid rgba(255,255,255,0.18); border-radius: 10px; background: rgba(255,255,255,0.06); color: #fff; font-size: 13.5px; font-family: 'Space Grotesk'; outline: none;"/>
                            @if(trim($backQrValue) !== '')
                                <button wire:click="captureBackQr(@js(trim($backQrValue)))" style="width: 100%; margin-top: 8px; padding: 12px; border: 1px solid rgba(255,255,255,0.2); border-radius: 11px; background: transparent; color: #fff; font-size: 13.5px; font-weight: 600; cursor: pointer;">{{ $L['b_qrUse'] }}</button>
                            @endif
                        </div>
                    @endif
                @else
                    <div style="margin-top: 18px; display: flex; gap: 10px;"><button wire:click="rescanBack" style="padding: 13px 16px; border: 1px solid rgba(255,255,255,0.2); border-radius: 11px; background: transparent; color: #fff; font-size: 13px; cursor: pointer;">{{ $L['b_rescan'] }}</button><button wire:click="toAssign" style="flex: 1; padding: 13px; border: none; border-radius: 11px; background: #E85D2A; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['b_toAssign'] }}</button></div>
                @endif
            </div>
            <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 18px; padding: 26px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; min-height: 240px;">
                <div style="width: 56px; height: 56px; border-radius: 50%; background: {{ $bdone ? '#E7F4EE' : '#F1EFE9' }}; display: flex; align-items: center; justify-content: center; margin-bottom: 14px;"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="{{ $bdone ? '#1F9D6B' : '#B7B4AB' }}" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3M20 20v.01M17 20v.01M20 17v.01"/></svg></div>
                <div style="font-size: 15px; font-weight: 700; color: {{ $bdone ? '#1F9D6B' : '#B7B4AB' }};">{{ $bdone ? $L['b_qrDone'] : $L['b_scanBack'] }}</div>
                @if($bdone)
                    <div style="margin-top: 12px; padding: 10px 16px; border-radius: 10px; background: #F1FAF5; border: 1px solid #CBE7D8;">
                        <div style="font-size: 10.5px; color: #8A8880; letter-spacing: 0.05em;">{{ $L['b_qrValue'] }}</div>
                        <div style="font-family: 'Space Grotesk'; font-size: 16px; font-weight: 700; color: #16181D; word-break: break-all;">{{ $backQrValue }}</div>
                    </div>
                @else
                    <div style="font-size: 12.5px; color: #A7A49B; margin-top: 10px; max-width: 260px; line-height: 1.5;">{{ $L['b_backNote'] }}</div>
                @endif
            </div>
        </div>
    @endif

    {{-- STEP 3: ASSIGN --}}
    @if($bstep === 'assign')
        @php $ndone = $scanN === 'done' || trim($nfcUidManual) !== ''; @endphp
        <div style="max-width: 720px;">
            <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 18px; padding: 26px;">
                <div style="display: flex; align-items: center; gap: 16px; padding-bottom: 20px; border-bottom: 1px solid #F0EEE8;">
                    <div style="width: 56px; height: 68px; border-radius: 8px; background: #F1EFE9; overflow: hidden; display: flex; align-items: flex-end; justify-content: center;"><svg width="56" height="60" viewBox="0 0 56 60"><circle cx="28" cy="22" r="13" fill="#1F9D6B"/><path d="M4 60c0-16 11-24 24-24s24 8 24 24z" fill="#1F9D6B"/></svg></div>
                    <div><div style="font-size: 19px; font-weight: 700;">{{ trim($regFirst.' '.$regLast) ?: '—' }}</div><div style="font-size: 13px; color: #8A8880;">{{ $regCoName ?: '—' }} · {{ $regRoleTitle ?: '—' }}</div><div style="font-family: 'Space Grotesk'; font-size: 12px; color: #E85D2A; margin-top: 2px;">{{ $b['regEmpId'] }}@if(trim($backQrValue) !== '') <span style="color:#8A8880;">· {{ $L['e_qr'] }} {{ $backQrValue }}</span>@endif</div></div>
                </div>
                <div class="wf-badge-fields" style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-top: 22px;">
                    <label><span style="font-size: 12.5px; color: #8A8880;">{{ $L['b_selectTeam'] }}</span><select wire:model="regTeam" style="width: 100%; margin-top: 6px; padding: 11px 13px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 14px; background: #fff; cursor: pointer;">@foreach($b['regTeamOptions'] as $o)<option value="{{ $o['id'] }}">{{ $o['label'] }}</option>@endforeach</select></label>
                    <label><span style="font-size: 12.5px; color: #8A8880;">{{ $L['b_rate'] }}</span><input wire:model.blur="regRate" placeholder="32.50" style="width: 100%; margin-top: 6px; padding: 11px 13px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 14px; outline: none;"/></label>
                    <label style="grid-column: span 2;"><span style="font-size: 12.5px; color: #8A8880;">{{ $L['b_type'] }}</span><select wire:change="setRegType($event.target.value)" style="width: 100%; margin-top: 6px; padding: 11px 13px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 14px; background: #fff; cursor: pointer;">@foreach($b['typeOptions'] as $o)<option value="{{ $o['id'] }}" @selected($o['id']===$regType)>{{ $o['label'] }}</option>@endforeach</select></label>
                    <div style="grid-column: span 2;"><span style="font-size: 12.5px; color: #8A8880;">{{ $L['b_access'] }}</span>@php $regGrant = $can['assignableRoles'] ?? []; $regCanon = \App\Support\Access::canonical($regAccess); @endphp<div style="display: flex; gap: 8px; margin-top: 6px; flex-wrap: wrap;">@forelse($regGrant as $r)<button wire:click="setRegAccess('{{ $r }}')" style="{{ $Ui::accessSeg2($regCanon === $r, $acc[$r] ?? '#6B6E76') }}">{{ $L['access_'.$r] ?? $r }}</button>@empty<span style="display: inline-flex; font-size: 12.5px; font-weight: 600; color: #8A8880; background: #EFEDE6; padding: 6px 12px; border-radius: 8px;">{{ $L['access_worker'] }}</span>@endforelse</div></div>
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
                    {{-- real NFC: type the UID or capture it via Web NFC (Chrome on Android) --}}
                    <div style="grid-column: span 2; display: flex; gap: 8px; align-items: center;"
                         x-data="{
                            sup: 'NDEFReader' in window,
                            scanNfc() {
                                const r = new NDEFReader();
                                r.scan().then(() => {
                                    r.onreading = (ev) => { if (ev.serialNumber) $wire.set('nfcUidManual', ev.serialNumber); };
                                }).catch((e) => console.warn('WebNFC', e));
                            }
                         }">
                        <input wire:model.live.debounce.400ms="nfcUidManual" placeholder="{{ $L['b_uidManual'] }} — 04:73:AC:2F:19:B4:5E" style="flex: 1; padding: 11px 13px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 13.5px; font-family: 'Space Grotesk'; outline: none;"/>
                        <button type="button" x-show="sup" x-cloak @click="scanNfc()"
                            style="padding: 11px 14px; border: 1px solid #E4E2DB; border-radius: 10px; background: #fff; font-size: 12.5px; font-weight: 600; cursor: pointer; white-space: nowrap;">{{ $L['b_nfcWeb'] }}</button>
                    </div>
                    <label><span style="font-size: 12.5px; color: #8A8880;">{{ $L['b_phone'] }}</span><input wire:model.blur="regPhone" placeholder="(480) 555-0000" style="width: 100%; margin-top: 6px; padding: 11px 13px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 14px; outline: none;"/></label>
                    <label><span style="font-size: 12.5px; color: #8A8880;">{{ $L['b_email'] }}</span><input wire:model.blur="regEmail" placeholder="name@nahshon.io" style="width: 100%; margin-top: 6px; padding: 11px 13px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 14px; outline: none;"/></label>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 26px;">
                    <button wire:click="backToBack" style="padding: 14px 20px; border: 1px solid #E4E2DB; border-radius: 12px; background: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['b_back'] }}</button>
                    <button wire:click="finishBadge" style="flex: 1; padding: 14px; border: none; border-radius: 12px; background: #E85D2A; color: #fff; font-size: 15px; font-weight: 600; cursor: pointer;">{{ $L['b_finish'] }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
