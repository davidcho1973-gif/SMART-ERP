@php
    $E = $A['equipment'];
    $el = $E['labels'];
    $eth = 'font-size: 10.5px; letter-spacing: .05em; text-transform: uppercase; color: #A7A49B; font-weight: 700; padding: 14px 12px 11px;';
    $ebd = 'border-top: 1px solid #F0EEE8;';
@endphp

{{-- ============ EQUIPMENT REGISTRY (M3 · STEP 2) ============ --}}

{{-- KPI strip --}}
<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 16px;">
    <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 14px; padding: 15px 17px;">
        <div style="font-size: 10.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #A7A49B;">{{ $el['kpi_total'] }}</div>
        <div style="font-family: 'Space Grotesk'; font-size: 25px; font-weight: 700; margin-top: 6px; color: #16181D;">{{ $E['counts']['total'] }}</div>
    </div>
    <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 14px; padding: 15px 17px;">
        <div style="font-size: 10.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #A7A49B;">{{ $el['kpi_out'] }}</div>
        <div style="font-family: 'Space Grotesk'; font-size: 25px; font-weight: 700; margin-top: 6px; color: #3B72E0;">{{ $E['counts']['out'] }}</div>
    </div>
    <div style="background: #fff; border: 1px solid {{ $E['dueSoon'] > 0 ? '#F1C9A6' : '#E4E2DB' }}; border-radius: 14px; padding: 15px 17px;">
        <div style="font-size: 10.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #A7A49B;">{{ $el['kpi_due'] }}</div>
        <div style="font-family: 'Space Grotesk'; font-size: 25px; font-weight: 700; margin-top: 6px; color: {{ $E['dueSoon'] > 0 ? '#C98A1E' : '#C4C1B8' }};">{{ $E['dueSoon'] }}</div>
    </div>
</div>

{{-- ---------- 유휴 장비 반납 절감 AI ---------- --}}
@if($E['idle']['count'] > 0)
    <div style="background: linear-gradient(135deg, #0E1A19, #16302E); border-radius: 16px; padding: 16px 18px; margin-bottom: 16px; color: #fff;">
        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <span style="display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 9px; background: rgba(14,165,160,.22); color: #5EE6D8;"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/><circle cx="12" cy="12" r="4"/></svg></span>
            <div style="font-family: 'Space Grotesk'; font-weight: 700; font-size: 14.5px;">{{ $el['idle_title'] }}</div>
            <span style="font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; padding: 2px 8px; border-radius: 20px; background: rgba(94,230,216,.16); color: #5EE6D8;">AI</span>
            <div style="flex: 1;"></div>
            <div style="text-align: right;">
                <div style="font-size: 10.5px; color: rgba(255,255,255,.55);">{{ $el['idle_save'] }}</div>
                <div style="font-family: 'Space Grotesk'; font-size: 20px; font-weight: 700; color: #5EE6D8; line-height: 1;">{{ $E['idle']['savingLabel'] }}</div>
            </div>
        </div>
        <div style="font-size: 12px; color: rgba(255,255,255,.62); margin: 9px 0 12px; line-height: 1.5;">{{ $el['idle_sub'] }} <strong style="color:#5EE6D8;">{{ $E['idle']['savingLabel'] }}</strong>.</div>
        <div style="display: flex; flex-direction: column; gap: 7px;">
            @foreach($E['idle']['units'] as $u)
                <div wire:key="idle-{{ $u['id'] }}" style="display: flex; align-items: center; gap: 11px; background: rgba(255,255,255,.05); border-radius: 10px; padding: 9px 12px; flex-wrap: wrap;">
                    <div style="min-width: 0; flex: 1;">
                        <div style="font-weight: 600; font-size: 13px;">{{ $u['name'] }}</div>
                        <div style="font-size: 11px; color: rgba(255,255,255,.5); margin-top: 1px;">{{ $u['site'] }} · {{ $u['reason'] }} · {{ $u['window'] }}</div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 11px; color: rgba(255,255,255,.5);">{{ $u['dailyLabel'] }}</div>
                        <div style="font-weight: 700; font-size: 13px; color: #5EE6D8;">−{{ $u['saveLabel'] }}</div>
                    </div>
                    @if($E['canCheckout'])
                        <button wire:click="equipStatus({{ $u['id'] }}, 'returned')" style="padding: 7px 13px; border: none; border-radius: 8px; background: #5EE6D8; color: #0E1A19; font-size: 12px; font-weight: 700; cursor: pointer; white-space: nowrap;">{{ $el['idle_return'] }}</button>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif

