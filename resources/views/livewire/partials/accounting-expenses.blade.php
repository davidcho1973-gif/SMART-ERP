@php
    $E = $A['expenses'];
    $el = $E['labels'];
    $sel = $E['selected'];
@endphp

{{-- ============ EXPENSES · RECEIPTS ============ --}}

{{-- toolbar --}}
<div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
    <div style="display: flex; gap: 5px; background: #fff; border: 1px solid #E4E2DB; border-radius: 11px; padding: 4px;">
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
    <div style="flex: 1;"></div>
    @if($E['canSubmit'])
        <button wire:click="openExpenseForm" style="display: inline-flex; align-items: center; gap: 7px; padding: 9px 15px; border: none; border-radius: 10px; background: #E85D2A; color: #fff; font-size: 13px; font-weight: 700; cursor: pointer;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>{{ $el['add'] }}
        </button>
    @endif
</div>

{{-- list + detail --}}
<div style="display: grid; grid-template-columns: 1fr 320px; gap: 16px; margin-top: 16px;" class="wf-exp-grid">

    {{-- ---------- receipt list ---------- --}}
    <div style="display: flex; flex-direction: column; gap: 8px;">
        @forelse($E['rows'] as $r)
            @php $on = $sel && $sel['id'] === $r['id']; @endphp
            <button wire:click="selectExpense({{ $r['id'] }})" wire:key="exp-{{ $r['id'] }}"
                style="display: grid; grid-template-columns: 40px 1fr auto; gap: 12px; align-items: center; width: 100%; text-align: left; background: #fff; border: 1px solid {{ $on ? '#E85D2A' : '#E4E2DB' }}; box-shadow: {{ $on ? '0 0 0 1px #E85D2A' : 'none' }}; border-radius: 12px; padding: 11px 13px; cursor: pointer;">
                <span style="width: 40px; height: 40px; border-radius: 9px; background: {{ $r['hasReceipt'] ? '#F4F3EE' : '#FAFAF8' }}; display: inline-flex; align-items: center; justify-content: center; color: {{ $r['catColor'] }};">
                    @if($r['hasReceipt'])
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1z"/><path d="M8 7h8M8 11h8M8 15h5"/></svg>
                    @else
                        <span style="width: 10px; height: 10px; border-radius: 3px; background: {{ $r['catColor'] }};"></span>
                    @endif
                </span>
                <span style="min-width: 0;">
                    <span style="display: block; font-size: 13.5px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $r['vendor'] }}</span>
                    <span style="display: inline-flex; align-items: center; gap: 7px; font-size: 11.5px; color: #A7A49B; margin-top: 2px;">
                        <span style="font-weight: 600; color: {{ $r['catColor'] }};">{{ $r['catName'] }}</span>· {{ $r['date'] }} · {{ $r['site'] }}
                    </span>
                </span>
                <span style="text-align: right;">
                    <span style="display: block; font-size: 14px; font-weight: 700; font-variant-numeric: tabular-nums;">{{ $r['amountLabel'] }}</span>
                    <span style="display: inline-block; margin-top: 3px; font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 20px; background: {{ $r['statusBg'] }}; color: {{ $r['statusColor'] }};">{{ $r['statusName'] }}</span>
                </span>
            </button>
        @empty
            <div style="padding: 44px 16px; text-align: center; color: #B7B4AB; font-size: 13px; background: #fff; border: 1px solid #E4E2DB; border-radius: 14px;">{{ $el['empty'] }}</div>
        @endforelse
    </div>

    {{-- ---------- detail pane ---------- --}}
    <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 14px; padding: 16px; align-self: start;">
        @if($sel)
            @if($sel['hasReceipt'] && $sel['isImage'])
                <a href="{{ $sel['receiptUrl'] }}" target="_blank" rel="noopener" style="display: block; border-radius: 11px; overflow: hidden; border: 1px solid #ECEAE3; margin-bottom: 14px;">
                    <img src="{{ $sel['receiptUrl'] }}" alt="{{ $el['receipt'] }}" loading="lazy" style="display: block; width: 100%; height: auto; max-height: 300px; object-fit: cover;">
                </a>
            @elseif($sel['hasReceipt'])
                <a href="{{ $sel['receiptUrl'] }}" target="_blank" rel="noopener" style="display: flex; align-items: center; gap: 8px; padding: 12px; border: 1px solid #ECEAE3; border-radius: 11px; text-decoration: none; color: #16181D; font-size: 13px; font-weight: 600; margin-bottom: 14px;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#E85D2A" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>{{ $el['openReceipt'] }}
                </a>
            @else
                <div style="padding: 20px; text-align: center; color: #B7B4AB; font-size: 12px; background: #FAFAF8; border: 1px dashed #E4E2DB; border-radius: 11px; margin-bottom: 14px;">{{ $el['noReceipt'] }}</div>
            @endif

            <div style="display: flex; align-items: baseline; justify-content: space-between; gap: 8px;">
                <div style="font-family: 'Space Grotesk'; font-size: 20px; font-weight: 700; font-variant-numeric: tabular-nums;">{{ $sel['amountLabel'] }}</div>
                <span style="font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 20px; background: {{ $sel['statusBg'] }}; color: {{ $sel['statusColor'] }};">{{ $sel['statusName'] }}</span>
            </div>
            <div style="font-size: 14px; font-weight: 600; margin-top: 4px;">{{ $sel['vendor'] }}</div>
            <div style="display: flex; flex-wrap: wrap; gap: 6px 14px; margin-top: 10px; font-size: 12.5px; color: #5A5D64;">
                <span><span style="color: #A7A49B;">{{ $el['category'] }}:</span> <b style="color: {{ $sel['catColor'] }};">{{ $sel['catName'] }}</b></span>
                <span><span style="color: #A7A49B;">{{ $el['date'] }}:</span> {{ $sel['date'] }}</span>
                <span><span style="color: #A7A49B;">{{ $el['site'] }}:</span> {{ $sel['site'] }}</span>
            </div>
            <div style="font-size: 11.5px; color: #A7A49B; margin-top: 8px;">{{ $el['by'] }} {{ $sel['submitter'] }}</div>
            @if($sel['note'])
                <div style="margin-top: 10px; font-size: 12.5px; color: #5A5D64; background: #F7F5F0; border-radius: 9px; padding: 9px 11px;">{{ $sel['note'] }}</div>
            @endif
            @if($sel['status'] === 'rejected' && $sel['rejectReason'])
                <div style="margin-top: 10px; font-size: 12.5px; color: #B23B3B; background: #FBEBE9; border-radius: 9px; padding: 9px 11px;"><span style="color: #A7A49B;">{{ $el['reason'] }}:</span> {{ $sel['rejectReason'] }}</div>
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
                    <div style="display: flex; gap: 8px; margin-top: 14px;">
                        <button wire:click="askRejectExpense({{ $sel['id'] }})" style="flex: 1; padding: 10px; border: 1px solid #E4E2DB; border-radius: 9px; background: #fff; color: #D9483B; font-size: 12.5px; font-weight: 700; cursor: pointer;">{{ $el['reject'] }}</button>
                        <button wire:click="approveExpense({{ $sel['id'] }})" style="flex: 1.4; padding: 10px; border: none; border-radius: 9px; background: #1F9D6B; color: #fff; font-size: 12.5px; font-weight: 700; cursor: pointer;">{{ $el['approve'] }}</button>
                    </div>
                @endif
            @endif
        @else
            <div style="padding: 40px 12px; text-align: center; color: #B7B4AB; font-size: 12.5px;">{{ $el['pickReceipt'] }}</div>
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
</style>
