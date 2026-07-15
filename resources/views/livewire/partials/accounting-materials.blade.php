@php
    $M = $A['materials'];
    $ml = $M['labels'];
    $th = 'font-size: 10.5px; letter-spacing: .05em; text-transform: uppercase; color: #A7A49B; font-weight: 700; padding: 14px 12px 11px;';
    $bd = 'border-top: 1px solid #F0EEE8;';
@endphp

{{-- ============ MATERIALS · EQUIPMENT (M3) ============ --}}

{{-- inner section toggle --}}
<div style="display: flex; gap: 4px; background: #fff; border: 1px solid #E4E2DB; border-radius: 12px; padding: 4px; width: fit-content; margin-bottom: 16px;">
    @foreach(['materials' => $ml['materials'], 'equipment' => $ml['equipment']] as $sk => $sl)
        @php $son = $M['section'] === $sk; @endphp
        <button wire:click="setMatSection('{{ $sk }}')" style="padding: 8px 18px; border: none; border-radius: 9px; font-size: 13px; font-weight: {{ $son ? '700' : '600' }}; cursor: pointer; background: {{ $son ? '#16181D' : 'transparent' }}; color: {{ $son ? '#fff' : '#5A5D64' }};">
            {{ $sl }}@if($sk === 'equipment')<span style="font-size: 9.5px; font-weight: 700; margin-left: 6px; padding: 2px 6px; border-radius: 20px; background: {{ $son ? 'rgba(255,255,255,.16)' : '#F0EEE8' }}; color: {{ $son ? '#fff' : '#A7A49B' }};">준비중</span>@endif
        </button>
    @endforeach
</div>

@if($M['section'] === 'equipment')
    <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 18px; padding: 48px 34px; text-align: center; max-width: 540px; margin: 12px auto;">
        <span style="display: inline-flex; width: 54px; height: 54px; border-radius: 15px; background: #E7F5EF; color: #1F9D6B; align-items: center; justify-content: center; margin-bottom: 12px;"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 17h13V6H3zM16 9h4l3 3v5h-7z"/><circle cx="6.5" cy="17.5" r="1.5"/><circle cx="18.5" cy="17.5" r="1.5"/></svg></span>
        <div style="font-family: 'Space Grotesk'; font-size: 19px; font-weight: 700;">{{ $ml['equipment'] }}</div>
        <div style="font-size: 13.5px; color: #5A5D64; margin-top: 8px; line-height: 1.6;">{{ $ml['equipSoon'] }}</div>
    </div>
@else

{{-- toolbar --}}
<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
    <div style="display: flex; gap: 3px; background: #fff; border: 1px solid #E4E2DB; border-radius: 11px; padding: 3px;">
        @foreach(['all' => $ml['filter_all'], 'pending' => $ml['filter_pending'], 'approved' => $ml['filter_approved'], 'rejected' => $ml['filter_rejected']] as $fk => $fl)
            @php $fon = $M['filter'] === $fk; @endphp
            <button wire:click="$set('matFilter', '{{ $fk }}')" style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; border: none; border-radius: 8px; font-size: 12.5px; font-weight: {{ $fon ? '700' : '600' }}; cursor: pointer; background: {{ $fon ? '#16181D' : 'transparent' }}; color: {{ $fon ? '#fff' : '#5A5D64' }};">
                {{ $fl }}@if($fk === 'pending' && $M['pendingCount'] > 0)<span style="min-width: 17px; height: 17px; padding: 0 5px; border-radius: 9px; background: #E85D2A; color: #fff; font-size: 10.5px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center;">{{ $M['pendingCount'] }}</span>@endif
            </button>
        @endforeach
    </div>
    <div style="display: flex; align-items: center; gap: 8px; background: #fff; border: 1px solid #E4E2DB; border-radius: 10px; padding: 8px 12px; flex: 1; min-width: 140px; max-width: 230px;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#A7A49B" stroke-width="2" style="flex-shrink: 0;"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>
        <input type="text" wire:model.live.debounce.300ms="matSearch" placeholder="{{ $ml['searchPh'] }}" style="border: none; outline: none; background: transparent; font-size: 13px; width: 100%; color: #16181D;">
    </div>
    <div style="flex: 1;"></div>
    @if($M['canSubmit'])
        <button wire:click="openMatBatch('manual')" style="padding: 9px 13px; border: 1px solid #E4E2DB; border-radius: 10px; background: #fff; color: #16181D; font-size: 12.5px; font-weight: 600; cursor: pointer;">{{ $ml['addManual'] }}</button>
        <button wire:click="openMatBatch('opening')" style="padding: 9px 13px; border: 1px solid #E4E2DB; border-radius: 10px; background: #fff; color: #16181D; font-size: 12.5px; font-weight: 600; cursor: pointer;">{{ $ml['addOpening'] }}</button>
        <button wire:click="openMatBatch('delivery')" style="display: inline-flex; align-items: center; gap: 7px; padding: 9px 15px; border: none; border-radius: 10px; background: #E85D2A; color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; box-shadow: 0 2px 8px rgba(232,93,42,0.28);">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>{{ $ml['addSlip'] }}
        </button>
    @endif
