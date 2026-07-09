{{-- 파견 (out-of-state dispatch) fields — shared by badge register, invite, edit.
     Pass wire:model names: mTo, mFrom, mUntil, mNote. Empty 파견지 = not dispatched. --}}
<div style="grid-column: span 2; margin-top: 4px; padding: 15px 16px; border: 1px solid #F0EEE8; border-radius: 12px; background: #FAFAF8;">
    <div style="font-size: 12px; font-weight: 700; color: #8A8880; margin-bottom: 2px;">🛫 {{ $L['e_dispatch'] }}</div>
    <div style="font-size: 11px; color: #A7A49B; margin-bottom: 12px;">{{ $L['e_dispatchToPh'] }} · {{ $L['e_dispatchTo'] }}{{ isset($hint) ? '' : '' }}</div>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 11px 12px;">
        <label style="grid-column: span 2;"><span style="font-size: 11.5px; color: #A7A49B;">{{ $L['e_dispatchTo'] }}</span>
            <input wire:model="{{ $mTo }}" placeholder="{{ $L['e_dispatchToPh'] }}" style="width:100%;margin-top:5px;padding:9px 11px;border:1px solid #E4E2DB;border-radius:9px;font-size:13.5px;background:#fff;color:#16181D;outline:none;"/></label>
        <label><span style="font-size: 11.5px; color: #A7A49B;">{{ $L['e_dispatchFrom'] }}</span>
            <input wire:model="{{ $mFrom }}" type="date" style="width:100%;margin-top:5px;padding:9px 11px;border:1px solid #E4E2DB;border-radius:9px;font-size:13.5px;background:#fff;color:#16181D;outline:none;font-family:'Space Grotesk';"/></label>
        <label><span style="font-size: 11.5px; color: #A7A49B;">{{ $L['e_dispatchUntil'] }}</span>
            <input wire:model="{{ $mUntil }}" type="date" style="width:100%;margin-top:5px;padding:9px 11px;border:1px solid #E4E2DB;border-radius:9px;font-size:13.5px;background:#fff;color:#16181D;outline:none;font-family:'Space Grotesk';"/></label>
        <label style="grid-column: span 2;"><span style="font-size: 11.5px; color: #A7A49B;">{{ $L['e_dispatchNote'] }}</span>
            <input wire:model="{{ $mNote }}" style="width:100%;margin-top:5px;padding:9px 11px;border:1px solid #E4E2DB;border-radius:9px;font-size:13.5px;background:#fff;color:#16181D;outline:none;"/></label>
    </div>
</div>
