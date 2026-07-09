{{-- Self-report modals for the desktop (결근 / 휴가 / 퇴사) — driven by $statusSheet.
     The mobile app renders its own bottom-sheet version inside worker.blade. --}}
@if(in_array($statusSheet, ['absent', 'leave', 'resign'], true))
    <div wire:click.self="closeStatusSheet" style="position: fixed; inset: 0; z-index: 80; background: rgba(22,24,29,0.45); display: flex; align-items: center; justify-content: center; padding: 20px;">
        <div style="width: 100%; max-width: 440px; background: #fff; border-radius: 18px; padding: 24px 24px 26px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
            @if($statusSheet === 'absent')
                <div style="font-size: 18px; font-weight: 800;">{{ $L['w_st_absentT'] }}</div>
                <div style="font-size: 12.5px; color: #8A8880; margin: 5px 0 16px;">{{ $L['w_st_absentTs'] }}</div>
                <label style="display: block;"><span style="font-size: 11.5px; color: #8A8880;">{{ $L['w_st_reason'] }}</span><input wire:model="absentReason" placeholder="{{ $L['w_st_reasonPh'] }}" style="width: 100%; margin-top: 5px; padding: 11px 12px; border: 1px solid #E4E2DB; border-radius: 11px; font-size: 14px; outline: none;"/></label>
                <div style="display: flex; gap: 10px; margin-top: 18px;">
                    <button wire:click="closeStatusSheet" style="flex: 1; padding: 12px; border: 1px solid #E4E2DB; border-radius: 11px; background: #fff; font-size: 14px; font-weight: 700; cursor: pointer;">{{ $L['w_cancel'] }}</button>
                    <button wire:click="reportAbsent" style="flex: 1.3; padding: 12px; border: none; border-radius: 11px; background: #C0392B; color: #fff; font-size: 14px; font-weight: 800; cursor: pointer;">{{ $L['w_st_report'] }}</button>
                </div>
            @elseif($statusSheet === 'leave')
                <div style="font-size: 18px; font-weight: 800;">{{ $L['w_st_leaveT'] }}</div>
                <div style="font-size: 12.5px; color: #8A8880; margin: 5px 0 16px;">{{ $L['w_st_leaveTs'] }}</div>
                <div style="display: flex; gap: 10px;">
                    <label style="flex: 1;"><span style="font-size: 11.5px; color: #8A8880;">{{ $L['w_st_start'] }}</span><input wire:model="leaveStart" type="date" style="width: 100%; margin-top: 5px; padding: 10px 11px; border: 1px solid #E4E2DB; border-radius: 11px; font-size: 14px; outline: none; font-family: 'Space Grotesk';"/></label>
                    <label style="flex: 1;"><span style="font-size: 11.5px; color: #8A8880;">{{ $L['w_st_end'] }}</span><input wire:model="leaveEnd" type="date" style="width: 100%; margin-top: 5px; padding: 10px 11px; border: 1px solid #E4E2DB; border-radius: 11px; font-size: 14px; outline: none; font-family: 'Space Grotesk';"/></label>
                </div>
                <label style="display: block; margin-top: 11px;"><span style="font-size: 11.5px; color: #8A8880;">{{ $L['w_st_reason'] }}</span><input wire:model="leaveReason" placeholder="{{ $L['w_st_leavePh'] }}" style="width: 100%; margin-top: 5px; padding: 11px 12px; border: 1px solid #E4E2DB; border-radius: 11px; font-size: 14px; outline: none;"/></label>
                <div style="display: flex; gap: 10px; margin-top: 18px;">
                    <button wire:click="closeStatusSheet" style="flex: 1; padding: 12px; border: 1px solid #E4E2DB; border-radius: 11px; background: #fff; font-size: 14px; font-weight: 700; cursor: pointer;">{{ $L['w_cancel'] }}</button>
                    <button wire:click="saveLeave" style="flex: 1.3; padding: 12px; border: none; border-radius: 11px; background: #3B72E0; color: #fff; font-size: 14px; font-weight: 800; cursor: pointer;">{{ $L['w_st_send'] }}</button>
                </div>
            @elseif($statusSheet === 'resign')
                <div style="font-size: 18px; font-weight: 800;">{{ $L['w_st_resignT'] }}</div>
                <div style="font-size: 12.5px; color: #8A8880; margin: 5px 0 16px;">{{ $L['w_st_resignTs'] }}</div>
                <label style="display: block;"><span style="font-size: 11.5px; color: #8A8880;">{{ $L['w_st_lastDay'] }}</span><input wire:model="resignOn" type="date" style="width: 100%; margin-top: 5px; padding: 10px 11px; border: 1px solid #E4E2DB; border-radius: 11px; font-size: 14px; outline: none; font-family: 'Space Grotesk';"/></label>
                <label style="display: block; margin-top: 11px;"><span style="font-size: 11.5px; color: #8A8880;">{{ $L['w_st_reason'] }}</span><input wire:model="resignReason" placeholder="{{ $L['w_st_resignPh'] }}" style="width: 100%; margin-top: 5px; padding: 11px 12px; border: 1px solid #E4E2DB; border-radius: 11px; font-size: 14px; outline: none;"/></label>
                <div style="display: flex; gap: 10px; margin-top: 18px;">
                    <button wire:click="closeStatusSheet" style="flex: 1; padding: 12px; border: 1px solid #E4E2DB; border-radius: 11px; background: #fff; font-size: 14px; font-weight: 700; cursor: pointer;">{{ $L['w_cancel'] }}</button>
                    <button wire:click="saveResign" style="flex: 1.3; padding: 12px; border: none; border-radius: 11px; background: #6B6E76; color: #fff; font-size: 14px; font-weight: 800; cursor: pointer;">{{ $L['w_st_send'] }}</button>
                </div>
            @endif
        </div>
    </div>
@endif
