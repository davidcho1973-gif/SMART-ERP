<div style="min-height: 100vh; background: linear-gradient(180deg,#EDECE7,#E6E4DD); color: #16181D; font-family: 'Space Grotesk', ui-sans-serif, system-ui, sans-serif; display: flex; align-items: flex-start; justify-content: center; padding: 28px 16px 60px;">
    <div style="width: 100%; max-width: 440px;">

        {{-- brand --}}
        <div style="display: flex; align-items: center; gap: 10px; justify-content: center; margin-bottom: 18px;">
            <img src="{{ asset('images/nahshon-mark.svg') }}" alt="NAHSHON MEP" style="width: 34px; height: 34px; display: block;"/>
            <span style="font-family: 'Space Grotesk'; font-size: 17px; font-weight: 700;">NAHSHON <span style="color: #E5403E;">MEP</span></span>
        </div>

        @if($invalid)
            <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 18px; padding: 34px 26px; text-align: center;">
                <div style="font-size: 34px;">🔒</div>
                <div style="font-size: 18px; font-weight: 800; margin-top: 8px;">{{ $L['j_invalid'] }}</div>
                <div style="font-size: 13px; color: #8A8880; margin-top: 6px;">{{ $L['j_invalidSub'] }}</div>
            </div>
        @elseif($submitted)
            <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 18px; padding: 40px 26px; text-align: center;">
                <div style="width: 66px; height: 66px; border-radius: 50%; background: #E7F4EE; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#1F9D6B" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg>
                </div>
                <div style="font-size: 20px; font-weight: 800;">{{ $L['j_doneT'] }}</div>
                <div style="font-size: 13.5px; color: #5A5D64; margin-top: 8px; line-height: 1.6;">{{ $L['j_doneSub'] }}</div>
                <div style="display: inline-block; margin-top: 18px; font-size: 12px; font-weight: 700; color: #C0641F; background: #FBF1DF; padding: 6px 14px; border-radius: 20px;">⏳ {{ $L['j_pending'] }}</div>
            </div>
        @else
            {{-- language --}}
            <div style="display: flex; gap: 6px; margin-bottom: 12px;">
                @foreach(['en' => 'English', 'es' => 'Español', 'ko' => '한국어'] as $code => $label)
                    <button wire:click="setLang('{{ $code }}')" style="flex: 1; padding: 9px 0; border-radius: 10px; border: 1px solid {{ $lang === $code ? '#E85D2A' : '#E4E2DB' }}; background: {{ $lang === $code ? '#E85D2A' : '#fff' }}; color: {{ $lang === $code ? '#fff' : '#5A5D64' }}; font-size: 12.5px; font-weight: 700; cursor: pointer;">{{ $label }}</button>
                @endforeach
            </div>

            <div style="background: #fff; border: 1px solid #E4E2DB; border-radius: 18px; padding: 22px 20px 26px;">
                <div style="font-size: 19px; font-weight: 800;">{{ $L['j_title'] }}</div>
                <div style="font-size: 12.5px; color: #8A8880; margin: 4px 0 18px;">{{ $siteName }} · {{ $L['j_sub'] }}</div>

                <div style="display: flex; gap: 10px;">
                    <label style="flex: 1;"><span style="font-size: 11.5px; color: #8A8880;">{{ $L['j_first'] }}</span>
                        <input wire:model="first" style="width:100%;margin-top:5px;padding:11px 12px;border:1px solid #E4E2DB;border-radius:11px;font-size:14px;outline:none;"/></label>
                    <label style="flex: 1;"><span style="font-size: 11.5px; color: #8A8880;">{{ $L['j_last'] }}</span>
                        <input wire:model="last" style="width:100%;margin-top:5px;padding:11px 12px;border:1px solid #E4E2DB;border-radius:11px;font-size:14px;outline:none;"/></label>
                </div>
                @error('first')<div style="color:#C0392B;font-size:11.5px;margin-top:5px;">{{ $message }}</div>@enderror

                <label style="display: block; margin-top: 11px;"><span style="font-size: 11.5px; color: #8A8880;">{{ $L['j_phone'] }}</span>
                    <input wire:model="phone" inputmode="tel" style="width:100%;margin-top:5px;padding:11px 12px;border:1px solid #E4E2DB;border-radius:11px;font-size:14px;outline:none;"/></label>

                <label style="display: block; margin-top: 11px;"><span style="font-size: 11.5px; color: #8A8880;">{{ $L['j_email'] }}</span>
                    <input wire:model="email" type="email" placeholder="name@email.com" style="width:100%;margin-top:5px;padding:11px 12px;border:1px solid #E4E2DB;border-radius:11px;font-size:14px;outline:none;"/></label>
                <div style="font-size: 11px; color: #A7A49B; margin-top: 4px;">{{ $L['j_emailHint'] }}</div>
                @error('email')<div style="color:#C0392B;font-size:11.5px;margin-top:5px;">{{ $message }}</div>@enderror

                {{-- password + confirm, right under email --}}
                <label style="display: block; margin-top: 11px;"><span style="font-size: 11.5px; color: #8A8880;">{{ $L['j_password'] }}</span>
                    <input wire:model="password" type="password" autocomplete="new-password" style="width:100%;margin-top:5px;padding:11px 12px;border:1px solid #E4E2DB;border-radius:11px;font-size:14px;outline:none;"/></label>
                <div style="font-size: 11px; color: #A7A49B; margin-top: 4px;">{{ $L['j_pwHint'] }}</div>
                @error('password')<div style="color:#C0392B;font-size:11.5px;margin-top:5px;">{{ $message }}</div>@enderror

                <label style="display: block; margin-top: 11px;"><span style="font-size: 11.5px; color: #8A8880;">{{ $L['j_pwConfirm'] }}</span>
                    <input wire:model="passwordConfirm" type="password" autocomplete="new-password" style="width:100%;margin-top:5px;padding:11px 12px;border:1px solid #E4E2DB;border-radius:11px;font-size:14px;outline:none;"/></label>
                @error('passwordConfirm')<div style="color:#C0392B;font-size:11.5px;margin-top:5px;">{{ $message }}</div>@enderror

                {{-- selfie --}}
                <div style="margin-top: 14px;">
                    <span style="font-size: 11.5px; color: #8A8880;">{{ $L['j_selfie'] }}</span>
                    <div x-data="{
                            read(e){ const f = e.target.files[0]; if(!f) return; const r = new FileReader();
                                r.onload = () => @this.set('selfie', r.result); r.readAsDataURL(f); }
                        }" style="margin-top: 5px;">
                        @if($selfie)
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <img src="{{ $selfie }}" style="width: 62px; height: 62px; border-radius: 12px; object-fit: cover; border: 1px solid #E4E2DB;"/>
                                <label style="font-size: 12.5px; font-weight: 700; color: #3B72E0; cursor: pointer;">{{ $L['j_selfieRetake'] }}
                                    <input type="file" accept="image/*" capture="user" x-on:change="read($event)" style="display: none;"/></label>
                            </div>
                        @else
                            <label style="display: block; border: 1.5px dashed #CBDBF5; background: #F6F9FE; border-radius: 12px; padding: 18px; text-align: center; color: #3B72E0; font-size: 13px; font-weight: 700; cursor: pointer;">
                                📷 {{ $L['j_selfieHint'] }}
                                <input type="file" accept="image/*" capture="user" x-on:change="read($event)" style="display: none;"/>
                            </label>
                        @endif
                    </div>
                </div>

                <label style="display: block; margin-top: 14px;"><span style="font-size: 11.5px; color: #8A8880;">{{ $L['j_trade'] }}</span>
                    <input wire:model="trade" placeholder="{{ $L['j_tradePh'] }}" style="width:100%;margin-top:5px;padding:11px 12px;border:1px solid #E4E2DB;border-radius:11px;font-size:14px;outline:none;"/></label>

                <button wire:click="submit" wire:loading.attr="disabled" style="width: 100%; margin-top: 20px; padding: 14px; border: none; border-radius: 13px; background: #E85D2A; color: #fff; font-size: 15px; font-weight: 700; cursor: pointer;">{{ $L['j_submit'] }}</button>
                <div style="font-size: 11px; color: #A7A49B; text-align: center; margin-top: 12px; line-height: 1.5;">{{ $L['j_footer'] }}</div>
            </div>
        @endif
    </div>
</div>
