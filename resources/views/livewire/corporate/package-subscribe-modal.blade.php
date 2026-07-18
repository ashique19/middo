<div>
    @if($showModal && !empty($package))
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-[#FDFBF7] rounded-[28px] shadow-2xl border border-amber-900/5 w-full max-w-3xl my-auto max-h-[92vh] overflow-y-auto">
                <div class="p-5 md:p-6 border-b border-amber-900/5 flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-wider text-middo-orange">Prepaid package</p>
                        <h2 class="text-xl font-black text-gray-800 mt-0.5">{{ $package['name'] }}</h2>
                        <p class="text-sm font-semibold text-gray-500 mt-1">৳{{ number_format($package['price_per_day']) }}/day · full prepaid</p>
                    </div>
                    <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-gray-700 font-bold text-lg leading-none">✕</button>
                </div>

                <div class="p-5 md:p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-5">
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-wider text-gray-400 mb-2">Omit weekdays</h3>
                            <p class="text-[11px] text-gray-500 mb-2">Checked days are skipped and not billed.</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach(\App\Support\PackageBilling::WEEKDAY_LABELS as $dow => $label)
                                    @php $omitted = in_array($dow, $omittedWeekdays, true); @endphp
                                    <button type="button" wire:click="toggleWeekday({{ $dow }})"
                                        @class([
                                            'px-3 py-2 rounded-xl text-xs font-black border transition',
                                            'bg-gray-200 text-gray-500 border-gray-300 line-through' => $omitted,
                                            'bg-emerald-800 text-white border-emerald-900' => ! $omitted,
                                        ])
                                        {{ $isConfirmingOtp ? 'disabled' : '' }}>
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-black uppercase tracking-wider text-gray-400 mb-2">Quantity / day</label>
                            <div class="inline-flex items-center gap-3 bg-white border border-gray-200 rounded-xl px-3 py-2">
                                <button type="button" wire:click="changeQuantity(-1)" class="font-black text-lg" {{ $isConfirmingOtp ? 'disabled' : '' }}>−</button>
                                <span class="font-black w-6 text-center">{{ $quantity }}</span>
                                <button type="button" wire:click="changeQuantity(1)" class="font-black text-lg" {{ $isConfirmingOtp ? 'disabled' : '' }}>+</button>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-xs font-black uppercase tracking-wider text-gray-400 mb-2">Billable days</h3>
                            <div class="bg-white border border-gray-200 rounded-xl max-h-48 overflow-y-auto divide-y divide-gray-100">
                                @forelse(($quote['days'] ?? []) as $day)
                                    <div class="px-3 py-2 flex items-center justify-between text-xs">
                                        <span class="font-semibold text-gray-700">
                                            {{ \Carbon\Carbon::parse($day['date'])->format('D, M d') }}
                                            <span class="text-gray-400 font-medium">· {{ $day['menu_item_name'] }}</span>
                                        </span>
                                        <span class="font-bold">৳{{ number_format($day['line_total']) }}</span>
                                    </div>
                                @empty
                                    <div class="px-3 py-4 text-xs text-gray-400">No billable days with current omit settings.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="bg-[#1E4630] text-white rounded-2xl p-4">
                            <div class="text-[11px] font-bold uppercase tracking-wider text-emerald-200/80">Total due now</div>
                            <div class="text-3xl font-black mt-1">৳{{ number_format($quote['total_amount'] ?? 0) }}</div>
                            <div class="text-xs font-semibold text-emerald-100/80 mt-1">
                                {{ $quote['billable_days'] ?? 0 }} days × ৳{{ number_format($quote['price_per_day'] ?? 0) }} × qty {{ $quantity }}
                            </div>
                            <div class="text-[11px] mt-2 text-emerald-100/70">Wallet: ৳{{ number_format($walletBalance) }}</div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Receiver name</label>
                            <input wire:model="customerName" type="text" class="w-full border-gray-200 rounded-xl text-sm p-2.5" {{ $isConfirmingOtp ? 'disabled' : '' }}>
                            @error('customerName') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Mobile</label>
                            <input wire:model="mobile" type="text" class="w-full border-gray-200 rounded-xl text-sm p-2.5" {{ $isConfirmingOtp ? 'disabled' : '' }}>
                            @error('mobile') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">City</label>
                                <select wire:model.live="city_id" class="w-full border-gray-200 rounded-xl text-sm p-2.5" {{ $isConfirmingOtp ? 'disabled' : '' }}>
                                    @foreach($citiesList as $cityOption)
                                        <option value="{{ $cityOption['id'] }}">{{ $cityOption['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Area</label>
                                <select wire:model="area_id" class="w-full border-gray-200 rounded-xl text-sm p-2.5" {{ $isConfirmingOtp ? 'disabled' : '' }}>
                                    @foreach($areasList as $areaOption)
                                        <option value="{{ $areaOption['id'] }}">{{ $areaOption['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Address</label>
                            <input wire:model="addressLine1" type="text" class="w-full border-gray-200 rounded-xl text-sm p-2.5" {{ $isConfirmingOtp ? 'disabled' : '' }}>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Delivery window</label>
                            <select wire:model="deliveryWindow" class="w-full border-gray-200 rounded-xl text-sm p-2.5" {{ $isConfirmingOtp ? 'disabled' : '' }}>
                                @foreach($deliveryWindows as $window)
                                    <option value="{{ $window }}">{{ $window }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Pay with</label>
                            <select wire:model="paymentMethod" class="w-full border-gray-200 rounded-xl text-sm p-2.5" {{ $isConfirmingOtp ? 'disabled' : '' }}>
                                <option value="balance">Middo Balance</option>
                                <option value="gateway">Online payment</option>
                            </select>
                        </div>

                        @if($errorMessage)
                            <div class="rounded-xl border border-red-200 bg-red-50 text-red-800 text-xs font-semibold px-3 py-2">{{ $errorMessage }}</div>
                        @endif

                        @if(!$isConfirmingOtp)
                            <button type="button" wire:click="initiateConfirmation" wire:loading.attr="disabled"
                                    class="w-full bg-middo-orange hover:bg-[#733614] text-white font-black text-sm py-3.5 rounded-xl shadow-md">
                                Confirm & send OTP · ৳{{ number_format($quote['total_amount'] ?? 0) }}
                            </button>
                        @else
                            <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 space-y-2">
                                <p class="text-[11px] font-bold text-amber-950">Enter the 4-digit OTP sent to your mobile.</p>
                                @if($paymentMethod === 'gateway')
                                    <button type="button" wire:click="startGatewayPayment" class="text-[11px] font-bold text-middo-orange underline">
                                        {{ $gatewayPaymentUrl ? 'Open / refresh payment page' : 'Start online payment' }}
                                    </button>
                                    @if($gatewayPaymentUrl)
                                        <a href="{{ $gatewayPaymentUrl }}" target="_blank" rel="noopener" class="block text-[11px] font-bold text-middo-orange break-all">{{ $gatewayPaymentUrl }}</a>
                                    @endif
                                @endif
                                <div class="flex gap-2">
                                    <input wire:model="otpInput" type="text" maxlength="4" placeholder="••••"
                                           class="w-24 border-gray-200 rounded-xl text-center font-bold tracking-widest p-2">
                                    <button type="button" wire:click="finalizeSubscribe" wire:loading.attr="disabled"
                                            class="flex-1 bg-emerald-800 hover:bg-emerald-950 text-white text-xs font-bold rounded-xl">
                                        Pay & activate package
                                    </button>
                                </div>
                                @error('otpInput') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                <button type="button" wire:click="$set('isConfirmingOtp', false)" class="text-[10px] text-gray-400 underline">Change details</button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
