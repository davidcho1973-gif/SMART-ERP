@php
    $E = $A['expenses'];
    $el = $E['labels'];
    $sel = $E['selected'];
@endphp

{{-- ============ EXPENSES · RECEIPTS ============ --}}

{{-- toolbar --}}
<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
    <div style="display: flex; gap: 3px; background: #fff; border: 1px solid #E4E2DB; border-radius: 11px; padding: 3px;">
        @foreach(['all' => $el['filter_all'], 'pending' => $el['filter_pending'], 'approved' => $el['filter_approved'], 'rejected' => $el['filter_rejected']] as $fk => $fl)
            @php $fon = $E['filter'] === $fk; @endphp
            <button wire:click="$set('expFilter', '{{ $fk }}')"
                style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 13px; border: none; border-radius: 8px; font-size: 12.5px; font-weight: {{ $fon ? '700' : '600' }}; cursor: pointer; background: {{ $fon ? '#16181D' : 'transparent' }}; color: {{ $fon ? '#fff' : '#5A5D64' }};">
                {{ $fl }}
                @if($fk === 'pending' && $E['pendingCount'] > 0)
                    <span style="min-width: 17px; height: 17px; padding: 0 5px; border-radius: 9px; background: #E85D2A; color: #fff; font-size: 10.5px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center;">{{ $E['pendingCount'] }}</span>
                @endif
            </button>
        @endforeach
    </div>
    <div style="display: flex; align-items: center; gap: 8px; background: #fff; border: 1px solid #E4E2DB; border-radius: 10px; padding: 8px 12px; flex: 1; min-width: 150px; max-width: 240px;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#A7A49B" stroke-width="2" style="flex-shrink: 0;"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>
        <input type="text" wire:model.live.debounce.300ms="expSearch" placeholder="{{ $el['searchPh'] }}" style="border: none; outline: none; background: transparent; font-size: 13px; width: 100%; color: #16181D;">
    </div>
    <div style="flex: 1;"></div>
    @if($E['canSubmit'])
        <button wire:click="openExpenseForm" style="display: inline-flex; align-items: center; gap: 7px; padding: 9px 15px; border: none; border-radius: 10px; background: #E85D2A; color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; box-shadow: 0 2px 8px rgba(232,93,42,0.28);">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>{{ $el['add'] }}
        </button>
    @endif
</div>