{{-- toolbar --}}
<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
    <div style="display: flex; gap: 3px; background: #fff; border: 1px solid #E4E2DB; border-radius: 11px; padding: 3px; flex-wrap: wrap;">
        @foreach(['all' => $el['filter_all'], 'available' => $el['filter_available'], 'out' => $el['filter_out'], 'maintenance' => $el['filter_maintenance'], 'owned' => $el['filter_owned'], 'rented' => $el['filter_rented']] as $fk => $fl)
            @php $fon = $E['filter'] === $fk; @endphp
            <button wire:click="$set('equipFilter', '{{ $fk }}')" style="padding: 7px 12px; border: none; border-radius: 8px; font-size: 12.5px; font-weight: {{ $fon ? '700' : '600' }}; cursor: pointer; background: {{ $fon ? '#16181D' : 'transparent' }}; color: {{ $fon ? '#fff' : '#5A5D64' }};">{{ $fl }}</button>
        @endforeach
    </div>
    <div style="display: flex; align-items: center; gap: 8px; background: #fff; border: 1px solid #E4E2DB; border-radius: 10px; padding: 8px 12px; flex: 1; min-width: 140px; max-width: 230px;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#A7A49B" stroke-width="2" style="flex-shrink: 0;"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>
        <input type="text" wire:model.live.debounce.300ms="equipSearch" placeholder="{{ $el['searchPh'] }}" style="border: none; outline: none; background: transparent; font-size: 13px; width: 100%; color: #16181D;">
    </div>
    <div style="flex: 1;"></div>
    @if($E['canManage'])
        <button wire:click="openEquipRegister" style="display: inline-flex; align-items: center; gap: 7px; padding: 9px 15px; border: none; border-radius: 10px; background: #E85D2A; color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; box-shadow: 0 2px 8px rgba(232,93,42,0.28);">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>{{ $el['add'] }}
        </button>
    @endif
</div>