</div>

{{-- batches table --}}
<div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; overflow: hidden; margin-top: 14px;">
    @if(count($M['rows']))
        <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; font-size: 13.5px; font-variant-numeric: tabular-nums; min-width: 900px;">
            <thead>
                <tr style="text-align: left;">
                    <th style="{{ $th }} width: 36px; padding-left: 16px;"></th>
                    <th style="{{ $th }}">{{ $ml['col_type'] }}</th>
                    <th style="{{ $th }}">{{ $ml['col_vendor'] }}</th>
                    <th style="{{ $th }}">{{ $ml['col_site'] }}</th>
                    <th style="{{ $th }} text-align: right;">{{ $ml['col_date'] }}</th>
                    <th style="{{ $th }} text-align: right;">{{ $ml['col_items'] }}</th>
                    <th style="{{ $th }} text-align: right;">{{ $ml['col_amount'] }}</th>
                    <th style="{{ $th }} text-align: right; padding-right: 16px;">{{ $ml['col_status'] }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($M['rows'] as $r)
                    @php $open = $M['expandId'] === $r['id']; @endphp
                    <tr wire:key="mat-{{ $r['id'] }}" onmouseover="this.style.background='#FAF9F5'" onmouseout="this.style.background='transparent'">
                        <td style="{{ $bd }} padding: 10px 0 10px 16px;">
                            <button wire:click="toggleMatExpand({{ $r['id'] }})" style="width: 26px; height: 26px; border: 1px solid #E4E2DB; border-radius: 7px; background: #fff; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; color: #8A8880;">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" style="transform: rotate({{ $open ? '90' : '0' }}deg); transition: transform .15s;"><path d="M9 18l6-6-6-6"/></svg>
                            </button>
                        </td>
                        <td style="{{ $bd }} padding: 10px 12px;"><span style="display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 600; color: {{ $r['kindColor'] }};"><span style="width: 8px; height: 8px; border-radius: 2px; background: {{ $r['kindColor'] }};"></span>{{ $r['kindName'] }}</span></td>
                        <td style="{{ $bd }} padding: 10px 12px; font-weight: 600;">{{ $r['vendor'] }}@if($r['hasImage'])<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#A7A49B" stroke-width="2" style="margin-left: 5px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>@endif</td>
                        <td style="{{ $bd }} padding: 10px 12px; color: #5A5D64;">{{ $r['site'] }}</td>
                        <td style="{{ $bd }} padding: 10px 12px; text-align: right; color: #5A5D64;">{{ $r['dateShort'] }}</td>
                        <td style="{{ $bd }} padding: 10px 12px; text-align: right; color: #5A5D64;">{{ $r['lineCount'] }} {{ $ml['items'] }}</td>
                        <td style="{{ $bd }} padding: 10px 12px; text-align: right; font-weight: 700; color: {{ $r['costed'] ? '#16181D' : '#C4C1B8' }};">{{ $r['costed'] ? $r['totalLabel'] : '—' }}</td>
                        <td style="{{ $bd }} padding: 10px 16px 10px 12px; text-align: right;">
                            @if($r['pending'] && $M['canDecide'])
                                <div style="display: inline-flex; gap: 6px; justify-content: flex-end;">
                                    <button wire:click="askRejectMaterial({{ $r['id'] }})" style="padding: 6px 11px; border: 1px solid #E4E2DB; border-radius: 8px; background: #fff; color: #D9483B; font-size: 12px; font-weight: 700; cursor: pointer;">{{ $ml['reject'] }}</button>
                                    <button wire:click="approveMaterial({{ $r['id'] }})" style="padding: 6px 12px; border: none; border-radius: 8px; background: #1F9D6B; color: #fff; font-size: 12px; font-weight: 700; cursor: pointer;">{{ $ml['approve'] }}</button>
                                </div>
                            @else
                                <span style="font-size: 10.5px; font-weight: 700; padding: 3px 9px; border-radius: 20px; background: {{ $r['statusBg'] }}; color: {{ $r['statusColor'] }};">{{ $r['statusName'] }}</span>
                                @if($r['decidedLine'])<div style="font-size: 10.5px; color: #A7A49B; margin-top: 4px; white-space: nowrap;">{{ $r['decidedLine'] }}</div>@endif
                            @endif
                        </td>
                    </tr>
                    {{-- reject-reason inline row --}}
                    @if($M['rejectId'] === $r['id'])
                        <tr wire:key="matrej-{{ $r['id'] }}"><td colspan="8" style="{{ $bd }} padding: 12px 16px; background: #FDF6F5;">
                            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                                <input type="text" wire:model="matRejectNote" placeholder="{{ $ml['rejectPh'] }}" style="flex: 1; min-width: 180px; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #fff;">
                                <button wire:click="cancelMatReject" style="padding: 9px 14px; border: 1px solid #E4E2DB; border-radius: 9px; background: #fff; color: #8A8880; font-size: 12.5px; font-weight: 600; cursor: pointer;">{{ $ml['cancel'] }}</button>
                                <button wire:click="rejectMaterial({{ $r['id'] }})" style="padding: 9px 16px; border: none; border-radius: 9px; background: #D9483B; color: #fff; font-size: 12.5px; font-weight: 700; cursor: pointer;">{{ $ml['confirmReject'] }}</button>
                            </div>
                        </td></tr>
                    @endif
                    {{-- expanded line items --}}
                    @if($open)
                        <tr wire:key="matexp-{{ $r['id'] }}"><td colspan="8" style="{{ $bd }} padding: 0; background: #FAFAF8;">
                            <div style="padding: 14px 16px; display: grid; grid-template-columns: {{ $r['hasImage'] && $r['isImage'] ? '1fr 160px' : '1fr' }}; gap: 16px; align-items: start;">
                                <div>
                                    <table style="width: 100%; border-collapse: collapse; font-size: 12.5px; font-variant-numeric: tabular-nums;">
                                        <thead><tr style="text-align: left; color: #A7A49B;">
                                            <th style="font-size: 10px; text-transform: uppercase; letter-spacing: .04em; padding: 0 8px 7px; font-weight: 700;">{{ $ml['name'] }}</th>
                                            <th style="font-size: 10px; text-transform: uppercase; letter-spacing: .04em; padding: 0 8px 7px; font-weight: 700; text-align: right;">{{ $ml['qty'] }}</th>
                                            <th style="font-size: 10px; text-transform: uppercase; letter-spacing: .04em; padding: 0 8px 7px; font-weight: 700; text-align: right;">{{ $ml['unitPrice'] }}</th>
                                            <th style="font-size: 10px; text-transform: uppercase; letter-spacing: .04em; padding: 0 8px 7px; font-weight: 700; text-align: right;">{{ $ml['amount'] }}</th>
                                        </tr></thead>
                                        <tbody>
                                            @foreach($r['lines'] as $ln)
                                                <tr>
                                                    <td style="padding: 7px 8px; border-top: 1px solid #EFEDE7; font-weight: 600;">{{ $ln['name'] }}</td>
                                                    <td style="padding: 7px 8px; border-top: 1px solid #EFEDE7; text-align: right; color: #5A5D64;">{{ rtrim(rtrim(number_format($ln['qty'], 2), '0'), '.') }} {{ $ln['unit'] }}</td>
                                                    <td style="padding: 7px 8px; border-top: 1px solid #EFEDE7; text-align: right; color: #5A5D64;">{{ $r['costed'] ? $ln['unitPriceLabel'] : '—' }}</td>
                                                    <td style="padding: 7px 8px; border-top: 1px solid #EFEDE7; text-align: right; font-weight: 700; color: {{ $r['costed'] ? '#16181D' : '#C4C1B8' }};">{{ $r['costed'] ? $ln['amountLabel'] : '—' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                    @unless($r['costed'])<div style="font-size: 11.5px; color: #8A8880; margin-top: 9px;">ⓘ {{ $ml['openingNote'] }}</div>@endunless
                                    <div style="font-size: 11.5px; color: #A7A49B; margin-top: 10px;">{{ $ml['by'] }} {{ $r['submitter'] }}</div>
                                    @if($r['note'])<div style="margin-top: 8px; font-size: 12px; color: #5A5D64; background: #fff; border: 1px solid #EFEDE7; border-radius: 8px; padding: 8px 10px;">{{ $r['note'] }}</div>@endif
                                    @if($r['status'] === 'rejected' && $r['rejectReason'])<div style="margin-top: 8px; font-size: 12px; color: #B23B3B; background: #FBEBE9; border-radius: 8px; padding: 8px 10px;">{{ $ml['reason'] }}: {{ $r['rejectReason'] }}</div>@endif
                                </div>
                                @if($r['hasImage'] && $r['isImage'])
                                    <div x-data="{ z: false }">
                                        <img @click="z = true" src="{{ $r['slipUrl'] }}" alt="{{ $ml['slip'] }}" loading="lazy" style="width: 100%; border-radius: 10px; border: 1px solid #ECEAE3; cursor: zoom-in;">
                                        <template x-teleport="body"><div x-show="z" x-cloak @click="z=false" @keydown.escape.window="z=false" x-transition.opacity style="position: fixed; inset: 0; z-index: 100; background: rgba(0,0,0,.85); display: flex; align-items: center; justify-content: center; padding: 24px; cursor: zoom-out;"><img src="{{ $r['slipUrl'] }}" style="max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 8px;"></div></template>
                                    </div>
                                @endif
                            </div>
                        </td></tr>
                    @endif
                @endforeach
            </tbody>
        </table>
        </div>
    @else
        <div style="padding: 54px 16px; text-align: center; color: #B7B4AB; font-size: 13px;">{{ $M['search'] !== '' ? $ml['searchPh'] : $ml['empty'] }}</div>
    @endif
</div>

{{-- ---------- add-batch modal ---------- --}}
@if($M['formOpen'])
    <div style="position: fixed; inset: 0; z-index: 80; background: rgba(22,24,29,0.55); display: flex; align-items: center; justify-content: center; padding: 16px;">
        <div style="width: 620px; max-width: 100%; max-height: 92vh; overflow-y: auto; background: #fff; border-radius: 18px; padding: 22px;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div style="font-family: 'Space Grotesk'; font-size: 17px; font-weight: 700;">{{ $M['formKindName'] }}</div>
                <button wire:click="closeMatBatch" style="border: none; background: transparent; font-size: 22px; line-height: 1; color: #8A8880; cursor: pointer;">×</button>
            </div>
            @if($M['formKind'] === 'opening')
                <div style="font-size: 12px; color: #8A6A2E; background: #FBF1DE; border-radius: 9px; padding: 9px 11px; margin-top: 10px; line-height: 1.5;">{{ $ml['openingHelp'] }}</div>
            @endif

            {{-- slip photo (delivery emphasises OCR; others optional) --}}
            <div style="margin-top: 14px;">
                <div style="font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 6px;">{{ $ml['photo'] }}@if($M['formKind'] !== 'delivery') <span style="color:#C4C1B8; font-weight:600;">(선택)</span>@endif</div>
                @if($matFile)
                    <div style="border: 1px solid #E4E2DB; border-radius: 12px; overflow: hidden; background: #FAFAF8;">
                        @if(str_starts_with((string) $matFile->getMimeType(), 'image/'))
                            <img src="{{ $matFile->temporaryUrl() }}" alt="slip" style="display: block; width: 100%; max-height: 220px; object-fit: contain; background: #16181D;">
                        @else
                            <div style="padding: 18px; text-align: center; font-size: 13px; color: #5A5D64;">{{ $matFile->getClientOriginalName() }}</div>
                        @endif
                        <div style="display: flex; gap: 8px; padding: 10px;">
                            @if($M['ocrOn'] && str_starts_with((string) $matFile->getMimeType(), 'image/'))
                                <button type="button" wire:click="readSlip" wire:loading.attr="disabled" wire:target="readSlip" style="flex: 1.4; padding: 9px; border: none; border-radius: 9px; background: #16181D; color: #fff; font-size: 12.5px; font-weight: 700; cursor: pointer;">
                                    <span wire:loading.remove wire:target="readSlip">✨ {{ $ml['readSlip'] }}</span><span wire:loading wire:target="readSlip">{{ $ml['reading'] }}</span>
                                </button>
                            @endif
                            <button type="button" wire:click="clearMatFile" style="flex: 1; padding: 9px; border: 1px solid #E4E2DB; border-radius: 9px; background: #fff; color: #8A8880; font-size: 12.5px; font-weight: 600; cursor: pointer;">{{ $ml['remove'] }}</button>
                        </div>
                    </div>
                @else
                    <label style="display: flex; flex-direction: column; align-items: center; gap: 5px; padding: 18px; border: 1.5px dashed #D6D3CB; border-radius: 12px; background: #FAFAF8; cursor: pointer; text-align: center;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#E85D2A" stroke-width="1.8"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                        <span style="font-size: 12.5px; font-weight: 600; color: #16181D;">{{ $ml['photo'] }}</span>
                        @if($M['formKind'] === 'delivery')<span style="font-size: 11px; color: #A7A49B;">{{ $ml['ocrHint'] }}</span>@endif
                        <input type="file" wire:model="matFile" accept="image/*,.pdf" style="display: none;">
                    </label>
                    <div wire:loading.flex wire:target="matFile" style="align-items: center; gap: 7px; margin-top: 7px; font-size: 12px; color: #8A8880;"><span style="width: 12px; height: 12px; border: 2px solid #E4E2DB; border-top-color: #E85D2A; border-radius: 50%; display: inline-block; animation: wfSpin 0.7s linear infinite;"></span>{{ $ml['reading'] }}</div>
                @endif
            </div>

            {{-- header fields --}}
            <div style="display: grid; grid-template-columns: 1fr 130px 1fr; gap: 10px; margin-top: 14px;">
                <label><span style="display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 5px;">{{ $ml['vendor'] }}</span><input type="text" wire:model="matVendor" style="width: 100%; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #FAFAF8;"></label>
                <label><span style="display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 5px;">{{ $ml['date'] }}</span><input type="date" wire:model="matDate" style="width: 100%; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #FAFAF8;"></label>
                <label><span style="display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 5px;">{{ $ml['site'] }}</span><select wire:model="matSite" style="width: 100%; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #FAFAF8;">@foreach($M['siteOptions'] as $o)<option value="{{ $o['id'] }}">{{ $o['label'] }}</option>@endforeach</select></label>
            </div>

            {{-- line-item editor --}}
            <div style="margin-top: 16px;">
                <div style="font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 8px;">{{ $ml['lineItems'] }}</div>
                <div style="display: flex; flex-direction: column; gap: 7px;">
                    @foreach($matLines as $i => $line)
                        <div wire:key="matline-{{ $i }}" style="display: grid; grid-template-columns: 1fr 74px 70px 92px 30px; gap: 7px; align-items: center;">
                            <input type="text" wire:model="matLines.{{ $i }}.name" placeholder="{{ $ml['name'] }}" style="padding: 8px 10px; border: 1.5px solid #E4E2DB; border-radius: 8px; font-size: 13px; outline: none; background: #FAFAF8;">
                            <select wire:model="matLines.{{ $i }}.unit" style="padding: 8px 6px; border: 1.5px solid #E4E2DB; border-radius: 8px; font-size: 12.5px; outline: none; background: #FAFAF8;">@foreach($M['units'] as $u)<option value="{{ $u }}">{{ $u }}</option>@endforeach</select>
                            <input type="number" step="0.01" min="0" wire:model="matLines.{{ $i }}.qty" placeholder="{{ $ml['qty'] }}" style="padding: 8px 8px; border: 1.5px solid #E4E2DB; border-radius: 8px; font-size: 13px; outline: none; background: #FAFAF8; text-align: right;">
                            <input type="number" step="0.01" min="0" wire:model="matLines.{{ $i }}.unitPrice" placeholder="{{ $ml['unitPrice'] }}" @disabled($M['formKind'] === 'opening') style="padding: 8px 8px; border: 1.5px solid #E4E2DB; border-radius: 8px; font-size: 13px; outline: none; background: {{ $M['formKind'] === 'opening' ? '#F0EEE8' : '#FAFAF8' }}; text-align: right;">
                            <button type="button" wire:click="removeMatLine({{ $i }})" style="width: 30px; height: 34px; border: 1px solid #E4E2DB; border-radius: 8px; background: #fff; color: #C0392B; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
                        </div>
                    @endforeach
                </div>
                <button type="button" wire:click="addMatLine" style="margin-top: 9px; padding: 7px 13px; border: 1px dashed #D6D3CB; border-radius: 9px; background: #fff; color: #5A5D64; font-size: 12.5px; font-weight: 600; cursor: pointer;">+ {{ $ml['addLine'] }}</button>
            </div>

            <label style="display: block; margin-top: 14px;"><span style="display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 5px;">{{ $ml['note'] }}</span><input type="text" wire:model="matNote" style="width: 100%; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #FAFAF8;"></label>

            <div style="display: flex; gap: 10px; margin-top: 18px;">
                <button wire:click="closeMatBatch" style="flex: 1; padding: 12px; border: 1px solid #E4E2DB; border-radius: 11px; background: #fff; color: #5A5D64; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $ml['cancel'] }}</button>
                <button wire:click="submitMaterials" wire:loading.attr="disabled" wire:target="submitMaterials,matFile" style="flex: 1.6; padding: 12px; border: none; border-radius: 11px; background: #E85D2A; color: #fff; font-size: 14px; font-weight: 700; cursor: pointer;">{{ $ml['save'] }}</button>
            </div>
        </div>
    </div>
    <style>@keyframes wfSpin { to { transform: rotate(360deg); } } [x-cloak]{display:none!important;}</style>
@endif

@endif