{{-- table + detail drawer --}}
<div style="display: grid; grid-template-columns: 1fr 340px; gap: 14px; margin-top: 14px;" class="wf-exp-grid">

    {{-- ---------- receipt table ---------- --}}
    <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; overflow: hidden;">
        @if(count($E['rows']))
            <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13.5px; font-variant-numeric: tabular-nums;">
                <thead>
                    <tr style="text-align: left;">
                        <th style="width: 46px; padding: 14px 0 11px 16px;"></th>
                        <th style="font-size: 10.5px; letter-spacing: .05em; text-transform: uppercase; color: #A7A49B; font-weight: 700; padding: 14px 12px 11px;">{{ $el['vendorCat'] }}</th>
                        <th style="font-size: 10.5px; letter-spacing: .05em; text-transform: uppercase; color: #A7A49B; font-weight: 700; padding: 14px 12px 11px;">{{ $el['site'] }}</th>
                        <th style="font-size: 10.5px; letter-spacing: .05em; text-transform: uppercase; color: #A7A49B; font-weight: 700; padding: 14px 12px 11px; text-align: right;">{{ $el['date'] }}</th>
                        <th style="font-size: 10.5px; letter-spacing: .05em; text-transform: uppercase; color: #A7A49B; font-weight: 700; padding: 14px 12px 11px; text-align: right;">{{ $el['amount'] }}</th>
                        <th style="font-size: 10.5px; letter-spacing: .05em; text-transform: uppercase; color: #A7A49B; font-weight: 700; padding: 14px 16px 11px 12px; text-align: right;">{{ $el['statusCol'] }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($E['rows'] as $r)
                        @php $on = $sel && $sel['id'] === $r['id']; @endphp
                        <tr wire:click="selectExpense({{ $r['id'] }})" wire:key="exp-{{ $r['id'] }}" style="cursor: pointer; background: {{ $on ? '#FCEEE7' : 'transparent' }};"
                            @if(!$on) onmouseover="this.style.background='#FAF9F5'" onmouseout="this.style.background='transparent'" @endif>
                            <td style="padding: 11px 0 11px 16px; border-top: 1px solid #F0EEE8; box-shadow: {{ $on ? 'inset 3px 0 0 #E85D2A' : 'none' }};">
                                @if($r['hasReceipt'] && $r['isImage'])
                                    <img src="{{ $r['receiptUrl'] }}" alt="" loading="lazy" style="width: 36px; height: 36px; object-fit: cover; border-radius: 8px; border: 1px solid #ECEAE3;">
                                @else
                                    <span style="display: inline-flex; width: 36px; height: 36px; border-radius: 8px; background: #F4F3EE; align-items: center; justify-content: center; color: {{ $r['catColor'] }};"><span style="width: 10px; height: 10px; border-radius: 3px; background: {{ $r['catColor'] }};"></span></span>
                                @endif
                            </td>
                            <td style="padding: 11px 12px; border-top: 1px solid #F0EEE8;">
                                <div style="font-weight: 600;">{{ $r['vendor'] }}</div>
                                <div style="display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; color: {{ $r['catColor'] }}; font-weight: 600; margin-top: 2px;"><span style="width: 7px; height: 7px; border-radius: 2px; background: {{ $r['catColor'] }};"></span>{{ $r['catName'] }}</div>
                            </td>
                            <td style="padding: 11px 12px; border-top: 1px solid #F0EEE8; color: #5A5D64;">{{ $r['site'] }}</td>
                            <td style="padding: 11px 12px; border-top: 1px solid #F0EEE8; text-align: right; color: #5A5D64;">{{ $r['dateShort'] }}</td>
                            <td style="padding: 11px 12px; border-top: 1px solid #F0EEE8; text-align: right; font-weight: 700;">{{ $r['amountLabel'] }}</td>
                            <td style="padding: 11px 16px 11px 12px; border-top: 1px solid #F0EEE8; text-align: right;"><span style="font-size: 10.5px; font-weight: 700; padding: 3px 9px; border-radius: 20px; background: {{ $r['statusBg'] }}; color: {{ $r['statusColor'] }};">{{ $r['statusName'] }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @else
            <div style="padding: 54px 16px; text-align: center; color: #B7B4AB; font-size: 13px;">{{ $E['search'] !== '' ? $el['searchPh'] : $el['empty'] }}</div>
        @endif
    </div>

    {{-- ---------- detail drawer (receipt-first, no repeated card) ---------- --}}
    <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; overflow: hidden; align-self: start;">
        @if($sel)
            {{-- receipt: compact thumbnail, click to zoom --}}
            @if($sel['hasReceipt'] && $sel['isImage'])
                <div x-data="{ zoom: false }" wire:key="rcpt-{{ $sel['id'] }}" style="padding: 14px 16px 0;">
                    <button type="button" @click="zoom = true" style="display: flex; align-items: center; gap: 11px; width: 100%; text-align: left; padding: 8px; border: 1px solid #ECEAE3; border-radius: 11px; background: #FAFAF8; cursor: zoom-in;">
                        <img src="{{ $sel['receiptUrl'] }}" alt="{{ $el['receipt'] }}" loading="lazy" style="width: 48px; height: 48px; object-fit: cover; border-radius: 8px; flex-shrink: 0;">
                        <span style="min-width: 0; flex: 1;"><span style="display: block; font-size: 12.5px; font-weight: 600; color: #16181D;">{{ $el['receipt'] }}</span><span style="font-size: 11px; color: #A7A49B;">{{ $el['openReceipt'] }}</span></span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#A7A49B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;"><path d="M15 3h6v6M10 14 21 3M21 14v7H3V3h7"/></svg>
                    </button>
                    <template x-teleport="body">
                        <div x-show="zoom" x-cloak @click="zoom = false" @keydown.escape.window="zoom = false" x-transition.opacity style="position: fixed; inset: 0; z-index: 100; background: rgba(0,0,0,0.85); display: flex; align-items: center; justify-content: center; padding: 24px; cursor: zoom-out;">
                            <img src="{{ $sel['receiptUrl'] }}" alt="{{ $el['receipt'] }}" style="max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 8px; box-shadow: 0 10px 40px rgba(0,0,0,0.5);">
                        </div>
                    </template>
                </div>
            @elseif($sel['hasReceipt'])
                <div style="padding: 14px 16px 0;"><a href="{{ $sel['receiptUrl'] }}" target="_blank" rel="noopener" style="display: flex; align-items: center; gap: 8px; padding: 12px; border: 1px solid #ECEAE3; border-radius: 11px; text-decoration: none; color: #16181D; font-size: 13px; font-weight: 600;"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#E85D2A" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>{{ $el['openReceipt'] }}</a></div>
            @endif

            <div style="padding: 14px 18px 18px;">
                <div style="display: flex; align-items: baseline; justify-content: space-between; gap: 8px;">
                    <div style="font-family: 'Space Grotesk'; font-size: 24px; font-weight: 700; letter-spacing: -0.02em; font-variant-numeric: tabular-nums;">{{ $sel['amountLabel'] }}</div>
                    <span style="font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 20px; background: {{ $sel['statusBg'] }}; color: {{ $sel['statusColor'] }};">{{ $sel['statusName'] }}</span>
                </div>
                <div style="font-size: 14.5px; font-weight: 600; margin-top: 3px;">{{ $sel['vendor'] }}</div>

                {{-- meta: shown once, here (not repeated as a card) --}}
                <div style="margin-top: 13px; border-top: 1px solid #F0EEE8;">
                    <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #F0EEE8; font-size: 13px;"><span style="color: #A7A49B;">{{ $el['category'] }}</span><span style="font-weight: 600; color: {{ $sel['catColor'] }};">{{ $sel['catName'] }}</span></div>
                    <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #F0EEE8; font-size: 13px;"><span style="color: #A7A49B;">{{ $el['site'] }}</span><span style="font-weight: 600;">{{ $sel['site'] }}</span></div>
                    <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #F0EEE8; font-size: 13px;"><span style="color: #A7A49B;">{{ $el['date'] }}</span><span style="font-weight: 600;">{{ $sel['date'] }}</span></div>
                    <div style="display: flex; justify-content: space-between; padding: 8px 0; font-size: 13px;"><span style="color: #A7A49B;">{{ $el['by'] }}</span><span style="font-weight: 600;">{{ $sel['submitter'] }}</span></div>
                </div>

                @if($sel['note'])
                    <div style="margin-top: 12px; font-size: 12.5px; color: #5A5D64; background: #F7F5F0; border-radius: 9px; padding: 9px 11px;">{{ $sel['note'] }}</div>
                @endif
                @if($sel['status'] === 'rejected' && $sel['rejectReason'])
                    <div style="margin-top: 12px; font-size: 12.5px; color: #B23B3B; background: #FBEBE9; border-radius: 9px; padding: 9px 11px;"><span style="color: #A7A49B;">{{ $el['reason'] }}:</span> {{ $sel['rejectReason'] }}</div>
                @endif

                @if($sel['pending'] && $E['canDecide'])
                    @if($E['rejectId'] === $sel['id'])
                        <div style="margin-top: 14px;">
                            <input type="text" wire:model="expRejectNote" placeholder="{{ $el['rejectPh'] }}" style="width: 100%; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #FAFAF8;">
                            <div style="display: flex; gap: 8px; margin-top: 8px;">
                                <button wire:click="cancelExpReject" style="flex: 1; padding: 9px; border: 1px solid #E4E2DB; border-radius: 9px; background: #fff; color: #8A8880; font-size: 12.5px; font-weight: 600; cursor: pointer;">{{ $el['cancel'] }}</button>
                                <button wire:click="rejectExpense({{ $sel['id'] }})" style="flex: 1.4; padding: 9px; border: none; border-radius: 9px; background: #D9483B; color: #fff; font-size: 12.5px; font-weight: 700; cursor: pointer;">{{ $el['confirmReject'] }}</button>
                            </div>
                        </div>
                    @else
                        <div style="display: flex; gap: 9px; margin-top: 16px;">
                            <button wire:click="askRejectExpense({{ $sel['id'] }})" style="flex: 1; padding: 10px; border: 1px solid #E4E2DB; border-radius: 10px; background: #fff; color: #D9483B; font-size: 12.5px; font-weight: 700; cursor: pointer;">{{ $el['reject'] }}</button>
                            <button wire:click="approveExpense({{ $sel['id'] }})" style="flex: 1.5; padding: 10px; border: none; border-radius: 10px; background: #1F9D6B; color: #fff; font-size: 12.5px; font-weight: 700; cursor: pointer;">{{ $el['approve'] }}</button>
                        </div>
                    @endif
                @elseif($sel['decidedLine'])
                    <div style="margin-top: 14px; font-size: 12px; color: #8A8880; display: flex; align-items: center; gap: 7px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="{{ $sel['status'] === 'approved' ? '#1F9D6B' : '#D9483B' }}" stroke-width="2.4" style="flex-shrink: 0;">@if($sel['status'] === 'approved')<path d="M20 6 9 17l-5-5"/>@else<path d="M18 6 6 18M6 6l12 12"/>@endif</svg>{{ $sel['decidedLine'] }}
                    </div>
                @endif
            </div>
        @else
            <div style="padding: 48px 16px; text-align: center; color: #B7B4AB; font-size: 12.5px;">{{ $el['pickReceipt'] }}</div>
        @endif
    </div>
</div>

{{-- ---------- add-receipt modal ---------- --}}
@if($E['formOpen'])
    <div style="position: fixed; inset: 0; z-index: 80; background: rgba(22,24,29,0.55); display: flex; align-items: center; justify-content: center; padding: 16px;">
        <div style="width: 460px; max-width: 100%; max-height: 92vh; overflow-y: auto; background: #fff; border-radius: 18px; padding: 22px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                <div style="font-family: 'Space Grotesk'; font-size: 17px; font-weight: 700;">{{ $el['newExpense'] }}</div>
                <button wire:click="closeExpenseForm" style="border: none; background: transparent; font-size: 22px; line-height: 1; color: #8A8880; cursor: pointer;">×</button>
            </div>

            {{-- receipt photo --}}
            <div style="margin-top: 12px;">
                <div style="font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 6px;">{{ $el['attach'] }}</div>
                @if($expFile)
                    <div style="border: 1px solid #E4E2DB; border-radius: 12px; overflow: hidden; background: #FAFAF8;">
                        @if(str_starts_with((string) $expFile->getMimeType(), 'image/'))
                            <img src="{{ $expFile->temporaryUrl() }}" alt="receipt" style="display: block; width: 100%; max-height: 240px; object-fit: contain; background: #16181D;">
                        @else
                            <div style="padding: 20px; text-align: center; font-size: 13px; color: #5A5D64;">{{ $expFile->getClientOriginalName() }}</div>
                        @endif
                        <div style="display: flex; gap: 8px; padding: 10px;">
                            @if($E['ocrOn'] && str_starts_with((string) $expFile->getMimeType(), 'image/'))
                                <button type="button" wire:click="readReceipt" wire:loading.attr="disabled" wire:target="readReceipt" style="flex: 1.4; padding: 9px; border: none; border-radius: 9px; background: #16181D; color: #fff; font-size: 12.5px; font-weight: 700; cursor: pointer;">
                                    <span wire:loading.remove wire:target="readReceipt">✨ {{ $el['readReceipt'] }}</span>
                                    <span wire:loading wire:target="readReceipt">{{ $el['reading'] }}</span>
                                </button>
                            @endif
                            <button type="button" wire:click="clearExpFile" style="flex: 1; padding: 9px; border: 1px solid #E4E2DB; border-radius: 9px; background: #fff; color: #8A8880; font-size: 12.5px; font-weight: 600; cursor: pointer;">{{ $el['remove'] }}</button>
                        </div>
                    </div>
                @else
                    <label style="display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 22px; border: 1.5px dashed #D6D3CB; border-radius: 12px; background: #FAFAF8; cursor: pointer; text-align: center;">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#E85D2A" stroke-width="1.8"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                        <span style="font-size: 12.5px; font-weight: 600; color: #16181D;">{{ $el['attach'] }}</span>
                        <span style="font-size: 11px; color: #A7A49B;">{{ $el['ocrHint'] }}</span>
                        <input type="file" wire:model="expFile" accept="image/*,.pdf" style="display: none;">
                    </label>
                    <div wire:loading.flex wire:target="expFile" style="align-items: center; gap: 7px; margin-top: 7px; font-size: 12px; color: #8A8880;">
                        <span style="width: 12px; height: 12px; border: 2px solid #E4E2DB; border-top-color: #E85D2A; border-radius: 50%; display: inline-block; animation: wfSpin 0.7s linear infinite;"></span>{{ $el['reading'] }}
                    </div>
                @endif
            </div>

            {{-- fields --}}
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 14px;">
                <label style="grid-column: 1 / -1;">
                    <span style="display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 5px;">{{ $el['vendor'] }}</span>
                    <input type="text" wire:model="expVendor" style="width: 100%; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #FAFAF8;">
                </label>
                <label>
                    <span style="display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 5px;">{{ $el['amount'] }} (USD)</span>
                    <input type="number" step="0.01" min="0" wire:model="expAmount" style="width: 100%; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #FAFAF8;">
                </label>
                <label>
                    <span style="display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 5px;">{{ $el['date'] }}</span>
                    <input type="date" wire:model="expDate" style="width: 100%; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #FAFAF8;">
                </label>
                <label>
                    <span style="display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 5px;">{{ $el['category'] }}</span>
                    <select wire:model="expCategory" style="width: 100%; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #FAFAF8;">
                        @foreach($E['categories'] as $c)
                            <option value="{{ $c['key'] }}">{{ $c['name'] }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span style="display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 5px;">{{ $el['site'] }}</span>
                    <select wire:model="expSite" style="width: 100%; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #FAFAF8;">
                        @foreach($E['siteOptions'] as $o)
                            <option value="{{ $o['id'] }}">{{ $o['label'] }}</option>
                        @endforeach
                    </select>
                </label>
                <label style="grid-column: 1 / -1;">
                    <span style="display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #A7A49B; margin-bottom: 5px;">{{ $el['note'] }}</span>
                    <input type="text" wire:model="expNote" style="width: 100%; padding: 9px 11px; border: 1.5px solid #E4E2DB; border-radius: 9px; font-size: 13px; outline: none; background: #FAFAF8;">
                </label>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 16px;">
                <button wire:click="closeExpenseForm" style="flex: 1; padding: 12px; border: 1px solid #E4E2DB; border-radius: 11px; background: #fff; color: #5A5D64; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $el['cancel'] }}</button>
                <button wire:click="submitExpense" wire:loading.attr="disabled" wire:target="submitExpense,expFile" style="flex: 1.6; padding: 12px; border: none; border-radius: 11px; background: #E85D2A; color: #fff; font-size: 14px; font-weight: 700; cursor: pointer;">{{ $el['save'] }}</button>
            </div>
        </div>
    </div>
    <style>@keyframes wfSpin { to { transform: rotate(360deg); } }</style>
@endif

<style>
    @media (max-width: 820px) { .wf-exp-grid { grid-template-columns: 1fr !important; } }
    [x-cloak] { display: none !important; }
</style>