{{-- registry table --}}
<div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; overflow: hidden; margin-top: 14px;">
    @if(count($E['rows']))
        <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; font-size: 13.5px; font-variant-numeric: tabular-nums; min-width: 920px;">
            <thead>
                <tr style="text-align: left;">
                    <th style="{{ $eth }} width: 36px; padding-left: 16px;"></th>
                    <th style="{{ $eth }}">{{ $el['col_equip'] }}</th>
                    <th style="{{ $eth }}">{{ $el['col_acq'] }}</th>
                    <th style="{{ $eth }}">{{ $el['col_status'] }}</th>
                    <th style="{{ $eth }}">{{ $el['col_site'] }}</th>
                    <th style="{{ $eth }} text-align: right; padding-right: 16px;">{{ $el['col_cost'] }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($E['rows'] as $r)
                    @php $open = $E['expandId'] === $r['id']; @endphp
                    <tr wire:key="eq-{{ $r['id'] }}" onmouseover="this.style.background='#FAF9F5'" onmouseout="this.style.background='transparent'">
                        <td style="{{ $ebd }} padding: 10px 0 10px 16px;">
                            <button wire:click="toggleEquipExpand({{ $r['id'] }})" style="width: 26px; height: 26px; border: 1px solid #E4E2DB; border-radius: 7px; background: #fff; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; color: #8A8880;">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" style="transform: rotate({{ $open ? '90' : '0' }}deg); transition: transform .15s;"><path d="M9 18l6-6-6-6"/></svg>
                            </button>
                        </td>
                        <td style="{{ $ebd }} padding: 9px 12px;">
                            <div style="display: flex; align-items: center; gap: 11px;">
                                @if($r['cover'])
                                    <img src="{{ $r['cover'] }}" alt="" loading="lazy" style="width: 40px; height: 40px; border-radius: 9px; object-fit: cover; border: 1px solid #ECEAE3; flex-shrink: 0;">
                                @else
                                    <span style="width: 40px; height: 40px; border-radius: 9px; background: #F4F2EC; color: #B7B4AB; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 17h13V6H3zM16 9h4l3 3v5h-7z"/><circle cx="6.5" cy="17.5" r="1.5"/><circle cx="18.5" cy="17.5" r="1.5"/></svg></span>
                                @endif
                                <div style="min-width: 0;">
                                    <div style="font-weight: 700; color: #16181D; white-space: nowrap;">{{ $r['name'] }}@if($r['photoCount'] > 0)<span style="font-size: 10.5px; color: #A7A49B; font-weight: 600; margin-left: 6px;">📷 {{ $r['photoCount'] }}</span>@endif</div>
                                    <div style="font-size: 11.5px; color: #A7A49B; margin-top: 1px;">{{ $r['type'] }}@if($r['serial']) · {{ $r['serial'] }}@endif</div>
                                </div>
                            </div>
                        </td>
                        <td style="{{ $ebd }} padding: 9px 12px;"><span style="display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 600; color: {{ $r['acqColor'] }};"><span style="width: 8px; height: 8px; border-radius: 2px; background: {{ $r['acqColor'] }};"></span>{{ $r['acqName'] }}</span></td>
                        <td style="{{ $ebd }} padding: 9px 12px;"><span style="font-size: 10.5px; font-weight: 700; padding: 3px 9px; border-radius: 20px; background: {{ $r['statusBg'] }}; color: {{ $r['statusColor'] }};">{{ $r['statusName'] }}</span></td>
                        <td style="{{ $ebd }} padding: 9px 12px; color: #5A5D64;">
                            <div>{{ $r['site'] }}</div>
                            @if($r['isOut'] && $r['holder'] !== '—')<div style="font-size: 11.5px; color: #A7A49B;">{{ $r['holder'] }}</div>@endif
                        </td>
                        <td style="{{ $ebd }} padding: 9px 16px 9px 12px; text-align: right;">
                            <div style="font-weight: 700; color: #16181D; white-space: nowrap;">{{ $r['costLabel'] }}</div>
                            @if($r['due'])<div style="font-size: 11px; font-weight: 700; color: {{ $r['due']['color'] }}; white-space: nowrap; margin-top: 2px;">{{ $r['due']['text'] }}</div>
                            @elseif($r['costSub'])<div style="font-size: 11px; color: #A7A49B; white-space: nowrap; margin-top: 2px;">{{ $r['costSub'] }}</div>@endif
                        </td>
                    </tr>

                    {{-- expanded detail: photos · actions · history --}}
                    @if($open)
                        <tr wire:key="eqexp-{{ $r['id'] }}"><td colspan="6" style="{{ $ebd }} padding: 0; background: #FAFAF8;">
                            <div style="padding: 16px; display: grid; grid-template-columns: 1.35fr 1fr; gap: 18px; align-items: start;">

                                {{-- left: photo gallery --}}
                                <div>
                                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 9px;">
                                        <div style="font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B;">{{ $el['photos'] }}</div>
                                        @if($E['canManage'])
                                            <span style="font-size: 11px; color: #A7A49B;">{{ $el['photoKind'] }}</span>
                                        @endif
                                    </div>
                                    @if(count($r['photos']))
                                        <div x-data="{ z: null }" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(88px, 1fr)); gap: 8px;">
                                            @foreach($r['photos'] as $p)
                                                <div wire:key="eqph-{{ $p['id'] }}" style="position: relative;">
                                                    @if($p['isImage'])
                                                        <img @click="z = '{{ $p['url'] }}'" src="{{ $p['url'] }}" alt="{{ $p['kindName'] }}" loading="lazy" style="width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 9px; border: 1px solid #ECEAE3; cursor: zoom-in;">
                                                    @else
                                                        <a href="{{ $p['url'] }}" target="_blank" style="display: flex; align-items: center; justify-content: center; width: 100%; aspect-ratio: 1; border-radius: 9px; border: 1px solid #ECEAE3; background: #fff; color: #8A8880;"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg></a>
                                                    @endif
                                                    <span style="position: absolute; left: 4px; bottom: 4px; font-size: 9.5px; font-weight: 700; padding: 1px 6px; border-radius: 20px; background: rgba(22,24,29,.72); color: #fff;">{{ $p['kindName'] }}</span>
                                                    @if($E['canManage'])
                                                        <button wire:click="removeEquipPhoto({{ $p['id'] }})" wire:confirm="{{ $el['removePhoto'] }}?" style="position: absolute; top: 4px; right: 4px; width: 19px; height: 19px; border: none; border-radius: 50%; background: rgba(22,24,29,.72); color: #fff; font-size: 12px; line-height: 1; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;">×</button>
                                                    @endif
                                                </div>
                                            @endforeach
                                            <template x-teleport="body"><div x-show="z" x-cloak @click="z=null" @keydown.escape.window="z=null" x-transition.opacity style="position: fixed; inset: 0; z-index: 100; background: rgba(0,0,0,.85); display: flex; align-items: center; justify-content: center; padding: 24px; cursor: zoom-out;"><img :src="z" style="max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 8px;"></div></template>
                                        </div>
                                    @else
                                        <div style="font-size: 12px; color: #B7B4AB; padding: 14px 0;">{{ $el['noPhotos'] }}</div>
                                    @endif

                                    {{-- add-photo (manager only, on the expanded row) --}}
                                    @if($E['canManage'])
                                        <div style="display: flex; gap: 8px; align-items: center; margin-top: 11px; flex-wrap: wrap;">
                                            <select wire:model="eqPhotoKind" style="padding: 8px 10px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 12.5px; outline: none; background: #fff;">
                                                @foreach($E['photoKinds'] as $pk)<option value="{{ $pk['key'] }}">{{ $pk['name'] }}</option>@endforeach
                                            </select>
                                            <label style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 13px; border: 1px dashed #D6D3CB; border-radius: 9px; background: #fff; color: #16181D; font-size: 12.5px; font-weight: 600; cursor: pointer;">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#E85D2A" stroke-width="1.9"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>{{ $el['addPhoto'] }}
                                                <input type="file" wire:model="eqPhotoFile" accept="image/*" style="display: none;">
                                            </label>
                                            <div wire:loading.flex wire:target="eqPhotoFile,addEquipPhoto" style="align-items: center; gap: 6px; font-size: 12px; color: #8A8880;"><span style="width: 12px; height: 12px; border: 2px solid #E4E2DB; border-top-color: #E85D2A; border-radius: 50%; display: inline-block; animation: wfSpin 0.7s linear infinite;"></span></div>
                                        </div>
                                        @if($eqPhotoFile)
                                            <button wire:click="addEquipPhoto" wire:loading.attr="disabled" wire:target="addEquipPhoto" style="margin-top: 8px; padding: 8px 16px; border: none; border-radius: 9px; background: #16181D; color: #fff; font-size: 12.5px; font-weight: 700; cursor: pointer;">{{ $el['addPhoto'] }} →</button>
                                        @endif
                                    @endif
                                </div>

                                {{-- right: facts · actions · history --}}
                                <div>
                                    {{-- fact grid --}}
                                    <div style="background: #fff; border: 1px solid #EFEDE7; border-radius: 11px; padding: 12px 14px; font-size: 12.5px;">
                                        @if($r['assetTag'])<div style="display: flex; justify-content: space-between; padding: 4px 0;"><span style="color: #A7A49B;">{{ $el['assetTag'] }}</span><span style="font-weight: 600;">{{ $r['assetTag'] }}</span></div>@endif
                                        @if($r['meter'])<div style="display: flex; justify-content: space-between; padding: 4px 0;"><span style="color: #A7A49B;">{{ $el['meter'] }}</span><span style="font-weight: 600;">{{ $r['meter'] }}</span></div>@endif
                                        @if($r['vendor'])<div style="display: flex; justify-content: space-between; padding: 4px 0;"><span style="color: #A7A49B;">{{ $el['vendor'] }}</span><span style="font-weight: 600;">{{ $r['vendor'] }}</span></div>@endif
                                        @if($r['note'])<div style="padding: 6px 0 2px; color: #5A5D64; line-height: 1.5;">{{ $r['note'] }}</div>@endif
                                    </div>

                                    {{-- status actions --}}
                                    @if($E['canCheckout'])
                                        @if($E['coTarget'] === $r['id'])
                                            <div style="background: #fff; border: 1.5px solid #C7D8FB; border-radius: 11px; padding: 12px 14px; margin-top: 10px;">
                                                <div style="font-size: 11px; font-weight: 700; color: #3B72E0; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 9px;">{{ $el['checkout'] }}</div>
                                                <label style="display: block; margin-bottom: 8px;"><span style="display: block; font-size: 11px; color: #A7A49B; margin-bottom: 4px;">{{ $el['site'] }}</span>
                                                    <select wire:model="coSite" style="width: 100%; padding: 8px 10px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #FAFAF8;">@foreach($E['siteOptions'] as $o)<option value="{{ $o['id'] }}">{{ $o['label'] }}</option>@endforeach</select></label>
                                                <label style="display: block; margin-bottom: 10px;"><span style="display: block; font-size: 11px; color: #A7A49B; margin-bottom: 4px;">{{ $el['holder'] }}</span>
                                                    <select wire:model="coHolder" style="width: 100%; padding: 8px 10px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #FAFAF8;"><option value="">—</option>@foreach($E['holderOptions'] as $o)<option value="{{ $o['id'] }}">{{ $o['label'] }}</option>@endforeach</select></label>
                                                <div style="display: flex; gap: 8px;">
                                                    <button wire:click="cancelCheckout" style="flex: 1; padding: 9px; border: 1px solid #E4E2DB; border-radius: 9px; background: #fff; color: #8A8880; font-size: 12.5px; font-weight: 600; cursor: pointer;">{{ $el['cancel'] }}</button>
                                                    <button wire:click="doCheckout({{ $r['id'] }})" style="flex: 1.5; padding: 9px; border: none; border-radius: 9px; background: #3B72E0; color: #fff; font-size: 12.5px; font-weight: 700; cursor: pointer;">{{ $el['confirm'] }}</button>
                                                </div>
                                            </div>
                                        @else
                                            <div style="display: flex; gap: 7px; flex-wrap: wrap; margin-top: 10px;">
                                                @if($r['status'] !== 'out')
                                                    <button wire:click="askCheckout({{ $r['id'] }})" style="padding: 8px 13px; border: none; border-radius: 9px; background: #3B72E0; color: #fff; font-size: 12.5px; font-weight: 700; cursor: pointer;">{{ $el['checkout'] }}</button>
                                                @else
                                                    <button wire:click="equipStatus({{ $r['id'] }}, 'available')" style="padding: 8px 13px; border: none; border-radius: 9px; background: #1F9D6B; color: #fff; font-size: 12.5px; font-weight: 700; cursor: pointer;">{{ $el['checkin'] }}</button>
                                                @endif
                                                <button wire:click="equipStatus({{ $r['id'] }}, 'maintenance')" style="padding: 8px 13px; border: 1px solid #E4E2DB; border-radius: 9px; background: #fff; color: #C98A1E; font-size: 12.5px; font-weight: 600; cursor: pointer;">{{ $el['maintenance'] }}</button>
                                                @if($r['acq'] === 'rented')
                                                    <button wire:click="equipStatus({{ $r['id'] }}, 'returned')" style="padding: 8px 13px; border: 1px solid #E4E2DB; border-radius: 9px; background: #fff; color: #5A5D64; font-size: 12.5px; font-weight: 600; cursor: pointer;">{{ $el['return'] }}</button>
                                                @endif
                                            </div>
                                        @endif
                                    @endif

                                    {{-- manager tools --}}
                                    @if($E['canManage'])
                                        <div style="display: flex; gap: 7px; margin-top: 9px;">
                                            <button wire:click="openEquipEdit({{ $r['id'] }})" style="padding: 7px 12px; border: 1px solid #E4E2DB; border-radius: 9px; background: #fff; color: #16181D; font-size: 12px; font-weight: 600; cursor: pointer;">{{ $el['edit'] }}</button>
                                            <button wire:click="openEquipQr({{ $r['id'] }})" style="display: inline-flex; align-items: center; gap: 5px; padding: 7px 12px; border: 1px solid #E4E2DB; border-radius: 9px; background: #fff; color: #16181D; font-size: 12px; font-weight: 600; cursor: pointer;"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3M21 14v7M14 21h7"/></svg>{{ $el['qr'] }}</button>
                                        </div>
                                    @endif

                                    {{-- history --}}
                                    <div style="margin-top: 13px;">
                                        <div style="font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 7px;">{{ $el['history'] }}</div>
                                        @if(count($r['events']))
                                            <div style="display: flex; flex-direction: column; gap: 6px;">
                                                @foreach($r['events'] as $ev)
                                                    <div style="display: flex; gap: 9px; font-size: 12px;">
                                                        <span style="width: 7px; height: 7px; border-radius: 50%; background: #C7C3B8; margin-top: 5px; flex-shrink: 0;"></span>
                                                        <div>
                                                            <span style="font-weight: 600; color: #16181D;">{{ $ev['typeName'] }}</span>
                                                            <span style="color: #A7A49B;"> · {{ $ev['at'] }}</span>
                                                            @if($ev['who'] !== '—')<span style="color: #A7A49B;"> · {{ $ev['who'] }}</span>@endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <div style="font-size: 12px; color: #B7B4AB;">{{ $el['noHistory'] }}</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td></tr>
                    @endif
                @endforeach
            </tbody>
        </table>
        </div>
    @else
        <div style="padding: 54px 16px; text-align: center; color: #B7B4AB; font-size: 13px;">{{ $E['search'] !== '' ? $el['searchPh'] : $el['empty'] }}</div>
    @endif
