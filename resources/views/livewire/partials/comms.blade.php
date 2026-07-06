@php
    $C = $comms;
    $lab = $C['labels'];
    $act = $C['active'];
@endphp

{{-- ============ INTERNAL COMMS ============ --}}
{{-- desktop: list + thread side by side · mobile: one pane at a time (master-detail) --}}
<div class="wf-comms {{ $C['mobilePane'] === 'thread' ? 'show-thread' : 'show-list' }}" style="display: grid; grid-template-columns: 300px 1fr; gap: 16px; height: calc(100vh - 44px - 71px - 52px); min-height: 460px;">

    {{-- ---------- channel list ---------- --}}
    <div class="wf-comms-list" style="background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; display: flex; flex-direction: column; overflow: hidden;">
        <div style="padding: 16px 16px 12px; border-bottom: 1px solid #F0EEE8; display: flex; align-items: center; justify-content: space-between; gap: 8px;">
            <div style="font-family: 'Space Grotesk'; font-weight: 700; font-size: 15px;">{{ $lab['title'] }}</div>
            <button wire:click="toggleNewDm" style="display: inline-flex; align-items: center; gap: 5px; padding: 6px 11px; border: none; border-radius: 9px; background: #16181D; color: #fff; font-size: 12px; font-weight: 600; cursor: pointer;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>{{ $lab['newChat'] }}
            </button>
        </div>

        <div style="flex: 1; overflow-y: auto; padding: 8px;">
            @if($C['newDm'])
                {{-- new chat: multi-select — pick one → DM, several → a group room (KakaoTalk-style) --}}
                <div style="padding: 6px 6px 10px;">
                    <input type="text" wire:model="commsRoomName" placeholder="{{ $lab['roomNamePh'] }}"
                        style="width: 100%; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #FAFAF8; margin-bottom: 6px;">
                    <input type="text" wire:model.live.debounce.250ms="commsDmSearch" placeholder="{{ $lab['dmSearchPh'] }}"
                        style="width: 100%; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #FAFAF8;">
                    <div style="margin-top: 8px; display: flex; flex-direction: column; gap: 2px;">
                        @forelse($C['dmCandidates'] as $p)
                            <button wire:click="togglePick({{ $p['id'] }})" style="display: flex; align-items: center; gap: 10px; width: 100%; text-align: left; padding: 8px; border: none; border-radius: 10px; background: {{ $p['picked'] ? '#EAF7F5' : 'transparent' }}; cursor: pointer;">
                                <span style="display: inline-flex; width: 32px; height: 32px; border-radius: 50%; background: {{ $p['color'] }}; color: #fff; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; flex-shrink: 0;">{{ $p['initials'] }}</span>
                                <span style="flex: 1; min-width: 0;"><span style="display: block; font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $p['name'] }}</span><span style="font-size: 11.5px; color: #A7A49B;">{{ $p['role'] }}</span></span>
                                <span style="flex-shrink: 0; width: 20px; height: 20px; border-radius: 50%; border: 1.5px solid {{ $p['picked'] ? '#0EA5A0' : '#D6D3CB' }}; background: {{ $p['picked'] ? '#0EA5A0' : 'transparent' }}; display: inline-flex; align-items: center; justify-content: center; color: #fff;">@if($p['picked'])<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M20 6L9 17l-5-5"/></svg>@endif</span>
                            </button>
                        @empty
                            <div style="padding: 18px 8px; text-align: center; color: #A7A49B; font-size: 12.5px;">—</div>
                        @endforelse
                    </div>
                    <div style="display: flex; gap: 8px; margin-top: 10px;">
                        <button wire:click="toggleNewDm" style="flex: 1; padding: 8px; border: 1px solid #E4E2DB; border-radius: 9px; background: #fff; color: #8A8880; font-size: 12px; font-weight: 600; cursor: pointer;">{{ $lab['cancel'] }}</button>
                        <button wire:click="createChat" @disabled($C['pickedCount'] === 0) style="flex: 1.4; padding: 8px; border: none; border-radius: 9px; background: {{ $C['pickedCount'] ? '#16181D' : '#C4C1B8' }}; color: #fff; font-size: 12px; font-weight: 700; cursor: {{ $C['pickedCount'] ? 'pointer' : 'not-allowed' }};">{{ $lab['createChat'] }}@if($C['pickedCount']) · {{ $C['pickedCount'] }}@endif</button>
                    </div>
                </div>
            @else
                @foreach(['announcement' => $lab['announcements'], 'correction' => $lab['corrections'], 'group' => $lab['rooms'], 'dm' => $lab['dms']] as $gkey => $glabel)
                    @if(count($C['groups'][$gkey]))
                        <div style="padding: 12px 8px 5px; font-size: 10.5px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #A7A49B;">{{ $glabel }}</div>
                        @foreach($C['groups'][$gkey] as $r)
                            @php $isActive = $act && $act['id'] === $r['id']; @endphp
                            <button wire:click="selectChannel({{ $r['id'] }})" wire:key="room-{{ $r['id'] }}"
                                style="display: flex; align-items: center; gap: 11px; width: 100%; text-align: left; padding: 9px 8px; border: none; border-radius: 11px; background: {{ $isActive ? '#FDF0EA' : 'transparent' }}; cursor: pointer; margin-top: 1px;"
                                @if(!$isActive) onmouseover="this.style.background='#F5F3EE'" onmouseout="this.style.background='transparent'" @endif>
                                @if($gkey === 'announcement')
                                    <span style="display: inline-flex; width: 34px; height: 34px; border-radius: 10px; background: #16181D; color: #fff; align-items: center; justify-content: center; flex-shrink: 0;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l18-5v12L3 14v-3zM11.6 16.8a3 3 0 0 1-5.8-1.6"/></svg></span>
                                @elseif($gkey === 'correction')
                                    <span style="display: inline-flex; width: 34px; height: 34px; border-radius: 10px; background: #E8A33D; color: #fff; align-items: center; justify-content: center; flex-shrink: 0;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
                                @elseif($gkey === 'dm')
                                    <span style="display: inline-flex; width: 34px; height: 34px; border-radius: 50%; background: {{ $r['color'] }}; color: #fff; align-items: center; justify-content: center; font-size: 12.5px; font-weight: 600; flex-shrink: 0;">{{ $r['initials'] }}</span>
                                @else
                                    <span style="display: inline-flex; width: 34px; height: 34px; border-radius: 10px; background: {{ $r['color'] }}; color: #fff; align-items: center; justify-content: center; flex-shrink: 0;">#</span>
                                @endif
                                <span style="flex: 1; min-width: 0;">
                                    <span style="display: flex; align-items: center; gap: 6px;">
                                        <span style="flex: 1; font-size: 13.5px; font-weight: {{ $r['unread'] ? '700' : '600' }}; color: #16181D; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $r['name'] }}</span>
                                        @if($r['time'])<span style="font-size: 10.5px; color: #B7B4AB; flex-shrink: 0;">{{ $r['time'] }}</span>@endif
                                    </span>
                                    <span style="display: flex; align-items: center; gap: 6px; margin-top: 2px;">
                                        <span style="flex: 1; font-size: 12px; color: {{ $r['unread'] ? '#5A5D64' : '#A7A49B' }}; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $r['preview'] ?: '—' }}</span>
                                        @if($r['unread'])<span style="flex-shrink: 0; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 9px; background: #E85D2A; color: #fff; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center;">{{ $r['unread'] }}</span>@endif
                                    </span>
                                </span>
                            </button>
                        @endforeach
                    @endif
                @endforeach
            @endif
        </div>
    </div>

    {{-- ---------- thread ---------- --}}
    <div class="wf-comms-thread" style="background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; display: flex; flex-direction: column; overflow: hidden;">
        @if($act)
            <div style="padding: 15px 20px; border-bottom: 1px solid #F0EEE8; display: flex; align-items: center; gap: 12px;">
                {{-- mobile-only: back to the channel list --}}
                <button type="button" wire:click="commsBack" class="wf-comms-back" aria-label="{{ $lab['back'] }}"
                    style="display: none; align-items: center; justify-content: center; width: 34px; height: 34px; margin: -3px -2px -3px -6px; border: none; border-radius: 9px; background: #F4F3EF; color: #16181D; cursor: pointer; flex-shrink: 0;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                </button>
                @if($act['type'] === 'dm')
                    <span style="display: inline-flex; width: 38px; height: 38px; border-radius: 50%; background: {{ $act['partnerColor'] }}; color: #fff; align-items: center; justify-content: center; font-size: 13px; font-weight: 600;">{{ $act['partnerInitials'] }}</span>
                @elseif($act['type'] === 'announcement')
                    <span style="display: inline-flex; width: 38px; height: 38px; border-radius: 11px; background: #16181D; color: #fff; align-items: center; justify-content: center;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l18-5v12L3 14v-3zM11.6 16.8a3 3 0 0 1-5.8-1.6"/></svg></span>
                @elseif($act['type'] === 'correction')
                    <span style="display: inline-flex; width: 38px; height: 38px; border-radius: 11px; background: #E8A33D; color: #fff; align-items: center; justify-content: center;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
                @elseif($act['type'] === 'group')
                    <span style="display: inline-flex; width: 38px; height: 38px; border-radius: 11px; background: #0EA5A0; color: #fff; align-items: center; justify-content: center;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                @endif
                <div style="flex: 1; min-width: 0;">
                    <div style="font-family: 'Space Grotesk'; font-weight: 700; font-size: 16px;">{{ $act['title'] }}</div>
                    <div style="font-size: 12px; color: #8A8880;">{{ $act['sub'] }}</div>
                </div>
                @if($act['isGroup'] ?? false)
                    <button wire:click="openInvite" style="display: inline-flex; align-items: center; gap: 5px; padding: 6px 11px; border: 1px solid #E4E2DB; border-radius: 9px; background: #fff; color: #16181D; font-size: 12px; font-weight: 600; cursor: pointer; flex-shrink: 0;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M20 8v6M23 11h-6"/></svg>{{ $lab['invite'] }}
                    </button>
                    <button wire:click="leaveActiveRoom" title="{{ $lab['leaveRoom'] }}" style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border: 1px solid #F3D9CB; border-radius: 9px; background: #fff; color: #D9483B; cursor: pointer; flex-shrink: 0;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                    </button>
                @endif
            </div>

            {{-- invite people to this group room --}}
            @if(($C['inviteOpen'] ?? false) && ($act['isGroup'] ?? false))
                <div style="padding: 12px 20px; border-bottom: 1px solid #F0EEE8; background: #FBFAF7;">
                    <input type="text" wire:model.live.debounce.250ms="commsDmSearch" placeholder="{{ $lab['dmSearchPh'] }}"
                        style="width: 100%; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #fff;">
                    <div style="max-height: 200px; overflow-y: auto; margin-top: 8px; display: flex; flex-direction: column; gap: 2px;">
                        @forelse($C['dmCandidates'] as $p)
                            <button wire:click="togglePick({{ $p['id'] }})" style="display: flex; align-items: center; gap: 10px; width: 100%; text-align: left; padding: 7px; border: none; border-radius: 10px; background: {{ $p['picked'] ? '#EAF7F5' : 'transparent' }}; cursor: pointer;">
                                <span style="display: inline-flex; width: 30px; height: 30px; border-radius: 50%; background: {{ $p['color'] }}; color: #fff; align-items: center; justify-content: center; font-size: 11.5px; font-weight: 600; flex-shrink: 0;">{{ $p['initials'] }}</span>
                                <span style="flex: 1; min-width: 0; font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $p['name'] }}</span>
                                <span style="flex-shrink: 0; width: 19px; height: 19px; border-radius: 50%; border: 1.5px solid {{ $p['picked'] ? '#0EA5A0' : '#D6D3CB' }}; background: {{ $p['picked'] ? '#0EA5A0' : 'transparent' }}; display: inline-flex; align-items: center; justify-content: center; color: #fff;">@if($p['picked'])<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M20 6L9 17l-5-5"/></svg>@endif</span>
                            </button>
                        @empty
                            <div style="padding: 14px 8px; text-align: center; color: #A7A49B; font-size: 12.5px;">—</div>
                        @endforelse
                    </div>
                    <div style="display: flex; gap: 8px; margin-top: 10px;">
                        <button wire:click="$set('commsInviteOpen', false)" style="flex: 1; padding: 8px; border: 1px solid #E4E2DB; border-radius: 9px; background: #fff; color: #8A8880; font-size: 12px; font-weight: 600; cursor: pointer;">{{ $lab['cancel'] }}</button>
                        <button wire:click="inviteMembers" @disabled($C['pickedCount'] === 0) style="flex: 1.4; padding: 8px; border: none; border-radius: 9px; background: {{ $C['pickedCount'] ? '#0EA5A0' : '#C4C1B8' }}; color: #fff; font-size: 12px; font-weight: 700; cursor: {{ $C['pickedCount'] ? 'pointer' : 'not-allowed' }};">{{ $lab['inviteAdd'] }}@if($C['pickedCount']) · {{ $C['pickedCount'] }}@endif</button>
                    </div>
                </div>
            @endif

            @if($act['isCorrection'] ?? false)
                {{-- attendance-correction approval queue --}}
                <div style="flex: 1; overflow-y: auto; padding: 18px; display: flex; flex-direction: column; gap: 12px; background: linear-gradient(180deg,#FBFAF7,#F6F5F0);">
                    @forelse($act['corrections'] as $c)
                        <div wire:key="corr-{{ $c['id'] }}" style="background: #fff; border: 1px solid #E9E6DE; border-radius: 14px; padding: 14px 16px;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="display: inline-flex; width: 34px; height: 34px; border-radius: 50%; background: #E8A33D; color: #fff; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; flex-shrink: 0;">{{ $c['workerInitials'] }}</span>
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-size: 14px; font-weight: 700;">{{ $c['worker'] }}</div>
                                    <div style="font-size: 11.5px; color: #8A8880;">{{ $c['dateLabel'] }}</div>
                                </div>
                            </div>
                            <div style="font-size: 11.5px; color: #8A8880; margin-top: 9px;">{{ $c['company'] }} · {{ $c['team'] }} · {{ $c['lead'] }}</div>
                            @if($c['isDelete'])
                                <div style="margin-top: 9px; font-size: 13px; color: #B23B3B; font-weight: 600;">{{ $lab['corrDelete'] }}</div>
                            @else
                                <div style="display: flex; align-items: flex-end; gap: 14px; margin-top: 9px; font-family: 'Space Grotesk'; font-size: 13px;">
                                    <div><div style="font-size: 10px; color: #A7A49B; text-transform: uppercase; letter-spacing: .05em;">{{ $lab['corrCurrent'] }}</div><div style="color: #8A8880;">↓ {{ $c['origIn'] }} · ↑ {{ $c['origOut'] }}</div></div>
                                    <div style="color: #C4C1B8; padding-bottom: 1px;">→</div>
                                    <div><div style="font-size: 10px; color: #A7A49B; text-transform: uppercase; letter-spacing: .05em;">{{ $lab['corrRequested'] }}</div><div style="color: #1F9D6B; font-weight: 700;">↓ {{ $c['reqIn'] }} · ↑ {{ $c['reqOut'] }}</div></div>
                                </div>
                            @endif
                            <div style="margin-top: 10px; font-size: 12.5px; color: #5A5D64; background: #F7F5F0; border-radius: 9px; padding: 9px 11px;"><span style="color: #A7A49B;">{{ $lab['corrReason'] }}:</span> {{ $c['reason'] }}</div>
                            @if($c['canDecide'])
                                @if(($act['rejectingId'] ?? null) === $c['id'])
                                    <div style="margin-top: 10px;">
                                        <input type="text" wire:model="rejectNote" placeholder="{{ $lab['corrRejectPh'] }}" style="width: 100%; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #FAFAF8;">
                                        <div style="display: flex; gap: 8px; margin-top: 8px;">
                                            <button wire:click="cancelReject" style="flex: 1; padding: 8px; border: 1px solid #E4E2DB; border-radius: 9px; background: #fff; color: #8A8880; font-size: 12.5px; font-weight: 600; cursor: pointer;">{{ $lab['cancel'] }}</button>
                                            <button wire:click="rejectCorrection({{ $c['id'] }})" style="flex: 1; padding: 8px; border: none; border-radius: 9px; background: #D9483B; color: #fff; font-size: 12.5px; font-weight: 700; cursor: pointer;">{{ $lab['corrConfirmReject'] }}</button>
                                        </div>
                                    </div>
                                @elseif(($act['adjustingId'] ?? null) === $c['id'])
                                    {{-- approver edits the times, then approves — applied straight to the punch --}}
                                    <div style="margin-top: 10px;">
                                        <div style="font-size: 11px; color: #A7A49B; margin-bottom: 7px;">{{ $lab['corrAdjustHint'] }}</div>
                                        <div style="display: flex; gap: 8px;">
                                            <label style="flex: 1;"><span style="display: block; font-size: 10.5px; color: #8A8880; margin-bottom: 3px;">↓ {{ $lab['corrIn'] }}</span><input type="time" wire:model="adjustIn" style="width: 100%; padding: 9px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #FAFAF8;"></label>
                                            <label style="flex: 1;"><span style="display: block; font-size: 10.5px; color: #8A8880; margin-bottom: 3px;">↑ {{ $lab['corrOut'] }}</span><input type="time" wire:model="adjustOut" style="width: 100%; padding: 9px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #FAFAF8;"></label>
                                        </div>
                                        <div style="display: flex; gap: 8px; margin-top: 8px;">
                                            <button wire:click="cancelAdjust" style="flex: 1; padding: 8px; border: 1px solid #E4E2DB; border-radius: 9px; background: #fff; color: #8A8880; font-size: 12.5px; font-weight: 600; cursor: pointer;">{{ $lab['cancel'] }}</button>
                                            <button wire:click="approveAdjusted({{ $c['id'] }})" style="flex: 1.4; padding: 8px; border: none; border-radius: 9px; background: #1F9D6B; color: #fff; font-size: 12.5px; font-weight: 700; cursor: pointer;">{{ $lab['corrConfirmAdjust'] }}</button>
                                        </div>
                                    </div>
                                @else
                                    <div style="display: flex; gap: 8px; margin-top: 12px;">
                                        <button wire:click="approveCorrection({{ $c['id'] }})" style="flex: 1; padding: 9px; border: none; border-radius: 9px; background: #1F9D6B; color: #fff; font-size: 12.5px; font-weight: 700; cursor: pointer;">{{ $lab['corrApprove'] }}</button>
                                        @unless($c['isDelete'])
                                            <button wire:click="askAdjustCorrection({{ $c['id'] }})" style="flex: 1; padding: 9px; border: 1px solid #16181D; border-radius: 9px; background: #fff; color: #16181D; font-size: 12.5px; font-weight: 600; cursor: pointer;">{{ $lab['corrAdjust'] }}</button>
                                        @endunless
                                        <button wire:click="askRejectCorrection({{ $c['id'] }})" style="flex: 1; padding: 9px; border: 1px solid #E4E2DB; border-radius: 9px; background: #fff; color: #D9483B; font-size: 12.5px; font-weight: 600; cursor: pointer;">{{ $lab['corrReject'] }}</button>
                                    </div>
                                @endif
                            @endif
                        </div>
                    @empty
                        <div style="flex: 1; display: flex; align-items: center; justify-content: center; color: #B7B4AB; font-size: 13px;">{{ $act['corrEmpty'] }}</div>
                    @endforelse
                </div>
            @else
            {{-- messages --}}
            <div class="wf-thread" x-data x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
                 style="flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 3px; background: linear-gradient(180deg,#FBFAF7,#F6F5F0);">
                @forelse($act['messages'] as $i => $m)
                    @php
                        $prev = $act['messages'][$i - 1] ?? null;
                        $grouped = $prev && $prev['mine'] === $m['mine'] && $prev['senderName'] === $m['senderName'];
                    @endphp
                    <div wire:key="msg-{{ $m['id'] }}" style="display: flex; gap: 9px; margin-top: {{ $grouped ? '1px' : '12px' }}; {{ $m['mine'] ? 'flex-direction: row-reverse;' : '' }}">
                        <span style="width: 30px; flex-shrink: 0;">
                            @if(!$grouped && !$m['mine'])
                                <span style="display: inline-flex; width: 30px; height: 30px; border-radius: 50%; background: {{ $m['color'] }}; color: #fff; align-items: center; justify-content: center; font-size: 11px; font-weight: 600;">{{ $m['initials'] }}</span>
                            @endif
                        </span>
                        <div style="max-width: 64%; display: flex; flex-direction: column; align-items: {{ $m['mine'] ? 'flex-end' : 'flex-start' }};">
                            @if(!$grouped)
                                <div style="font-size: 11.5px; color: #8A8880; margin-bottom: 3px; padding: 0 3px;">{{ $m['mine'] ? '' : $m['senderName'] }}<span style="color: #B7B4AB;"> · {{ $m['time'] }}</span></div>
                            @endif
                            <div style="padding: 9px 13px; border-radius: 14px; font-size: 13.5px; line-height: 1.45; white-space: pre-wrap; word-break: break-word; {{ $m['mine'] ? 'background: #16181D; color: #fff; border-bottom-right-radius: 4px;' : 'background: #fff; color: #16181D; border: 1px solid #ECEAE3; border-bottom-left-radius: 4px;' }}">{{ $m['body'] }}</div>
                        </div>
                    </div>
                @empty
                    <div style="flex: 1; display: flex; align-items: center; justify-content: center; color: #B7B4AB; font-size: 13px;">{{ $lab['empty'] }}</div>
                @endforelse
            </div>

            {{-- composer --}}
            @if($act['canPost'])
                <form wire:submit.prevent="sendMessage" style="padding: 12px 16px; border-top: 1px solid #F0EEE8; display: flex; align-items: flex-end; gap: 10px; background: #fff;">
                    @if($act['isGroup'] ?? false)
                        {{-- voice daily report: dictate → AI-format → post --}}
                        <button type="button" wire:click="openReport" title="{{ $lab['reportTitle'] }}" style="display: inline-flex; align-items: center; gap: 6px; padding: 11px 13px; border: 1.5px solid #E4E2DB; border-radius: 12px; background: #FAFAF8; color: #16181D; font-size: 13px; font-weight: 600; cursor: pointer; flex-shrink: 0;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#E85D2A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10a7 7 0 0 0 14 0M12 17v4M8 21h8"/></svg>
                            <span class="wf-report-label">{{ $lab['report'] }}</span>
                        </button>
                    @endif
                    <textarea wire:model="commsCompose" rows="1" placeholder="{{ $act['type'] === 'announcement' ? $lab['announce'] : $lab['compose'] }}"
                        x-data x-init="$el.style.height='auto'; $el.style.height=Math.min($el.scrollHeight,120)+'px'"
                        x-on:input="$el.style.height='auto'; $el.style.height=Math.min($el.scrollHeight,120)+'px'"
                        x-on:keydown.enter.prevent="if(!$event.shiftKey){ $wire.sendMessage() }"
                        style="flex: 1; resize: none; padding: 11px 14px; border: 1.5px solid #E4E2DB; border-radius: 12px; font-size: 13.5px; font-family: inherit; outline: none; background: #FAFAF8; max-height: 120px;"></textarea>
                    <button type="submit" style="display: inline-flex; align-items: center; gap: 6px; padding: 11px 16px; border: none; border-radius: 12px; background: #E85D2A; color: #fff; font-size: 13px; font-weight: 600; cursor: pointer; flex-shrink: 0;">
                        {{ $lab['send'] }}<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                    </button>
                </form>
            @else
                <div style="padding: 15px 16px; border-top: 1px solid #F0EEE8; text-align: center; font-size: 12.5px; color: #A7A49B; background: #FBFAF7; display: flex; align-items: center; justify-content: center; gap: 7px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="11" width="16" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>{{ $act['readOnlyNote'] }}
                </div>
            @endif
            @endif
        @else
            <div style="flex: 1; display: flex; align-items: center; justify-content: center; color: #B7B4AB; font-size: 13.5px;">{{ $lab['empty'] }}</div>
        @endif
    </div>
