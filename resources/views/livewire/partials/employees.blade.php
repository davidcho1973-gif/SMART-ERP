@php $Ui = \App\Support\Ui::class; $acc = $emp['accColor']; @endphp
{{-- ============ EMPLOYEES ============ --}}
<div>
    <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 16px; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 8px; flex: 1; min-width: 240px; background: #fff; border: 1px solid #E4E2DB; border-radius: 11px; padding: 10px 14px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#A7A49B" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>
            <input wire:model.live="search" placeholder="{{ $L['e_search'] }}" style="border: none; outline: none; flex: 1; font-size: 14px; background: transparent;"/>
        </div>
        <button wire:click="addWorker" style="padding: 10px 18px; border: none; border-radius: 11px; background: #E85D2A; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer; white-space: nowrap;">{{ $L['e_add'] }}</button>
    </div>
    <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 16px; flex-wrap: wrap;">
        <div style="display: inline-flex; gap: 2px; padding: 3px; background: #EAE8E1; border-radius: 10px;">
            <button wire:click="setEmpFilter('active')" style="{{ $Ui::pill($empFilter==='active') }}">{{ $L['e_filterActive'] }}</button>
            <button wire:click="setEmpFilter('terminated')" style="{{ $Ui::pill($empFilter==='terminated') }}">{{ $L['e_filterTerminated'] }}</button>
            <button wire:click="setEmpFilter('all')" style="{{ $Ui::pill($empFilter==='all') }}">{{ $L['e_filterAll'] }}</button>
        </div>
        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <button wire:click="setTeamFilter('all')" style="{{ $Ui::allChip($teamFilter==='all') }}">{{ $L['e_allTeams'] }}</button>
            @foreach($emp['teamChips'] as $c)
                <button wire:click="setTeamFilter('{{ $c['id'] }}')" style="{{ $Ui::teamChip($c['active'], $c['color']) }}">{{ $c['label'] }}</button>
            @endforeach
        </div>
    </div>
    <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 16px; overflow: auto;">
        <div style="display: grid; grid-template-columns: 2.1fr 1.1fr 1fr 1fr 0.9fr 1fr; gap: 8px; padding: 13px 20px; background: #FAFAF8; border-bottom: 1px solid #E4E2DB; font-size: 12px; font-weight: 600; color: #8A8880; min-width: 820px;">
            <span>{{ $L['e_name'] }}</span><span>{{ $L['e_company'] }}</span><span>{{ $L['e_team'] }}</span><span>{{ $L['e_role'] }}</span><span>{{ $L['e_status'] }}</span><span style="text-align: right;">{{ $L['e_action'] }}</span>
        </div>
        @foreach($emp['rows'] as $e)
            <div wire:click="selectEmp({{ $e['id'] }})" style="display: grid; grid-template-columns: 2.1fr 1.1fr 1fr 1fr 0.9fr 1fr; gap: 8px; align-items: center; padding: 12px 20px; border-bottom: 1px solid #F2F0EA; cursor: pointer; font-size: 13px; min-width: 820px; opacity: {{ $e['rowOpacity'] }};">
                <span style="display: flex; align-items: center; gap: 11px; min-width: 0;">
                    <span style="display: inline-flex; width: 36px; height: 36px; border-radius: 50%; background: {{ $e['teamColor'] }}; color: #fff; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; flex-shrink: 0; font-family: 'Space Grotesk';">{{ $e['initials'] }}</span>
                    <span style="min-width: 0; overflow: hidden;"><span style="display: block; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $e['name'] }}</span><span style="display: block; font-size: 11px; color: #A7A49B; font-family: 'Space Grotesk'; white-space: nowrap;">{{ $e['empId'] }}</span></span>
                </span>
                <span style="color: #5A5D64; font-size: 12.5px;">{{ $e['companyName'] }}</span>
                <span><span style="display: inline-flex; align-items: center; gap: 5px; font-size: 12.5px; color: #5A5D64;"><span style="width: 7px; height: 7px; border-radius: 2px; background: {{ $e['teamColor'] }};"></span>{{ $e['teamName'] }}</span></span>
                <span style="color: #5A5D64;">{{ $e['role'] }}</span>
                <span><span style="display: inline-flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 600; color: {{ $e['statusColor'] }}; background: {{ $e['statusBg'] }}; padding: 4px 9px; border-radius: 7px;">{{ $e['statusLabel'] }}</span></span>
                <span style="display: flex; gap: 6px; justify-content: flex-end;">
                    @if($e['isTerminated'])
                        <button wire:click.stop="reactivate({{ $e['id'] }})" title="reactivate" style="width: 30px; height: 30px; border: 1px solid #E4E2DB; border-radius: 8px; background: #fff; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5"/></svg></button>
                    @endif
                    @if($e['isActive'])
                        <button wire:click.stop="askTerm({{ $e['id'] }})" title="terminate" style="width: 30px; height: 30px; border: 1px solid #E4E2DB; border-radius: 8px; background: #fff; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg></button>
                    @endif
                    <button wire:click.stop="askDelete({{ $e['id'] }})" title="delete" style="width: 30px; height: 30px; border: 1px solid #E4E2DB; border-radius: 8px; background: #fff; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg></button>
                </span>
            </div>
        @endforeach
    </div>