</div>

{{-- ---------- register / edit modal ---------- --}}
@if($E['formOpen'])
    <div style="position: fixed; inset: 0; z-index: 80; background: rgba(22,24,29,0.55); display: flex; align-items: center; justify-content: center; padding: 16px;">
        <div style="width: 640px; max-width: 100%; max-height: 92vh; overflow-y: auto; background: #fff; border-radius: 18px; padding: 22px;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div style="font-family: 'Space Grotesk'; font-size: 17px; font-weight: 700;">{{ $E['editing'] ? $el['editEquip'] : $el['newEquip'] }}</div>
                <button wire:click="closeEquip" style="border: none; background: transparent; font-size: 22px; line-height: 1; color: #8A8880; cursor: pointer;">×</button>
            </div>

            {{-- nameplate photo + OCR --}}
            <div style="margin-top: 14px;">
                <div style="font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 6px;">{{ $el['plate'] }}</div>
                @if($equipFile)
                    <div style="border: 1px solid #E4E2DB; border-radius: 12px; overflow: hidden; background: #FAFAF8;">
                        @if(str_starts_with((string) $equipFile->getMimeType(), 'image/'))
                            <img src="{{ $equipFile->temporaryUrl() }}" alt="plate" style="display: block; width: 100%; max-height: 220px; object-fit: contain; background: #16181D;">
                        @else
                            <div style="padding: 18px; text-align: center; font-size: 13px; color: #5A5D64;">{{ $equipFile->getClientOriginalName() }}</div>
                        @endif
                        <div style="display: flex; gap: 8px; padding: 10px;">
                            @if($E['ocrOn'] && str_starts_with((string) $equipFile->getMimeType(), 'image/'))
                                <button type="button" wire:click="readPlate" wire:loading.attr="disabled" wire:target="readPlate" style="flex: 1.4; padding: 9px; border: none; border-radius: 9px; background: #16181D; color: #fff; font-size: 12.5px; font-weight: 700; cursor: pointer;">
                                    <span wire:loading.remove wire:target="readPlate">✨ {{ $el['readPlate'] }}</span><span wire:loading wire:target="readPlate">{{ $el['reading'] }}</span>
                                </button>
                            @endif
                            <button type="button" wire:click="clearEquipFile" style="flex: 1; padding: 9px; border: 1px solid #E4E2DB; border-radius: 9px; background: #fff; color: #8A8880; font-size: 12.5px; font-weight: 600; cursor: pointer;">{{ $el['removePhoto'] }}</button>
                        </div>
                    </div>
                @else
                    <label style="display: flex; flex-direction: column; align-items: center; gap: 5px; padding: 18px; border: 1.5px dashed #D6D3CB; border-radius: 12px; background: #FAFAF8; cursor: pointer; text-align: center;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#E85D2A" stroke-width="1.8"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                        <span style="font-size: 12.5px; font-weight: 600; color: #16181D;">{{ $el['plate'] }}</span>
                        <span style="font-size: 11px; color: #A7A49B;">{{ $el['plateHint'] }}</span>
                        <input type="file" wire:model="equipFile" accept="image/*,.pdf" style="display: none;">
                    </label>
                    <div wire:loading.flex wire:target="equipFile" style="align-items: center; gap: 7px; margin-top: 7px; font-size: 12px; color: #8A8880;"><span style="width: 12px; height: 12px; border: 2px solid #E4E2DB; border-top-color: #E85D2A; border-radius: 50%; display: inline-block; animation: wfSpin 0.7s linear infinite;"></span>{{ $el['reading'] }}</div>
                @endif
            </div>

            {{-- acquisition toggle --}}
            <div style="margin-top: 15px;">
                <div style="font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 6px;">{{ $el['acquisition'] }}</div>
                <div style="display: flex; gap: 4px; background: #F4F2EC; border-radius: 11px; padding: 4px; width: fit-content;">
                    @foreach(['owned' => $el['owned'], 'rented' => $el['rented']] as $ak => $alab)
                        @php $aon = $E['acq'] === $ak; @endphp
                        <button type="button" wire:click="$set('eqAcq', '{{ $ak }}')" style="padding: 8px 18px; border: none; border-radius: 8px; font-size: 13px; font-weight: {{ $aon ? '700' : '600' }}; cursor: pointer; background: {{ $aon ? '#16181D' : 'transparent' }}; color: {{ $aon ? '#fff' : '#5A5D64' }};">{{ $alab }}</button>
                    @endforeach
                </div>
            </div>

            {{-- common fields --}}
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 14px;">
                <label style="grid-column: 1 / -1;"><span style="display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 5px;">{{ $el['name'] }}</span><input type="text" wire:model="eqName" style="width: 100%; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #FAFAF8;"></label>
                <label><span style="display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 5px;">{{ $el['typeField'] }}</span><input type="text" wire:model="eqType" style="width: 100%; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #FAFAF8;"></label>
                <label><span style="display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 5px;">{{ $el['site'] }}</span><select wire:model="eqSite" style="width: 100%; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #FAFAF8;">@foreach($E['siteOptions'] as $o)<option value="{{ $o['id'] }}">{{ $o['label'] }}</option>@endforeach</select></label>
                <label><span style="display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 5px;">{{ $el['serial'] }}</span><input type="text" wire:model="eqSerial" style="width: 100%; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #FAFAF8;"></label>
                <label><span style="display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 5px;">{{ $el['assetTag'] }}</span><input type="text" wire:model="eqTag" style="width: 100%; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #FAFAF8;"></label>
                <label><span style="display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 5px;">{{ $el['meter'] }}</span>
                    <div style="display: flex; gap: 6px;">
                        <input type="number" step="0.1" min="0" wire:model="eqMeter" style="flex: 1; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #FAFAF8; text-align: right;">
                        <select wire:model="eqMeterUnit" style="width: 84px; padding: 9px 8px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 12.5px; outline: none; background: #FAFAF8;"><option value="hours">{{ $el['hours'] }}</option><option value="km">{{ $el['km'] }}</option></select>
                    </div>
                </label>
            </div>

            {{-- owned-only fields --}}
            @if($E['acq'] === 'owned')
                <div style="background: #F6F4FB; border: 1px solid #E6E0F5; border-radius: 12px; padding: 13px 14px; margin-top: 13px;">
                    <div style="font-size: 11px; color: #6B4EE6; line-height: 1.5; margin-bottom: 11px;">{{ $el['ownedHelp'] }}</div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <label><span style="display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 5px;">{{ $el['purchaseDate'] }}</span><input type="date" wire:model="eqPurchaseDate" style="width: 100%; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #fff;"></label>
                        <label><span style="display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 5px;">{{ $el['purchaseCost'] }}</span><input type="number" step="0.01" min="0" wire:model="eqPurchaseCost" style="width: 100%; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #fff; text-align: right;"></label>
                        <label><span style="display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 5px;">{{ $el['life'] }}</span><input type="number" min="1" wire:model="eqLife" style="width: 100%; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #fff; text-align: right;"></label>
                        <label><span style="display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 5px;">{{ $el['salvage'] }}</span><input type="number" step="0.01" min="0" wire:model="eqSalvage" style="width: 100%; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #fff; text-align: right;"></label>
                    </div>
                </div>
            @else
                <div style="background: #EAF7F6; border: 1px solid #CFEBE8; border-radius: 12px; padding: 13px 14px; margin-top: 13px;">
                    <div style="font-size: 11px; color: #0EA5A0; line-height: 1.5; margin-bottom: 11px;">{{ $el['rentedHelp'] }}</div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <label style="grid-column: 1 / -1;"><span style="display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 5px;">{{ $el['vendor'] }}</span><input type="text" wire:model="eqVendor" style="width: 100%; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #fff;"></label>
                        <label><span style="display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 5px;">{{ $el['rate'] }}</span>
                            <div style="display: flex; gap: 6px;">
                                <input type="number" step="0.01" min="0" wire:model="eqRate" style="flex: 1; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #fff; text-align: right;">
                                <select wire:model="eqRateUnit" style="width: 76px; padding: 9px 7px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 12px; outline: none; background: #fff;"><option value="day">{{ $el['perDay'] }}</option><option value="week">{{ $el['perWeek'] }}</option><option value="month">{{ $el['perMonth'] }}</option></select>
                            </div>
                        </label>
                        <label><span style="display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 5px;">{{ $el['deposit'] }}</span><input type="number" step="0.01" min="0" wire:model="eqDeposit" style="width: 100%; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #fff; text-align: right;"></label>
                        <label><span style="display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 5px;">{{ $el['start'] }}</span><input type="date" wire:model="eqStart" style="width: 100%; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #fff;"></label>
                        <label><span style="display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 5px;">{{ $el['end'] }}</span><input type="date" wire:model="eqEnd" style="width: 100%; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #fff;"></label>
                    </div>
                </div>
            @endif

            <label style="display: block; margin-top: 13px;"><span style="display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 5px;">{{ $el['note'] }}</span><input type="text" wire:model="eqNote" style="width: 100%; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #FAFAF8;"></label>

            <div style="display: flex; gap: 10px; margin-top: 18px;">
                <button wire:click="closeEquip" style="flex: 1; padding: 12px; border: 1px solid #E4E2DB; border-radius: 11px; background: #fff; color: #5A5D64; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $el['cancel'] }}</button>
                <button wire:click="submitEquip" wire:loading.attr="disabled" wire:target="submitEquip,equipFile" style="flex: 1.6; padding: 12px; border: none; border-radius: 11px; background: #E85D2A; color: #fff; font-size: 14px; font-weight: 700; cursor: pointer;">{{ $el['save'] }}</button>
            </div>
        </div>
    </div>
    <style>@keyframes wfSpin { to { transform: rotate(360deg); } } [x-cloak]{display:none!important;}</style>