</div>

{{-- ===== voice daily-report composer (dictate → AI report → post) ===== --}}
@if($C['reportOpen'] ?? false)
    <div style="position: fixed; inset: 0; z-index: 80; background: rgba(22,24,29,0.55); display: flex; align-items: center; justify-content: center; padding: 16px;">
        <div x-data="{
                raw: '',
                listening: false,
                wantListen: false,   {{-- true from tap-start until tap-stop: silence never ends the session --}}
                rec: null,
                micLang: '{{ $C['speechLang'] }}',
                supported: !!(window.SpeechRecognition || window.webkitSpeechRecognition),
                setLang(l) {
                    this.micLang = l;
                    // switching language mid-dictation: stop the current recognizer;
                    // wantListen stays true so onend restarts it in the new language
                    if (this.listening) { try { this.rec?.stop(); } catch (e) {} }
                },
                stopMic() { this.wantListen = false; this.listening = false; try { this.rec?.stop(); } catch (e) {} },
                toggle() {
                    if (this.listening) { this.stopMic(); return; }
                    this.wantListen = true;
                    this.startRec();
                },
                startRec() {
                    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
                    if (!SR) { this.wantListen = false; return; }
                    this.rec = new SR();
                    this.rec.lang = this.micLang;
                    this.rec.continuous = true;
                    this.rec.interimResults = false;
                    this.rec.onresult = (e) => {
                        for (let i = e.resultIndex; i < e.results.length; i++) {
                            if (e.results[i].isFinal) {
                                const t = e.results[i][0].transcript.trim();
                                if (t) this.raw = (this.raw ? this.raw + ' ' : '') + t;
                            }
                        }
                    };
                    this.rec.onerror = (e) => {
                        // mic permission problems end the session for real; silence does not
                        if (e.error === 'not-allowed' || e.error === 'service-not-allowed' || e.error === 'audio-capture') {
                            this.wantListen = false;
                        }
                    };
                    this.rec.onend = () => {
                        // the browser auto-stops after a pause — restart (fresh recognizer, current
                        // language) until the reporter taps stop, so thinking time mid-report
                        // never kills the dictation
                        if (this.wantListen) {
                            setTimeout(() => { if (this.wantListen) this.startRec(); }, 150);
                        } else {
                            this.listening = false;
                        }
                    };
                    try { this.rec.start(); this.listening = true; }
                    catch (e) { this.listening = false; this.wantListen = false; }
                }
            }"
            style="width: 500px; max-width: 100%; max-height: 92vh; overflow-y: auto; background: #fff; border-radius: 18px; padding: 24px;">

            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                <div style="font-size: 17px; font-weight: 700;">{{ $lab['reportTitle'] }}</div>
                <button wire:click="closeReport" x-on:click="stopMic()" style="border: none; background: transparent; font-size: 22px; line-height: 1; color: #8A8880; cursor: pointer;">×</button>
            </div>
            <div style="font-size: 12.5px; color: #8A8880; margin-bottom: 16px;">{{ $lab['reportHint'] }}</div>

            @if($C['reportDraft'] === '')
                {{-- step 1: pick the speaking language, then dictate / type --}}
                <div x-show="supported" style="display: flex; gap: 6px; margin-bottom: 10px;">
                    <span style="font-size: 12px; color: #8A8880; align-self: center; margin-right: 2px;">🎙</span>
                    <template x-for="opt in [['ko-KR','한국어'],['es-MX','Español'],['en-US','English']]" :key="opt[0]">
                        <button type="button" x-on:click="setLang(opt[0])" x-text="opt[1]"
                            x-bind:style="micLang === opt[0]
                                ? 'flex:1;padding:8px;border:1.5px solid #16181D;border-radius:9px;background:#16181D;color:#fff;font-size:12.5px;font-weight:700;cursor:pointer;'
                                : 'flex:1;padding:8px;border:1.5px solid #E4E2DB;border-radius:9px;background:#fff;color:#5A5D64;font-size:12.5px;font-weight:600;cursor:pointer;'"></button>
                    </template>
                </div>
                <button type="button" x-on:click="toggle" x-show="supported"
                    x-bind:style="listening
                        ? 'width:100%;padding:14px;border:none;border-radius:13px;background:#D9483B;color:#fff;font-size:14.5px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px;'
                        : 'width:100%;padding:14px;border:none;border-radius:13px;background:#16181D;color:#fff;font-size:14.5px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px;'">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10a7 7 0 0 0 14 0M12 17v4M8 21h8"/></svg>
                    <span x-show="!listening">{{ $lab['micStart'] }}</span>
                    <span x-show="listening" style="display: inline-flex; align-items: center; gap: 8px;"><span style="width: 9px; height: 9px; border-radius: 50%; background: #fff; animation: wfPulse 1s infinite;"></span>{{ $lab['micStop'] }} · {{ $lab['micListening'] }}</span>
                </button>
                <div x-show="!supported" style="padding: 11px 13px; border-radius: 11px; background: #FBF1DF; color: #8A6A2E; font-size: 12.5px; line-height: 1.5;">{{ $lab['micUnsupported'] }}</div>

                <textarea x-model="raw" rows="6" placeholder="{{ $lab['reportRawPh'] }}"
                    style="width: 100%; margin-top: 12px; padding: 12px 14px; border: 1.5px solid #E4E2DB; border-radius: 12px; font-size: 13.5px; font-family: inherit; line-height: 1.6; outline: none; background: #FAFAF8; resize: vertical;"></textarea>

                <div style="display: flex; gap: 10px; margin-top: 14px;">
                    <button wire:click="closeReport" x-on:click="stopMic()" style="flex: 1; padding: 12px; border: 1px solid #E4E2DB; border-radius: 12px; background: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $lab['cancel'] }}</button>
                    <button x-on:click="stopMic(); $wire.generateReport(raw)" wire:loading.attr="disabled" wire:target="generateReport"
                        style="flex: 1.5; padding: 12px; border: none; border-radius: 12px; background: #E85D2A; color: #fff; font-size: 14px; font-weight: 700; cursor: pointer;">
                        <span wire:loading.remove wire:target="generateReport">✨ {{ $lab['reportGen'] }}</span>
                        <span wire:loading wire:target="generateReport">{{ $lab['reportGenBusy'] }}</span>
                    </button>
                </div>
            @else
                {{-- step 2: review the AI draft, edit freely, post --}}
                <div style="font-size: 12px; font-weight: 700; color: #1F9D6B; margin-bottom: 7px;">{{ $lab['reportDraftLabel'] }}</div>
                <textarea wire:model="reportDraft" rows="14"
                    style="width: 100%; padding: 13px 15px; border: 1.5px solid #CBE7DB; border-radius: 12px; font-size: 13.5px; font-family: inherit; line-height: 1.65; outline: none; background: #F6FBF8; resize: vertical;"></textarea>
                <div style="display: flex; gap: 10px; margin-top: 14px;">
                    <button wire:click="$set('reportDraft', '')" style="flex: 1; padding: 12px; border: 1px solid #E4E2DB; border-radius: 12px; background: #fff; color: #5A5D64; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $lab['reportRedo'] }}</button>
                    <button wire:click="postReport" style="flex: 1.5; padding: 12px; border: none; border-radius: 12px; background: #1F9D6B; color: #fff; font-size: 14px; font-weight: 700; cursor: pointer;">{{ $lab['reportPost'] }}</button>
                </div>
            @endif
        </div>
    </div>
    <style>@keyframes wfPulse { 0%,100% { opacity: 1; } 50% { opacity: 0.25; } }</style>
@endif