</div>

{{-- employee detail drawer --}}
@if($emp['sel'])
    @php $sel = $emp['sel']; @endphp
    <div x-data @click="$wire.closeDetail()" style="position: fixed; inset: 0; z-index: 60; background: rgba(22,24,29,0.4); display: flex; justify-content: flex-end;">
        <div @click.stop style="width: 460px; max-width: 92vw; height: 100%; background: #fff; overflow: auto;">
            <div style="padding: 26px 30px; background: {{ $sel['teamColor'] }}; color: #fff; position: relative;">
                <button wire:click="closeDetail" style="position: absolute; top: 18px; right: 18px; width: 32px; height: 32px; border: none; border-radius: 9px; background: rgba(255,255,255,0.2); color: #fff; cursor: pointer; font-size: 18px;">×</button>
                <div style="display: flex; align-items: center; gap: 16px;">
                    <span style="display: inline-flex; width: 62px; height: 62px; border-radius: 50%; background: rgba(255,255,255,0.25); align-items: center; justify-content: center; font-size: 22px; font-weight: 700; font-family: 'Space Grotesk';">{{ $sel['initials'] }}</span>
                    <div><div style="font-size: 22px; font-weight: 700;">{{ $sel['name'] }}</div><div style="font-size: 13px; opacity: 0.85; font-family: 'Space Grotesk';">{{ $sel['empId'] }}</div></div>
                </div>
                <div style="display: flex; gap: 8px; margin-top: 16px; flex-wrap: wrap;">
                    <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 700; background: rgba(255,255,255,0.28); padding: 5px 12px; border-radius: 8px;">{{ $emp['operator'] }} {{ $L['e_belongs'] }}</span>
                    <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 600; background: rgba(255,255,255,0.2); padding: 5px 12px; border-radius: 8px;">{{ $sel['companyName'] }} · {{ $sel['teamName'] }}</span>
                    <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 600; background: rgba(255,255,255,0.2); padding: 5px 12px; border-radius: 8px;">{{ $sel['typeLabel'] }}</span>
                </div>
            </div>
            <div style="padding: 24px 30px;">
                @if(!empty($sel['badgePhoto']))
                    <div style="margin-bottom: 20px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                            <span style="font-size: 11.5px; color: #A7A49B;">{{ $L['b_faceCrop'] }}</span>
                            <button wire:click="rotateBadgePhoto" style="display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px; border: 1px solid #E4E2DB; border-radius: 8px; background: #fff; font-size: 11.5px; font-weight: 600; color: #5A5D64; cursor: pointer;">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>{{ $L['e_rotate'] }}
                            </button>
                        </div>
                        <div style="width: 100%; aspect-ratio: 1.58; border-radius: 12px; border: 1px solid #E4E2DB; background-color: #16181D; background-image: url('{{ $sel['badgePhoto'] }}'); background-size: contain; background-position: center; background-repeat: no-repeat;"></div>
                    </div>
                @endif
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px 16px;">
                    <label><span style="font-size: 11.5px; color: #A7A49B;">{{ $L['b_firstName'] }}</span><input wire:model="editForm.first" style="width:100%;margin-top:5px;padding:9px 11px;border:1px solid #E4E2DB;border-radius:9px;font-size:13.5px;background:#fff;color:#16181D;outline:none;"/></label>
                    <label><span style="font-size: 11.5px; color: #A7A49B;">{{ $L['b_lastName'] }}</span><input wire:model="editForm.last" style="width:100%;margin-top:5px;padding:9px 11px;border:1px solid #E4E2DB;border-radius:9px;font-size:13.5px;background:#fff;color:#16181D;outline:none;"/></label>
                    <label><span style="font-size: 11.5px; color: #A7A49B;">{{ $L['e_company'] }}</span><select wire:model="editForm.company" style="width:100%;margin-top:5px;padding:9px 11px;border:1px solid #E4E2DB;border-radius:9px;font-size:13.5px;background:#fff;color:#16181D;cursor:pointer;">@foreach($emp['companyOptions'] as $o)<option value="{{ $o['id'] }}">{{ $o['label'] }}</option>@endforeach</select></label>
                    <label><span style="font-size: 11.5px; color: #A7A49B;">{{ $L['e_team'] }}</span><select wire:model="editForm.team" style="width:100%;margin-top:5px;padding:9px 11px;border:1px solid #E4E2DB;border-radius:9px;font-size:13.5px;background:#fff;color:#16181D;cursor:pointer;">@foreach($emp['teamOptionsAll'] as $o)<option value="{{ $o['id'] }}">{{ $o['label'] }}</option>@endforeach</select></label>
                    <label><span style="font-size: 11.5px; color: #A7A49B;">{{ $L['e_role'] }}</span><input wire:model="editForm.role" style="width:100%;margin-top:5px;padding:9px 11px;border:1px solid #E4E2DB;border-radius:9px;font-size:13.5px;background:#fff;color:#16181D;outline:none;"/></label>
                    <label><span style="font-size: 11.5px; color: #A7A49B;">{{ $L['e_rate'] }} (USD/hr)</span><input wire:model="editForm.rate" type="number" style="width:100%;margin-top:5px;padding:9px 11px;border:1px solid #E4E2DB;border-radius:9px;font-size:13.5px;background:#fff;color:#16181D;outline:none;font-family:'Space Grotesk';"/></label>
                    <label><span style="font-size: 11.5px; color: #A7A49B;">{{ $L['b_type'] }}</span><select wire:model="editForm.type" style="width:100%;margin-top:5px;padding:9px 11px;border:1px solid #E4E2DB;border-radius:9px;font-size:13.5px;background:#fff;color:#16181D;cursor:pointer;">@foreach($emp['typeOptions'] as $o)<option value="{{ $o['id'] }}">{{ $o['label'] }}</option>@endforeach</select></label>
                    <label><span style="font-size: 11.5px; color: #A7A49B;">{{ $L['e_issued'] }}</span><input wire:model="editForm.issued" style="width:100%;margin-top:5px;padding:9px 11px;border:1px solid #E4E2DB;border-radius:9px;font-size:13.5px;background:#fff;color:#16181D;outline:none;font-family:'Space Grotesk';"/></label>
                    <label><span style="font-size: 11.5px; color: #A7A49B;">{{ $L['e_contact'] }}</span><input wire:model="editForm.phone" style="width:100%;margin-top:5px;padding:9px 11px;border:1px solid #E4E2DB;border-radius:9px;font-size:13.5px;background:#fff;color:#16181D;outline:none;"/></label>
                    <label><span style="font-size: 11.5px; color: #A7A49B;">Email</span><input wire:model="editForm.email" style="width:100%;margin-top:5px;padding:9px 11px;border:1px solid #E4E2DB;border-radius:9px;font-size:13.5px;background:#fff;color:#16181D;outline:none;"/></label>
                    <label><span style="font-size: 11.5px; color: #A7A49B;">{{ $L['e_nation'] }}</span><input wire:model="editForm.nat" style="width:100%;margin-top:5px;padding:9px 11px;border:1px solid #E4E2DB;border-radius:9px;font-size:13.5px;background:#fff;color:#16181D;outline:none;"/></label>
                    <div><div style="font-size: 11.5px; color: #A7A49B;">{{ $L['e_id'] }}</div><div style="margin-top: 5px; padding: 9px 11px; border: 1px dashed #E4E2DB; border-radius: 9px; font-size: 13px; font-family: 'Space Grotesk'; color: #E85D2A; background: #FAFAF8;">{{ $sel['empId'] }}</div></div>
                    @if(!empty($sel['badgeQr']))
                    <div><div style="font-size: 11.5px; color: #A7A49B;">{{ $L['e_qr'] }}</div><div style="margin-top: 5px; padding: 9px 11px; border: 1px dashed #E4E2DB; border-radius: 9px; font-size: 13px; font-family: 'Space Grotesk'; color: #16181D; background: #FAFAF8; word-break: break-all;">{{ $sel['badgeQr'] }}</div></div>
                    @endif
                    <div style="grid-column: span 2;"><div style="font-size: 11.5px; color: #A7A49B;">{{ $L['e_status'] }}</div><div style="margin-top: 5px;"><span style="display: inline-flex; font-size: 12.5px; font-weight: 600; color: {{ $sel['statusColor'] }}; background: {{ $sel['statusBg'] }}; padding: 5px 12px; border-radius: 8px;">{{ $sel['statusLabel'] }} · {{ $sel['inT'] }}</span></div></div>
                </div>
                <div style="margin-top: 22px; padding: 16px; border: 1px solid #F0EEE8; border-radius: 12px; background: #FAFAF8;">
                    <div style="font-size: 12px; font-weight: 700; color: #8A8880; margin-bottom: 10px;">{{ $L['e_accessNote'] }}</div>
                    <div style="display: flex; gap: 6px;">
                        <button wire:click="setFormAccess('admin')" style="{{ $Ui::accessSeg(($editForm['access'] ?? '')==='admin', $acc['admin']) }}">{{ $L['access_admin'] }}</button>
                        <button wire:click="setFormAccess('manager')" style="{{ $Ui::accessSeg(($editForm['access'] ?? '')==='manager', $acc['manager']) }}">{{ $L['access_manager'] }}</button>
                        <button wire:click="setFormAccess('worker')" style="{{ $Ui::accessSeg(($editForm['access'] ?? '')==='worker', $acc['worker']) }}">{{ $L['access_worker'] }}</button>
                    </div>
                </div>

                {{-- company involvement (NAHSHON staffing several clients) --}}
                <div style="margin-top: 22px; padding: 16px; border: 1px solid #F0EEE8; border-radius: 12px; background: #FAFAF8;">
                    <div style="font-size: 12px; font-weight: 700; color: #8A8880; margin-bottom: 12px;">{{ $L['e_involveNote'] }}</div>
                    @forelse($emp['assignments'] as $a)
                        <div style="display: flex; align-items: center; gap: 10px; padding: 9px 11px; background: #fff; border: 1px solid #F0EEE8; border-radius: 10px; margin-bottom: 8px;">
                            <span style="width: 9px; height: 9px; border-radius: 3px; background: {{ $a['teamColor'] }}; flex-shrink: 0;"></span>
                            <span style="flex: 1; font-size: 13px;"><span style="font-weight: 600;">{{ $a['company'] }}</span>@if($a['team'])<span style="color: #8A8880;"> · {{ $a['team'] }}</span>@endif</span>
                            <span style="font-size: 11.5px; font-weight: 600; color: #C0641F; background: #FDF0EA; padding: 3px 9px; border-radius: 7px;">{{ $a['relation'] }}</span>
                            <button wire:click="removeAssignment({{ $a['id'] }})" title="{{ $L['pj_delete'] }}" style="width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center; border: none; border-radius: 6px; background: #F4F3EF; color: #D9483B; font-size: 14px; cursor: pointer;">×</button>
                        </div>
                    @empty
                        <div style="font-size: 12px; color: #A7A49B; margin-bottom: 8px;">{{ $L['e_involveEmpty'] }}</div>
                    @endforelse
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 4px;">
                        <select wire:model.live="newAssignCompany" style="width: 100%; padding: 9px 10px; border: 1px solid #E4E2DB; border-radius: 9px; font-size: 12.5px; background: #fff; cursor: pointer;">
                            <option value="">{{ $L['e_involveCompany'] }}</option>
                            @foreach($emp['companyOptions'] as $o)<option value="{{ $o['id'] }}">{{ $o['label'] }}</option>@endforeach
                        </select>
                        <select wire:model="newAssignTeam" style="width: 100%; padding: 9px 10px; border: 1px solid #E4E2DB; border-radius: 9px; font-size: 12.5px; background: #fff; cursor: pointer;">
                            <option value="">{{ $L['e_involveTeam'] }}</option>
                            @foreach($emp['assignTeamOptions'] as $o)<option value="{{ $o['id'] }}">{{ $o['label'] }}</option>@endforeach
                        </select>
                    </div>
                    <div style="display: flex; gap: 8px; margin-top: 8px;">
                        <input wire:model="newAssignRelation" placeholder="{{ $L['e_involveRelation'] }}" style="flex: 1; min-width: 0; padding: 9px 10px; border: 1px solid #E4E2DB; border-radius: 9px; font-size: 12.5px; outline: none;"/>
                        <button wire:click="addAssignment" style="flex-shrink: 0; padding: 9px 18px; border: none; border-radius: 9px; background: #16181D; color: #fff; font-size: 12.5px; font-weight: 600; cursor: pointer; white-space: nowrap;">{{ $L['e_involveAdd'] }}</button>
                    </div>
                </div>
                @if($sel['isTerminated'])
                    <div style="margin-top: 14px; padding: 12px 14px; border-radius: 11px; background: #FBE9E7; color: #9B3A31; font-size: 13px; font-weight: 500;">{{ $L['e_termOn'] }} {{ $sel['term'] }}</div>
                @endif
                <button wire:click="saveEmp" style="width: 100%; margin-top: 22px; padding: 13px; border: none; border-radius: 12px; background: #E85D2A; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['e_save'] }}</button>
                <div style="display: flex; gap: 10px; margin-top: 10px;">
                    @if($sel['isActive'])
                        <button wire:click="askTerm({{ $sel['id'] }})" style="flex: 1; padding: 12px; border: 1px solid #FBF1DF; border-radius: 11px; background: #FBF1DF; color: #8A6A2E; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['e_terminate'] }}</button>
                    @endif
                    @if($sel['isTerminated'])
                        <button wire:click="reactivate({{ $sel['id'] }})" style="flex: 1; padding: 12px; border: 1px solid #E7F4EE; border-radius: 11px; background: #E7F4EE; color: #1F7A57; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['e_reactivate'] }}</button>
                    @endif
                    <button wire:click="askDelete({{ $sel['id'] }})" style="padding: 12px 18px; border: 1px solid #FBE9E7; border-radius: 11px; background: #FBE9E7; color: #D9483B; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['e_delete'] }}</button>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- delete modal --}}
