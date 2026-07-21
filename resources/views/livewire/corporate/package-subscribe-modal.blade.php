<div>
    @if($showModal && !empty($package))
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-[#FDFBF7] rounded-[28px] shadow-2xl border border-amber-900/5 w-full max-w-4xl my-auto max-h-[92vh] overflow-y-auto">
                <div class="p-5 md:p-6 border-b border-amber-900/5 flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-wider text-middo-orange">Build monthly package</p>
                        <h2 class="text-xl font-black text-gray-800 mt-0.5">{{ $package['name'] }}</h2>
                        <p class="text-sm font-semibold text-gray-500 mt-1">৳{{ number_format($package['price_per_day']) }}/day · prepaid · ops schedules dates</p>
                    </div>
                    <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-gray-700 font-bold text-lg leading-none">✕</button>
                </div>

                <div class="p-5 md:p-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="space-y-5">
                        <div>
                            <label class="block text-xs font-black uppercase tracking-wider text-gray-400 mb-2">Target month</label>
                            <select wire:model.live="targetMonth" class="w-full border-gray-200 rounded-xl text-sm p-2.5 bg-white" {{ $isConfirmingOtp ? 'disabled' : '' }}>
                                @foreach($monthOptions as $option)
                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <h3 class="text-xs font-black uppercase tracking-wider text-gray-400 mb-2">Omit weekdays / off-days</h3>
                            <p class="text-[11px] text-gray-500 mb-2">Checked days are skipped and never scheduled.</p>
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
                            <p class="text-[11px] text-gray-500 mt-2">
                                Available days this month: <span class="font-bold text-gray-700">{{ $quote['available_days'] ?? 0 }}</span>
                            </p>
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
                            <h3 class="text-xs font-black uppercase tracking-wider text-gray-400 mb-2">Menus & day counts</h3>
                            <p class="text-[11px] text-gray-500 mb-2">Choose which menus you want and how many days each this month.</p>
                            <div class="bg-white border border-gray-200 rounded-xl max-h-72 overflow-y-auto divide-y divide-gray-100">
                                @forelse($menuCatalog as $menu)
                                    @php $count = (int) ($menuDayCounts[$menu['id']] ?? 0); @endphp
                                    <div class="px-3 py-2.5 flex items-center gap-3" wire:key="menu-sel-{{ $menu['id'] }}">
                                        <div class="w-10 h-10 rounded-lg bg-[#F7F4EB] overflow-hidden shrink-0">
                                            @if($menu['thumbnail'])
                                                <img src="{{ $menu['thumbnail'] }}" alt="" class="w-full h-full object-cover">
                                            @endif
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="text-sm font-bold text-gray-800 truncate">{{ $menu['name'] }}</div>
                                        </div>
                                        <div class="inline-flex items-center gap-2 shrink-0">
                                            <button type="button" wire:click="changeMenuDays({{ $menu['id'] }}, -1)" class="w-7 h-7 rounded-lg border border-gray-200 font-black" {{ $isConfirmingOtp ? 'disabled' : '' }}>−</button>
                                            <span class="font-black w-6 text-center text-sm">{{ $count }}</span>
                                            <button type="button" wire:click="changeMenuDays({{ $menu['id'] }}, 1)" class="w-7 h-7 rounded-lg border border-gray-200 font-black" {{ $isConfirmingOtp ? 'disabled' : '' }}>+</button>
                                        </div>
                                    </div>
                                @empty
                                    <div class="px-3 py-4 text-xs text-gray-400">No menu items available.</div>
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

                        @if(!empty($quote['selections']))
                            <div class="bg-white border border-gray-200 rounded-xl p-3 space-y-1.5">
                                <p class="text-[11px] font-black uppercase tracking-wider text-gray-400">Your selection</p>
                                @foreach($quote['selections'] as $sel)
                                    <div class="flex justify-between text-xs font-semibold text-gray-700">
                                        <span>{{ $sel['menu_item_name'] }}</span>
                                        <span>{{ $sel['day_count'] }} days</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

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
                            <textarea wire:model="addressLine1" rows="2" class="w-full border-gray-200 rounded-xl text-sm p-2.5" {{ $isConfirmingOtp ? 'disabled' : '' }}></textarea>
                            @error('addressLine1') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Delivery window</label>
                            <select wire:model="deliveryWindow" class="w-full border-gray-200 rounded-xl text-sm p-2.5" {{ $isConfirmingOtp ? 'disabled' : '' }}>
                                @foreach($deliveryWindows as $window)
                                    <option value="{{ $window }}">{{ $window }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if($errorMessage)
                            <div class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">{{ $errorMessage }}</div>
                        @endif

                        @if(! $isConfirmingOtp)
                            <div class="flex flex-col gap-2">
                                <button type="button" wire:click="initiateConfirmation"
                                        class="w-full bg-middo-orange hover:bg-[#733614] text-white font-black text-xs uppercase tracking-wider py-3 rounded-xl transition">
                                    Confirm & pay with wallet
                                </button>
                                <button type="button" wire:click="startGatewayPayment"
                                        class="w-full border border-gray-200 bg-white text-gray-700 font-black text-xs uppercase tracking-wider py-3 rounded-xl">
                                    Pay online instead
                                </button>
                                @if($gatewayPaymentUrl)
                                    <a href="{{ $gatewayPaymentUrl }}" target="_blank" class="text-center text-xs font-bold text-middo-orange underline">
                                        Open payment page
                                    </a>
                                @endif
                            </div>
                        @else
                            <div class="space-y-3">
                                <p class="text-xs font-semibold text-gray-600">Enter the 4-digit OTP sent to {{ $mobile }}</p>
                                <input wire:model="otpInput" type="text" maxlength="4" class="w-full border-gray-200 rounded-xl text-sm p-2.5 tracking-[0.4em] text-center font-black" placeholder="••••">
                                @error('otpInput') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                <button type="button" wire:click="finalizeSubscribe"
                                        class="w-full bg-emerald-800 hover:bg-emerald-900 text-white font-black text-xs uppercase tracking-wider py-3 rounded-xl">
                                    Prepaid & create package
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