@endif

{{-- ---------- QR modal ---------- --}}
@if($E['qrEquip'])
    <div style="position: fixed; inset: 0; z-index: 80; background: rgba(22,24,29,0.55); display: flex; align-items: center; justify-content: center; padding: 16px;">
        <div style="width: 360px; max-width: 100%; background: #fff; border-radius: 18px; padding: 24px; text-align: center;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                <div style="font-family: 'Space Grotesk'; font-size: 16px; font-weight: 700;">{{ $el['qrTitle'] }}</div>
                <button wire:click="closeEquip" style="border: none; background: transparent; font-size: 22px; line-height: 1; color: #8A8880; cursor: pointer;">×</button>
            </div>
            <div style="font-size: 13.5px; font-weight: 600; color: #16181D; margin: 6px 0 14px;">{{ $E['qrEquip']['name'] }}</div>
            <div style="display: inline-flex; padding: 14px; background: #fff; border: 1px solid #ECEAE3; border-radius: 14px;">{!! $E['qrEquip']['svg'] !!}</div>
            <div style="font-family: 'Space Grotesk'; font-size: 15px; font-weight: 700; letter-spacing: .12em; color: #16181D; margin-top: 12px;">{{ $E['qrEquip']['token'] }}</div>
            <div style="font-size: 11.5px; color: #A7A49B; margin-top: 10px; line-height: 1.55;">{{ $el['qrHint'] }}</div>
        </div>
    </div>
@endif