@if($deleteId)
    <div style="position: fixed; inset: 0; z-index: 80; background: rgba(22,24,29,0.5); display: flex; align-items: center; justify-content: center; padding: 20px;">
        <div style="width: 380px; background: #fff; border-radius: 18px; padding: 28px; text-align: center;">
            <div style="width: 52px; height: 52px; border-radius: 50%; background: #FBE9E7; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#D9483B" stroke-width="2"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg></div>
            <div style="font-size: 15px; font-weight: 600; line-height: 1.5;">{{ $L['e_confirmDel'] }}</div>
            <div style="font-size: 13.5px; color: #8A8880; margin-top: 6px;">{{ $emp['delName'] }}</div>
            <div style="display: flex; gap: 10px; margin-top: 22px;">
                <button wire:click="cancelDelete" style="flex: 1; padding: 12px; border: 1px solid #E4E2DB; border-radius: 11px; background: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['e_cancel'] }}</button>
                <button wire:click="confirmDelete" style="flex: 1; padding: 12px; border: none; border-radius: 11px; background: #D9483B; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['e_delete'] }}</button>
            </div>
        </div>
    </div>
@endif

{{-- terminate modal --}}
@if($terminateId)
    <div style="position: fixed; inset: 0; z-index: 80; background: rgba(22,24,29,0.5); display: flex; align-items: center; justify-content: center; padding: 20px;">
        <div style="width: 400px; background: #fff; border-radius: 18px; padding: 28px; text-align: center;">
            <div style="width: 52px; height: 52px; border-radius: 50%; background: #FBF1DF; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#E8A33D" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg></div>
            <div style="font-size: 17px; font-weight: 700;">{{ $emp['termName'] }}</div>
            <div style="font-size: 13.5px; color: #8A8880; margin-top: 8px; line-height: 1.55;">{{ $L['e_confirmTerm'] }}</div>
            <div style="display: flex; gap: 10px; margin-top: 22px;">
                <button wire:click="cancelTerm" style="flex: 1; padding: 12px; border: 1px solid #E4E2DB; border-radius: 11px; background: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['e_cancel'] }}</button>
                <button wire:click="confirmTerm" style="flex: 1; padding: 12px; border: none; border-radius: 11px; background: #E8A33D; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer;">{{ $L['e_terminate'] }}</button>
            </div>
        </div>
    </div>
@endif
