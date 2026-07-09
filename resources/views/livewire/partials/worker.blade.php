@php
    $Ui = \App\Support\Ui::class;
    $Icon = \App\Support\Icons::class;
    $w = $worker['me'];
    $clockedIn = $clock === 'in';
    $clockDone = $clock === 'done';
    $clockBtnBase = 'display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:19px;border:none;border-radius:18px;font-size:18.5px;font-weight:800;color:#fff;';
    $clockBtnStyle = $clockDone
        ? $clockBtnBase.'background:#3A3D45;color:rgba(255,255,255,0.75);cursor:not-allowed;'
        : ($clockedIn
            ? $clockBtnBase.'background:linear-gradient(180deg,#E25A4C,#D9483B);cursor:pointer;box-shadow:0 10px 24px rgba(217,72,59,0.34),inset 0 1px 0 rgba(255,255,255,0.15);'
            : $clockBtnBase.'background:linear-gradient(180deg,#23B27C,#1F9D6B);cursor:pointer;box-shadow:0 10px 24px rgba(31,157,107,0.38),inset 0 1px 0 rgba(255,255,255,0.18);');
    $statusPillBg = $clockDone ? 'rgba(255,255,255,0.12)' : ($clockedIn ? 'rgba(74,222,128,0.15)' : 'rgba(255,255,255,0.1)');
    $statusPillColor = $clockDone ? 'rgba(255,255,255,0.8)' : ($clockedIn ? '#4ADE80' : 'rgba(255,255,255,0.7)');
    $noLunchRowStyle = 'display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:12px;padding:12px 14px;border-radius:14px;background:'.($noLunchToday?'rgba(74,222,128,0.12)':'rgba(255,255,255,0.06)').';border:1px solid '.($noLunchToday?'rgba(74,222,128,0.4)':'rgba(255,255,255,0.14)').';';
    $noLunchSwStyle = 'width:44px;height:26px;border-radius:20px;border:none;cursor:pointer;position:relative;flex-shrink:0;transition:background .15s;background:'.($noLunchToday?'#4ADE80':'rgba(255,255,255,0.25)').';';
    $noLunchKnobStyle = 'position:absolute;top:3px;left:'.($noLunchToday?'21px':'3px').';width:20px;height:20px;border-radius:50%;background:#fff;transition:left .15s;';
    $earlyIsCustom = $earlyReasonVal === '__custom__';
@endphp
{{-- ===== WORKER MOBILE ===== --}}
<div class="wf-phone-wrap" style="min-height: calc(100vh - 44px); display: flex; align-items: flex-start; justify-content: center; padding: 30px 16px;">
    <div class="wf-phone" style="width: 390px; max-width: 100%; background: #000; border-radius: 44px; padding: 11px; box-shadow: 0 30px 70px rgba(0,0,0,0.35);">
        <div class="wf-phone-inner" style="position: relative; background: #F4F3EF; border-radius: 34px; overflow: hidden; height: 800px; display: flex; flex-direction: column;">
            <div class="wf-phone-notch" style="position: absolute; top: 0; left: 50%; transform: translateX(-50%); width: 130px; height: 26px; background: #000; border-radius: 0 0 16px 16px; z-index: 30;"></div>
            <div class="wf-phone-status" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 24px 6px; font-size: 12px; font-weight: 600; color: #16181D;">
                <span class="wk-time" style="font-family: 'Space Grotesk';">{{ now()->format('g:i') }}</span>
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
                            <div style="display: inline-flex; align-items: center; gap: 7px; font-size: 12.5px; background: {{ $statusPillBg }}; color: {{ $statusPillColor }}; padding: 6px 14px; border-radius: 20px; font-weight: 600;"><span style="width: 8px; height: 8px; border-radius: 50%; background: {{ $statusPillColor }};"></span>{{ $clockDone ? $L['w_workDone'] : ($clockedIn ? $L['w_status_in'] : $L['w_status_out']) }}</div>
                            <div class="wk-bigtime" style="font-family: 'Space Grotesk'; font-size: 46px; font-weight: 700; margin-top: 16px;">{{ now()->format('g:i A') }}</div>
                            <div style="font-size: 12.5px; color: rgba(255,255,255,0.5);">{{ $w['role'] }}</div>
                            {{-- today's crew — updated live when the worker scans another crew's QR --}}
                            <div style="display: flex; align-items: center; gap: 8px; justify-content: center; margin-top: 11px; font-size: 12px; color: rgba(255,255,255,0.55);">
                                {{ $L['w_todayTeam'] }}
                                <span style="display: inline-flex; align-items: center; gap: 6px; background: rgba(59,114,224,0.22); color: #9DBCFF; padding: 3px 10px; border-radius: 7px; font-weight: 700; font-size: 12px;">{{ $w['teamName'] }}</span>
                            </div>
                            @if($clockedIn)
                                <div style="margin-top: 9px; font-size: 12.5px; color: #4ADE80;">{{ $L['w_since'] }} {{ $clockInTime }}</div>
                            @endif
                            <div style="margin-top: 18px;">
                                @if($clockDone)
                                    <button type="button" disabled style="{{ $clockBtnStyle }}">{{ $L['w_workDone'] }}</button>
                                @else
                                    {{-- capture GPS on tap; clock proceeds even if permission is denied (coords → null).
                                         busy latch: one punch per tap — repeat taps are ignored until the round-trip
                                         (GPS lookup + server call) finishes, so a double-tap can't clock straight back out --}}
                                    <button type="button" x-data="{ busy: false }" :disabled="busy" :style="busy ? { opacity: '0.55' } : {}"
                                        @click="
                                            if (busy) return;
                                            busy = true;
                                            const go = (la, ln, ac) => $wire.doClock(la, ln, ac).finally(() => busy = false);
                                            if (navigator.geolocation) {
                                                navigator.geolocation.getCurrentPosition(
                                                    p => go(p.coords.latitude, p.coords.longitude, p.coords.accuracy),
                                                    () => go(null, null, null),
                                                    { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 }
                                                );
                                            } else { go(null, null, null); }
                                        "
                                        style="{{ $clockBtnStyle }}">
                                        <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M12 7v5l3 3"/><circle cx="12" cy="12" r="9"/></svg>
                                        {{ $clockedIn ? $L['w_clockout'] : $L['w_clockin'] }}
                                    </button>
                                    {{-- moved crews today? scan that crew's QR — assigns today's crew (and clocks in if not yet in).
                                         Field leads run a fixed crew, so this worker-only option is hidden for them. --}}
                                    @if(! ($isFieldLead ?? false))
                                        <button type="button" @click="$dispatch('open-team-scan')" style="display: flex; align-items: center; gap: 12px; width: 100%; margin-top: 12px; padding: 13px 16px; border-radius: 15px; border: 1.5px dashed rgba(255,255,255,0.3); background: rgba(255,255,255,0.05); color: #fff; cursor: pointer; text-align: left;">
                                            <span style="width: 38px; height: 38px; border-radius: 10px; background: rgba(232,93,42,0.18); display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#E85D2A" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3M21 21v.01M17 21v.01M21 17v.01"/></svg></span>
                                            <span style="flex: 1;"><span style="display: block; font-size: 14px; font-weight: 700;">{{ $L['w_teamQrBtn'] }}</span><span style="display: block; font-size: 11.5px; color: rgba(255,255,255,0.5);">{{ $L['w_teamQrHint'] }}</span></span>
                                            <span style="color: rgba(255,255,255,0.4); font-size: 18px;">›</span>
                                        </button>
                                    @endif
                                @endif
                            </div>
                            @if($clockedIn)
                                <div style="{{ $noLunchRowStyle }}">
                                    <div style="text-align: left;"><div style="font-size: 13.5px; font-weight: 600; color: #fff;">{{ $L['w_noLunch'] }}</div><div style="font-size: 11px; color: rgba(255,255,255,0.5);">{{ $L['w_noLunchHint'] }}</div></div>
                                    <button wire:click="toggleNoLunch" style="{{ $noLunchSwStyle }}"><span style="{{ $noLunchKnobStyle }}"></span></button>
                                </div>
                            @endif
                            @if($clockedIn)
                                <button wire:click="openEarly" style="margin-top: 10px; width: 100%; padding: 13px; border: 1px solid rgba(255,255,255,0.22); border-radius: 16px; background: transparent; color: #F4C168; font-size: 15px; font-weight: 600; cursor: pointer;">{{ $L['w_early'] }}</button>
                            @endif
                        </div>

                        {{-- self-report exceptions (결근·휴가·퇴사): kept separate so the clock stays the hero --}}
                        <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 18px; padding: 15px 16px; margin-top: 14px;">
                            <div style="font-size: 13px; font-weight: 800; display: flex; align-items: center; gap: 7px;">🗓️ {{ $L['w_st_title'] }}</div>
                            <div style="font-size: 11px; color: #9A968C; margin-top: 2px; margin-bottom: 11px;">{{ $L['w_st_sub'] }}</div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
                                @unless($clockedIn)
                                    <button wire:click="openStatusSheet('absent')" style="display: flex; flex-direction: column; align-items: center; gap: 5px; padding: 12px 6px; border-radius: 13px; border: 1.5px solid #F3CFC9; background: #FDF6F5; cursor: pointer;">
                                        <span style="width: 30px; height: 30px; border-radius: 9px; background: #FBE9E7; display: flex; align-items: center; justify-content: center;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#C0392B" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg></span>
                                        <span style="font-size: 12.5px; font-weight: 700;">{{ $L['w_st_absent'] }}</span>
                                    </button>
                                @endunless
                                <button wire:click="openStatusSheet('leave')" style="display: flex; flex-direction: column; align-items: center; gap: 5px; padding: 12px 6px; border-radius: 13px; border: 1.5px solid #CBDBF5; background: #F6F9FE; cursor: pointer;">
                                    <span style="width: 30px; height: 30px; border-radius: 9px; background: #E9F1FB; display: flex; align-items: center; justify-content: center;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#3B72E0" stroke-width="2"><path d="M3 8h18M7 3v3M17 3v3M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/></svg></span>
                                    <span style="font-size: 12.5px; font-weight: 700;">{{ $L['w_st_leave'] }} <span style="font-size: 9px; font-weight: 700; padding: 1px 5px; border-radius: 5px; background: #E9F1FB; color: #3B72E0;">{{ $L['w_st_appr'] }}</span></span>
                                </button>
                                <button wire:click="openStatusSheet('resign')" style="display: flex; flex-direction: column; align-items: center; gap: 5px; padding: 12px 6px; border-radius: 13px; border: 1.5px solid #DDD9CF; background: #FAFAF8; cursor: pointer;">
                                    <span style="width: 30px; height: 30px; border-radius: 9px; background: #ECEBE6; display: flex; align-items: center; justify-content: center;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6B6E76" stroke-width="2"><path d="M16 17l5-5-5-5M21 12H9M13 21H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7"/></svg></span>
                                    <span style="font-size: 12.5px; font-weight: 700;">{{ $L['w_st_resign'] }} <span style="font-size: 9px; font-weight: 700; padding: 1px 5px; border-radius: 5px; background: #ECEBE6; color: #6B6E76;">{{ $L['w_st_appr'] }}</span></span>
                                </button>
                            </div>
                        </div>

                        {{-- team-QR scanner: camera + BarcodeDetector; the scanned crew becomes today's crew --}}
                        <div x-data="{
                                open: false, unsupported: false, stream: null, timer: null, det: null, sending: false,
                                async start() {
                                    this.open = true; this.unsupported = false; this.sending = false;
                                    if (!('BarcodeDetector' in window) || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { this.unsupported = true; return; }
                                    try {
                                        this.det = new BarcodeDetector({ formats: ['qr_code'] });
                                        this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                                        this.$refs.vid.srcObject = this.stream;
                                        this.tick();
                                    } catch (e) { this.unsupported = true; }
                                },
                                async tick() {
                                    if (!this.open || this.sending) return;
                                    try {
                                        const codes = await this.det.detect(this.$refs.vid);
                                        if (codes.length && codes[0].rawValue) { this.found(codes[0].rawValue); return; }
                                    } catch (e) {}
                                    this.timer = setTimeout(() => this.tick(), 250);
                                },
                                found(text) {
                                    this.sending = true;
                                    const send = (la, ln, ac) => $wire.assignTeamByQr(text, la, ln, ac).finally(() => this.stop());
                                    if (navigator.geolocation) {
                                        navigator.geolocation.getCurrentPosition(
                                            p => send(p.coords.latitude, p.coords.longitude, p.coords.accuracy),
                                            () => send(null, null, null),
                                            { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 });
                                    } else { send(null, null, null); }
                                },
                                stop() {
                                    this.open = false;
                                    clearTimeout(this.timer);
                                    if (this.stream) { this.stream.getTracks().forEach(t => t.stop()); this.stream = null; }
                                }
                            }"
                            @open-team-scan.window="start()" @keydown.escape.window="stop()">
                            <div x-show="open" x-cloak style="position: absolute; inset: 0; background: #0B0C0F; z-index: 60; display: flex; flex-direction: column; color: #fff;">
                                <div style="display: flex; align-items: center; gap: 10px; padding: 46px 18px 12px;">
                                    <button type="button" @click="stop()" style="width: 34px; height: 34px; border-radius: 50%; background: rgba(255,255,255,0.12); border: none; color: #fff; font-size: 16px; cursor: pointer;">✕</button>
                                    <div><div style="font-size: 16px; font-weight: 700;">{{ $L['w_scanTitle'] }}</div><div style="font-size: 11.5px; color: rgba(255,255,255,0.5);">{{ $L['w_scanSub'] }}</div></div>
                                </div>
                                <div style="flex: 1; position: relative; margin: 8px 18px; border-radius: 18px; overflow: hidden; background: #1B1D22;">
                                    <video x-ref="vid" autoplay playsinline muted style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover;"></video>
                                    <div style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none;">
                                        <div style="width: 200px; height: 200px; border: 3px solid rgba(232,93,42,0.9); border-radius: 18px; box-shadow: 0 0 0 2000px rgba(0,0,0,0.35);"></div>
                                    </div>
                                    <div x-show="unsupported" style="position: absolute; inset: 0; background: #16181D; display: flex; align-items: center; justify-content: center; text-align: center; padding: 28px; font-size: 13.5px; color: rgba(255,255,255,0.75); line-height: 1.6;">{{ $L['w_scanUnsupported'] }}</div>
                                </div>
                                <div style="text-align: center; font-size: 12.5px; color: rgba(255,255,255,0.55); padding: 10px 26px 26px;" x-text="sending ? '{{ $L['w_scanSending'] }}' : '{{ $L['w_scanHint'] }}'"></div>
                            </div>
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
                        {{-- ===== internal comms: announcements board + my chat rooms ===== --}}
                        @if($comms)
                            @if($comms['mobilePane'] === 'thread' && $comms['active'])
                                {{-- open conversation --}}
                                <div style="margin-top: 16px; background: #fff; border: 1px solid #E4E2DB; border-radius: 18px; overflow: hidden;">
                                    <div style="display: flex; align-items: center; gap: 8px; padding: 12px 14px; border-bottom: 1px solid #F0EEE8;">
                                        <button wire:click="commsBack" style="width: 30px; height: 30px; border: 1px solid #E4E2DB; border-radius: 9px; background: #fff; cursor: pointer; font-size: 16px; line-height: 1; color: #5A5D64;">‹</button>
                                        <div style="flex: 1; min-width: 0;"><div style="font-size: 14px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $comms['active']['title'] }}</div><div style="font-size: 11px; color: #A7A49B;">{{ $comms['active']['sub'] }}</div></div>
                                    </div>
                                    <div style="max-height: 300px; overflow: auto; padding: 14px; display: flex; flex-direction: column; gap: 9px;">
                                        @forelse($comms['active']['messages'] as $m)
                                            <div style="align-self: {{ $m['mine'] ? 'flex-end' : 'flex-start' }}; max-width: 82%;">
                                                @unless($m['mine'])<div style="font-size: 10.5px; color: #8A8880; margin: 0 0 2px 2px;">{{ $m['senderName'] }}</div>@endunless
                                                <div style="background: {{ $m['mine'] ? '#16181D' : '#F4F3EF' }}; color: {{ $m['mine'] ? '#fff' : '#16181D' }}; padding: 8px 12px; border-radius: 14px; font-size: 13.5px; line-height: 1.45; word-break: break-word;">{{ $m['body'] }}</div>
                                                <div style="font-size: 10px; color: #C7C4BB; margin-top: 2px; text-align: {{ $m['mine'] ? 'right' : 'left' }};">{{ $m['time'] }}</div>
                                            </div>
                                        @empty
                                            <div style="text-align: center; color: #A7A49B; font-size: 12.5px; padding: 18px;">{{ $comms['labels']['empty'] }}</div>
                                        @endforelse
                                    </div>
                                    @if($comms['active']['canPost'])
                                        <div style="display: flex; gap: 8px; padding: 11px 12px; border-top: 1px solid #F0EEE8;">
                                            <input wire:model="commsCompose" wire:keydown.enter="sendMessage" placeholder="{{ $comms['labels']['compose'] }}" style="flex: 1; min-width: 0; padding: 10px 12px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 13.5px; outline: none;"/>
                                            <button wire:click="sendMessage" style="padding: 10px 15px; border: none; border-radius: 10px; background: #E85D2A; color: #fff; font-size: 13px; font-weight: 600; cursor: pointer;">{{ $comms['labels']['send'] }}</button>
                                        </div>
                                    @else
                                        <div style="padding: 12px 16px; border-top: 1px solid #F0EEE8; font-size: 11.5px; color: #A7A49B; text-align: center;">{{ $comms['active']['readOnlyNote'] }}</div>
                                    @endif
                                </div>
                            @else
                                {{-- announcements board --}}
                                <div style="margin-top: 16px; background: #fff; border: 1px solid #E4E2DB; border-radius: 18px; padding: 16px 18px;">
                                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#E85D2A" stroke-width="2"><path d="M3 11l18-5v12L3 14v-3zM11.6 16.8a3 3 0 0 1-5.8-1.6"/></svg>
                                        <span style="font-size: 13.5px; font-weight: 700;">{{ $L['w_announce'] }}</span>
                                        @if($comms['annId'])<button wire:click="selectChannel({{ $comms['annId'] }})" style="margin-left: auto; font-size: 11.5px; color: #E85D2A; background: none; border: none; cursor: pointer; font-weight: 600;">{{ $L['w_viewAll'] }}</button>@endif
                                    </div>
                                    @forelse(array_slice($comms['annFeed'], 0, 3) as $a)
                                        <div style="padding: 9px 0; border-top: 1px solid #F4F3EF;">
                                            <div style="font-size: 13px; line-height: 1.5; color: #16181D; word-break: break-word;">{{ $a['body'] }}</div>
                                            <div style="font-size: 10.5px; color: #A7A49B; margin-top: 3px;">{{ $a['sender'] }} · {{ $a['time'] }}</div>
                                        </div>
                                    @empty
                                        <div style="font-size: 12.5px; color: #A7A49B; padding: 6px 0;">{{ $L['w_noAnnounce'] }}</div>
                                    @endforelse
                                </div>

                                {{-- my chat rooms --}}
                                <div style="margin-top: 14px; background: #fff; border: 1px solid #E4E2DB; border-radius: 18px; padding: 8px 6px;">
                                    <div style="font-size: 13.5px; font-weight: 700; padding: 10px 12px 4px;">{{ $L['w_myChats'] }}</div>
                                    @forelse($comms['myRooms'] as $r)
                                        <button wire:click="selectChannel({{ $r['id'] }})" style="width: 100%; display: flex; align-items: center; gap: 12px; padding: 11px 12px; border: none; background: none; cursor: pointer; text-align: left;">
                                            <span style="width: 38px; height: 38px; border-radius: {{ $r['type']==='dm' ? '50%' : '11px' }}; background: {{ $r['color'] }}; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; font-family: 'Space Grotesk'; flex-shrink: 0;">{{ $r['initials'] ?? mb_strtoupper(mb_substr($r['name'], 0, 1)) }}</span>
                                            <span style="flex: 1; min-width: 0;"><span style="display: block; font-size: 14px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $r['name'] }}</span><span style="display: block; font-size: 12px; color: #A7A49B; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $r['preview'] ?: '—' }}</span></span>
                                            <span style="text-align: right; flex-shrink: 0;"><span style="display: block; font-size: 10.5px; color: #C7C4BB;">{{ $r['time'] }}</span>@if($r['unread'] > 0)<span style="display: inline-block; margin-top: 3px; min-width: 18px; padding: 1px 6px; border-radius: 9px; background: #E85D2A; color: #fff; font-size: 10.5px; font-weight: 700; text-align: center;">{{ $r['unread'] }}</span>@endif</span>
                                        </button>
                                    @empty
                                        <div style="font-size: 12.5px; color: #A7A49B; padding: 8px 12px 14px;">{{ $L['w_noChats'] }}</div>
                                    @endforelse
                                </div>
                            @endif
                        @endif
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
                                        @if(($p['pid'] ?? null) !== null)
                                            <button wire:click="togglePunchLunch({{ $p['pid'] }})" style="{{ $Ui::lunchToggle($p['lunchIsNo']) }}">{{ $p['lunchToggleLabel'] }}</button>
                                        @else
                                            <button wire:click="toggleLunchRow('{{ $p['d'] }}', {{ $p['seedNoLunch'] ? 'true' : 'false' }})" style="{{ $Ui::lunchToggle($p['lunchIsNo']) }}">{{ $p['lunchToggleLabel'] }}</button>
                                        @endif
                                        @if(!empty($p['corrChip']))
                                            <span style="font-size: 10px; font-weight: 600; padding: 2px 8px; border-radius: 6px; background: {{ $p['corrChip']['bg'] }}; color: {{ $p['corrChip']['color'] }};">{{ $p['corrChip']['label'] }}</span>
                                        @endif
                                        @if(!empty($p['workDate']) && empty($p['corrPending']))
                                            <button wire:click="openCorrection('{{ $p['workDate'] }}')" style="font-size: 10px; font-weight: 600; padding: 2px 9px; border-radius: 6px; background: #16181D; color: #fff; border: none; cursor: pointer;">{{ $L['w_fix'] }}</button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @elseif($mobileTab === 'crew' && $crew)
                    <div style="padding: 12px 20px 20px;">
                        <div style="display: flex; align-items: baseline; justify-content: space-between; margin: 6px 0 4px;">
                            <div style="font-size: 18px; font-weight: 700;">{{ $L['w_tab_crew'] }}</div>
                            <div style="font-size: 12px; color: #8A8880;">{{ $crew['dateLabel'] }}</div>
                        </div>
                        <div style="font-size: 12.5px; color: #8A8880; margin-bottom: 14px;">{{ $L['ts_present'] }} <b style="color:#1F9D6B;">{{ $crew['present'] }}</b> / {{ $crew['count'] }}</div>

                        {{-- each crew's work shift + a phone editor to set it --}}
                        @foreach($crew['teams'] as $t)
                            <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; padding: 14px 16px; margin-bottom: 12px;">
                                <div style="display: flex; align-items: center; gap: 9px;">
                                    <span style="width: 10px; height: 10px; border-radius: 3px; background: {{ $t['color'] }};"></span>
                                    <span style="flex: 1; font-weight: 700; font-size: 14.5px;">{{ $t['name'] }}</span>
                                    <button wire:click="openCrewShift('{{ $t['id'] }}')" style="font-size: 12px; font-weight: 600; padding: 6px 12px; border-radius: 9px; border: 1px solid #E4E2DB; background: #FAFAF8; color: #3B72E0; cursor: pointer;">{{ $L['w_crew_setShift'] }}</button>
                                </div>
                                <div style="display: flex; gap: 18px; margin-top: 10px; font-size: 12.5px;">
                                    <div><span style="color: #8A8880;">{{ $L['pj_shiftWeekday'] }}</span><br><b style="font-family: 'Space Grotesk';">{{ $t['weekday'] ?? $L['w_crew_noShift'] }}</b></div>
                                    @if($t['saturday'])<div><span style="color: #8A8880;">{{ $L['pj_shiftSat'] }}</span><br><b style="font-family: 'Space Grotesk';">{{ $t['saturday'] }}</b></div>@endif
                                </div>
                            </div>
                        @endforeach

                        {{-- crew members' correction requests — the lead decides right here --}}
                        @if(!empty($crew['corrections']))
                            <div style="font-size: 13px; font-weight: 600; color: #8A8880; margin: 18px 0 8px;">{{ $L['w_crew_fixes'] }} <span style="color: #E85D2A;">{{ count($crew['corrections']) }}</span></div>
                            <div style="background: #fff; border: 1px solid #F3D9CB; border-radius: 16px; overflow: hidden;">
                                @foreach($crew['corrections'] as $c)
                                    <div style="padding: 12px 16px; border-bottom: 1px solid #F2F0EA;">
                                        <div style="display: flex; align-items: baseline; gap: 8px;">
                                            <span style="flex: 1; font-weight: 600; font-size: 13.5px;">{{ $c['name'] }}</span>
                                            <span style="font-size: 11.5px; color: #8A8880; font-family: 'Space Grotesk';">{{ $c['date'] }}</span>
                                        </div>
                                        <div style="margin-top: 4px; font-size: 12px; font-family: 'Space Grotesk';">
                                            @if($c['isDelete'])<span style="color: #C0522B; font-weight: 700;">{{ $L['w_crew_fixDelete'] }}</span>
                                            @else<span style="color: #16181D;">{{ $c['reqIn'] }} → {{ $c['reqOut'] }}</span>@endif
                                        </div>
                                        @if($c['reason'] !== '')<div style="margin-top: 3px; font-size: 11.5px; color: #8A8880;">“{{ $c['reason'] }}”</div>@endif
                                        @if($c['canDecide'])
                                            <div style="display: flex; gap: 8px; margin-top: 9px;">
                                                <button wire:click="approveCorrection({{ $c['id'] }})" style="flex: 1; padding: 9px; border: none; border-radius: 9px; background: #1F9D6B; color: #fff; font-size: 12.5px; font-weight: 700; cursor: pointer;">{{ $L['w_crew_approve'] }}</button>
                                                <button wire:click="rejectCorrection({{ $c['id'] }})" style="flex: 1; padding: 9px; border: 1px solid #E4E2DB; border-radius: 9px; background: #fff; color: #C0522B; font-size: 12.5px; font-weight: 700; cursor: pointer;">{{ $L['w_crew_reject'] }}</button>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        {{-- today's crew attendance — actual vs paid, with a lead adjust button --}}
                        <div style="font-size: 13px; font-weight: 600; color: #8A8880; margin: 18px 0 8px;">{{ $L['w_crew_today'] }}</div>
                        <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; overflow: hidden;">
                            @forelse($crew['rows'] as $r)
                                <div style="padding: 12px 16px; border-bottom: 1px solid #F2F0EA;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <span style="flex: 1; font-weight: 600; font-size: 13.5px;">{{ $r['name'] }}@if(!empty($r['adjusted']))<span style="margin-left: 6px; font-size: 9.5px; font-weight: 700; padding: 2px 6px; border-radius: 6px; background: #EAF1FC; color: #3B72E0;">{{ $L['ts_adjusted'] }}</span>@endif</span>
                                        @if(!empty($r['hasPunch']) && !empty($r['punchId']))
                                            <button wire:click="openAdjust({{ $r['punchId'] }})" style="font-size: 11.5px; font-weight: 600; padding: 5px 11px; border-radius: 8px; border: 1px solid #E4E2DB; background: #fff; color: #3B72E0; cursor: pointer;">{{ $L['ts_adjust'] }}</button>
                                        @endif
                                    </div>
                                    <div style="display: flex; gap: 14px; margin-top: 6px; font-family: 'Space Grotesk'; font-size: 12px;">
                                        <span style="color: #8A8880;">{{ $L['ts_actual'] }} <b style="color:#16181D;">{{ $r['actIn'] }} → {{ $r['onDuty'] ? $L['ts_onduty'] : $r['actOut'] }}</b></span>
                                        <span style="color: #8A8880;">{{ $L['ts_paidIn'] }} <b style="color:#16181D;">{{ $r['paidIn'] }} → {{ $r['paidOut'] }}</b></span>
                                    </div>
                                    <div style="margin-top: 5px; font-family: 'Space Grotesk'; font-size: 12px; font-weight: 700;">{{ $r['reg'] }}@if($r['ot'] !== '—')<span style="color:#C05621;"> +{{ $r['ot'] }} {{ $L['ts_ot'] }}</span>@endif</div>
                                </div>
                            @empty
                                <div style="padding: 26px 16px; text-align: center; color: #A7A49B; font-size: 13px;">{{ $L['w_crew_noOne'] }}</div>
                            @endforelse
                        </div>
                    </div>

                    {{-- crew shift editor (field lead, phone) --}}
                    @if($crewShiftTeam)
                        <div style="position: fixed; inset: 0; z-index: 90; background: rgba(22,24,29,0.5); display: flex; align-items: flex-end; justify-content: center;">
                            <div style="width: 100%; max-width: 460px; background: #fff; border-radius: 20px 20px 0 0; padding: 22px 20px 28px;">
                                <div style="font-size: 16px; font-weight: 700;">{{ $L['pj_shiftTitle'] }}</div>
                                <div style="font-size: 11.5px; color: #8A8880; margin-top: 4px; line-height: 1.45;">{{ $L['pj_shiftHint'] }}</div>
                                <div style="display: flex; gap: 10px; margin-top: 14px; align-items: flex-end;">
                                    <div style="width: 58px; font-size: 12px; font-weight: 600; padding-bottom: 11px;">{{ $L['pj_shiftWeekday'] }}</div>
                                    <label style="flex: 1;"><span style="font-size: 11px; color: #8A8880;">{{ $L['pj_shiftIn'] }}</span><input wire:model="teamShiftIn" type="time" style="width: 100%; margin-top: 4px; padding: 10px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 15px; outline: none; font-family: 'Space Grotesk';"/></label>
                                    <label style="flex: 1;"><span style="font-size: 11px; color: #8A8880;">{{ $L['pj_shiftOut'] }}</span><input wire:model="teamShiftOut" type="time" style="width: 100%; margin-top: 4px; padding: 10px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 15px; outline: none; font-family: 'Space Grotesk';"/></label>
                                </div>
                                <div style="display: flex; gap: 10px; margin-top: 10px; align-items: flex-end;">
                                    <div style="width: 58px; font-size: 12px; font-weight: 600; padding-bottom: 11px;">{{ $L['pj_shiftSat'] }}</div>
                                    <label style="flex: 1;"><span style="font-size: 11px; color: #8A8880;">{{ $L['pj_shiftIn'] }}</span><input wire:model="teamSatIn" type="time" style="width: 100%; margin-top: 4px; padding: 10px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 15px; outline: none; font-family: 'Space Grotesk';"/></label>
                                    <label style="flex: 1;"><span style="font-size: 11px; color: #8A8880;">{{ $L['pj_shiftOut'] }}</span><input wire:model="teamSatOut" type="time" style="width: 100%; margin-top: 4px; padding: 10px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 15px; outline: none; font-family: 'Space Grotesk';"/></label>
                                </div>
                                <div style="display: flex; gap: 10px; margin-top: 20px;">
                                    <button wire:click="closeCrewShift" style="flex: 1; padding: 13px; border: 1px solid #E4E2DB; border-radius: 12px; background: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['pj_cancel'] }}</button>
                                    <button wire:click="saveCrewShift" style="flex: 1; padding: 13px; border: none; border-radius: 12px; background: #E85D2A; color: #fff; font-size: 14px; font-weight: 700; cursor: pointer;">{{ $L['pj_save'] }}</button>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- paid-time adjust editor (field lead, phone) --}}
                    @if($adjPunchId)
                        @php $ar = collect($crew['rows'])->firstWhere('punchId', $adjPunchId); @endphp
                        <div style="position: fixed; inset: 0; z-index: 90; background: rgba(22,24,29,0.5); display: flex; align-items: flex-end; justify-content: center;">
                            <div style="width: 100%; max-width: 460px; background: #fff; border-radius: 20px 20px 0 0; padding: 22px 20px 28px;">
                                <div style="font-size: 16px; font-weight: 700;">{{ $L['ts_adjTitle'] }}</div>
                                @if($ar)
                                    <div style="font-size: 13.5px; font-weight: 600; margin-top: 5px;">{{ $ar['name'] }}</div>
                                    <div style="display: flex; gap: 16px; margin-top: 10px; padding: 11px 13px; background: #FAFAF8; border-radius: 12px; font-size: 12px;">
                                        @if($ar['shiftLabel'])<div><span style="color:#8A8880;">{{ $L['ts_shift'] }}</span><br><b style="font-family:'Space Grotesk';">{{ $ar['shiftLabel'] }}</b></div>@endif
                                        <div><span style="color:#8A8880;">{{ $L['ts_actual'] }}</span><br><b style="font-family:'Space Grotesk';">{{ $ar['actIn'] }} → {{ $ar['actOut'] }}</b></div>
                                    </div>
                                @endif
                                <div style="display: flex; gap: 10px; margin-top: 14px; align-items: flex-end;">
                                    <label style="flex: 1;"><span style="font-size: 11px; color: #8A8880;">{{ $L['ts_paidIn'] }}</span><input wire:model="adjPaidIn" type="time" style="width: 100%; margin-top: 4px; padding: 10px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 15px; outline: none; font-family: 'Space Grotesk';"/></label>
                                    <label style="flex: 1;"><span style="font-size: 11px; color: #8A8880;">{{ $L['ts_paidOut'] }}</span><input wire:model="adjPaidOut" type="time" style="width: 100%; margin-top: 4px; padding: 10px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 15px; outline: none; font-family: 'Space Grotesk';"/></label>
                                </div>
                                <div style="display: flex; gap: 7px; margin-top: 10px;">
                                    <button wire:click="bumpAdjust('out', 30)" style="padding: 7px 12px; border: 1px solid #E4E2DB; border-radius: 9px; background: #fff; font-size: 12px; font-weight: 600; cursor: pointer;">+30m {{ $L['ts_ot'] }}</button>
                                    <button wire:click="bumpAdjust('out', 60)" style="padding: 7px 12px; border: 1px solid #E4E2DB; border-radius: 9px; background: #fff; font-size: 12px; font-weight: 600; cursor: pointer;">+1h {{ $L['ts_ot'] }}</button>
                                    <button wire:click="bumpAdjust('out', -30)" style="padding: 7px 12px; border: 1px solid #E4E2DB; border-radius: 9px; background: #fff; color: #5A5D64; font-size: 12px; font-weight: 600; cursor: pointer;">−30m</button>
                                </div>
                                <label style="display: block; margin-top: 14px;"><span style="font-size: 11px; color: #8A8880;">{{ $L['ts_adjReason'] }}</span><input wire:model="adjPaidReason" placeholder="{{ $L['ts_adjReasonPh'] }}" style="width: 100%; margin-top: 4px; padding: 11px; border: 1px solid #E4E2DB; border-radius: 10px; font-size: 14px; outline: none;"/></label>
                                <div style="display: flex; gap: 10px; margin-top: 18px;">
                                    <button wire:click="closeAdjust" style="flex: 1; padding: 13px; border: 1px solid #E4E2DB; border-radius: 12px; background: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['e_cancel'] }}</button>
                                    @if(!empty($ar['adjusted']))
                                        <button wire:click="clearAdjust" style="padding: 13px 15px; border: 1px solid #F3D9CB; border-radius: 12px; background: #fff; color: #C0522B; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['ts_adjRemove'] }}</button>
                                    @endif
                                    <button wire:click="saveAdjust" style="flex: 1; padding: 13px; border: none; border-radius: 12px; background: #3B72E0; color: #fff; font-size: 14px; font-weight: 700; cursor: pointer;">{{ $L['ts_adjSave'] }}</button>
                                </div>
                            </div>
                        </div>
                    @endif
                @elseif($mobileTab === 'pay')
                    <div style="padding: 12px 20px 20px;">
                        <div style="font-size: 18px; font-weight: 700; margin: 6px 0 16px;">{{ $L['w_payslip'] }}</div>
                        <div style="background: linear-gradient(135deg,#E85D2A,#C74A20); border-radius: 20px; padding: 22px; color: #fff;">
                            <div style="font-size: 12.5px; opacity: 0.85;">{{ $L['w_est'] }} · {{ $pay['periodLabel'] }}</div>
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
                        {{-- my badge QR (moved here from the clock tab) --}}
                        <div style="margin-top: 16px; background: #fff; border: 1px solid #E4E2DB; border-radius: 18px; padding: 18px; text-align: center;">
                            <div style="font-size: 12.5px; color: #8A8880; margin-bottom: 12px;">{{ $L['w_myqr'] }}</div>
                            <div style="width: 150px; height: 150px; margin: 0 auto; background: #fff; border: 1px solid #F0EEE8; border-radius: 12px; padding: 10px;">{!! $worker['qrSvg'] !!}</div>
                            <div style="font-family: 'Space Grotesk'; font-size: 12px; color: #E85D2A; margin-top: 10px;">{{ $w['empId'] }}</div>
                            <div style="font-size: 11.5px; color: #A7A49B; margin-top: 2px;">{{ $L['w_qrhint'] }}</div>
                        </div>
                        <button wire:click="logout" style="width: 100%; margin-top: 16px; padding: 13px; border: 1px solid #E4E2DB; border-radius: 12px; background: #fff; font-size: 14px; font-weight: 600; color: #D9483B; cursor: pointer;">Logout</button>
                    </div>
                @endif
            </div>
            @php $cf = $worker['correctionForm'] ?? null; @endphp
            @if($cf)
                {{-- attendance-correction request sheet --}}
                <div style="position: absolute; inset: 0; z-index: 40; background: rgba(0,0,0,0.45); display: flex; align-items: flex-end;">
                    <div style="width: 100%; background: #F4F3EF; border-radius: 24px 24px 34px 34px; padding: 20px 20px 26px; max-height: 94%; overflow-y: auto;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                            <div style="font-size: 16px; font-weight: 700;">{{ $L['w_fixTitle'] }}</div>
                            <button wire:click="closeCorrection" style="border: none; background: transparent; font-size: 22px; line-height: 1; color: #8A8880; cursor: pointer;">×</button>
                        </div>
                        <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 14px; padding: 14px 16px;">
                            <div style="font-size: 13.5px; font-weight: 700;">{{ $cf['dateLabel'] }}</div>
                            <div style="font-size: 12px; color: #8A8880; margin-top: 4px;">{{ $cf['company'] }} · {{ $cf['team'] }}</div>
                            <div style="font-size: 12px; color: #8A8880; margin-top: 2px;">{{ $L['w_fixLead'] }}: {{ $cf['lead'] }}</div>
                        </div>
                        <div style="display: flex; gap: 8px; margin-top: 14px;">
                            <button wire:click="$set('correctionType','set')" style="flex: 1; padding: 10px; border-radius: 11px; border: 1.5px solid {{ $cf['type']==='set' ? '#16181D':'#E4E2DB' }}; background: {{ $cf['type']==='set' ? '#16181D':'#fff' }}; color: {{ $cf['type']==='set' ? '#fff':'#5A5D64' }}; font-size: 13px; font-weight: 600; cursor: pointer;">{{ $L['w_fixEdit'] }}</button>
                            <button wire:click="$set('correctionType','delete')" style="flex: 1; padding: 10px; border-radius: 11px; border: 1.5px solid {{ $cf['type']==='delete' ? '#D9483B':'#E4E2DB' }}; background: {{ $cf['type']==='delete' ? '#FBEAEA':'#fff' }}; color: {{ $cf['type']==='delete' ? '#D9483B':'#5A5D64' }}; font-size: 13px; font-weight: 600; cursor: pointer;">{{ $L['w_fixDelete'] }}</button>
                        </div>
                        @if($cf['type'] !== 'delete')
                            <div style="display: flex; gap: 10px; margin-top: 12px;">
                                <label style="flex: 1;"><span style="display: block; font-size: 11.5px; color: #8A8880; margin-bottom: 4px;">{{ $L['w_clockin'] }}</span><input type="time" wire:model="correctionIn" style="width: 100%; padding: 10px; border: 1.5px solid #E4E2DB; border-radius: 10px; font-size: 14px; outline: none; background: #fff;"></label>
                                <label style="flex: 1;"><span style="display: block; font-size: 11.5px; color: #8A8880; margin-bottom: 4px;">{{ $L['w_clockout'] }}</span><input type="time" wire:model="correctionOut" style="width: 100%; padding: 10px; border: 1.5px solid #E4E2DB; border-radius: 10px; font-size: 14px; outline: none; background: #fff;"></label>
                            </div>
                        @endif
                        <div style="margin-top: 12px;">
                            <span style="display: block; font-size: 11.5px; color: #8A8880; margin-bottom: 4px;">{{ $L['w_fixReason'] }}</span>
                            <textarea wire:model="correctionReason" rows="3" placeholder="{{ $L['w_fixReasonPh'] }}" style="width: 100%; padding: 11px; border: 1.5px solid #E4E2DB; border-radius: 10px; font-size: 13.5px; font-family: inherit; outline: none; resize: none;"></textarea>
                        </div>
                        <div style="display: flex; gap: 10px; margin-top: 16px;">
                            <button wire:click="closeCorrection" style="flex: 1; padding: 13px; border: 1px solid #E4E2DB; border-radius: 12px; background: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['w_cancel'] }}</button>
                            <button wire:click="submitCorrection" style="flex: 1; padding: 13px; border: none; border-radius: 12px; background: #E85D2A; color: #fff; font-size: 14px; font-weight: 700; cursor: pointer;">{{ $L['w_fixSend'] }}</button>
                        </div>
                    </div>
                </div>
            @endif
            {{-- self-report sheets: 결근 (즉시) · 휴가/퇴사 (신청→승인) --}}
            @if($statusSheet === 'absent')
                <div style="position: absolute; inset: 0; z-index: 60; background: rgba(22,24,29,0.45); display: flex; align-items: flex-end;">
                    <div style="width: 100%; background: #fff; border-radius: 22px 22px 0 0; padding: 22px 20px 28px;">
                        <div style="font-size: 17px; font-weight: 800;">{{ $L['w_st_absentT'] }}</div>
                        <div style="font-size: 12.5px; color: #8A8880; margin: 4px 0 14px;">{{ $L['w_st_absentTs'] }}</div>
                        <label style="display: block;"><span style="font-size: 11.5px; color: #8A8880;">{{ $L['w_st_reason'] }}</span><input wire:model="absentReason" placeholder="{{ $L['w_st_reasonPh'] }}" style="width: 100%; margin-top: 5px; padding: 12px; border: 1px solid #E4E2DB; border-radius: 12px; font-size: 14px; outline: none;"/></label>
                        <div style="display: flex; gap: 10px; margin-top: 18px;">
                            <button wire:click="closeStatusSheet" style="flex: 1; padding: 13px; border: 1px solid #E4E2DB; border-radius: 12px; background: #fff; font-size: 14px; font-weight: 700; cursor: pointer;">{{ $L['w_cancel'] }}</button>
                            <button wire:click="reportAbsent" style="flex: 1.3; padding: 13px; border: none; border-radius: 12px; background: #C0392B; color: #fff; font-size: 14px; font-weight: 800; cursor: pointer;">{{ $L['w_st_report'] }}</button>
                        </div>
                    </div>
                </div>
            @elseif($statusSheet === 'leave')
                <div style="position: absolute; inset: 0; z-index: 60; background: rgba(22,24,29,0.45); display: flex; align-items: flex-end;">
                    <div style="width: 100%; background: #fff; border-radius: 22px 22px 0 0; padding: 22px 20px 28px;">
                        <div style="font-size: 17px; font-weight: 800;">{{ $L['w_st_leaveT'] }}</div>
                        <div style="font-size: 12.5px; color: #8A8880; margin: 4px 0 14px;">{{ $L['w_st_leaveTs'] }}</div>
                        <div style="display: flex; gap: 10px;">
                            <label style="flex: 1;"><span style="font-size: 11.5px; color: #8A8880;">{{ $L['w_st_start'] }}</span><input wire:model="leaveStart" type="date" style="width: 100%; margin-top: 5px; padding: 11px; border: 1px solid #E4E2DB; border-radius: 12px; font-size: 14px; outline: none; font-family: 'Space Grotesk';"/></label>
                            <label style="flex: 1;"><span style="font-size: 11.5px; color: #8A8880;">{{ $L['w_st_end'] }}</span><input wire:model="leaveEnd" type="date" style="width: 100%; margin-top: 5px; padding: 11px; border: 1px solid #E4E2DB; border-radius: 12px; font-size: 14px; outline: none; font-family: 'Space Grotesk';"/></label>
                        </div>
                        <label style="display: block; margin-top: 11px;"><span style="font-size: 11.5px; color: #8A8880;">{{ $L['w_st_reason'] }}</span><input wire:model="leaveReason" placeholder="{{ $L['w_st_leavePh'] }}" style="width: 100%; margin-top: 5px; padding: 12px; border: 1px solid #E4E2DB; border-radius: 12px; font-size: 14px; outline: none;"/></label>
                        <div style="display: flex; gap: 10px; margin-top: 18px;">
                            <button wire:click="closeStatusSheet" style="flex: 1; padding: 13px; border: 1px solid #E4E2DB; border-radius: 12px; background: #fff; font-size: 14px; font-weight: 700; cursor: pointer;">{{ $L['w_cancel'] }}</button>
                            <button wire:click="saveLeave" style="flex: 1.3; padding: 13px; border: none; border-radius: 12px; background: #3B72E0; color: #fff; font-size: 14px; font-weight: 800; cursor: pointer;">{{ $L['w_st_send'] }}</button>
                        </div>
                    </div>
                </div>
            @elseif($statusSheet === 'resign')
                <div style="position: absolute; inset: 0; z-index: 60; background: rgba(22,24,29,0.45); display: flex; align-items: flex-end;">
                    <div style="width: 100%; background: #fff; border-radius: 22px 22px 0 0; padding: 22px 20px 28px;">
                        <div style="font-size: 17px; font-weight: 800;">{{ $L['w_st_resignT'] }}</div>
                        <div style="font-size: 12.5px; color: #8A8880; margin: 4px 0 14px;">{{ $L['w_st_resignTs'] }}</div>
                        <label style="display: block;"><span style="font-size: 11.5px; color: #8A8880;">{{ $L['w_st_lastDay'] }}</span><input wire:model="resignOn" type="date" style="width: 100%; margin-top: 5px; padding: 11px; border: 1px solid #E4E2DB; border-radius: 12px; font-size: 14px; outline: none; font-family: 'Space Grotesk';"/></label>
                        <label style="display: block; margin-top: 11px;"><span style="font-size: 11.5px; color: #8A8880;">{{ $L['w_st_reason'] }}</span><input wire:model="resignReason" placeholder="{{ $L['w_st_resignPh'] }}" style="width: 100%; margin-top: 5px; padding: 12px; border: 1px solid #E4E2DB; border-radius: 12px; font-size: 14px; outline: none;"/></label>
                        <div style="display: flex; gap: 10px; margin-top: 18px;">
                            <button wire:click="closeStatusSheet" style="flex: 1; padding: 13px; border: 1px solid #E4E2DB; border-radius: 12px; background: #fff; font-size: 14px; font-weight: 700; cursor: pointer;">{{ $L['w_cancel'] }}</button>
                            <button wire:click="saveResign" style="flex: 1.3; padding: 13px; border: none; border-radius: 12px; background: #6B6E76; color: #fff; font-size: 14px; font-weight: 800; cursor: pointer;">{{ $L['w_st_send'] }}</button>
                        </div>
                    </div>
                </div>
            @endif
            <div style="position: absolute; bottom: 0; left: 0; right: 0; display: flex; background: rgba(255,255,255,0.94); backdrop-filter: blur(10px); border-top: 1px solid #E4E2DB; padding: 8px 6px 22px;">
                @foreach($mobileTabs as $tab)
                    <button wire:click="setMobileTab('{{ $tab['key'] }}')" style="flex:1;display:flex;flex-direction:column;align-items:center;gap:0;padding:6px 0;border:none;background:transparent;cursor:pointer;color:{{ $tab['active'] ? '#E85D2A' : '#9AA0A6' }};"><span>{!! $Icon::mobile($tab['key']) !!}</span><span style="font-size: 10.5px; margin-top: 2px;">{{ $tab['label'] }}</span></button>
                @endforeach
            </div>
        </div>
    </div>
</div>
