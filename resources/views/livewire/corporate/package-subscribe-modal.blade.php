<div>
    @if($showModal && !empty($package))
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-[#FDFBF7] rounded-[28px] shadow-2xl border border-amber-900/5 w-full max-w-4xl my-auto max-h-[92vh] overflow-y-auto">
                <div class="p-5 md:p-6 border-b border-amber-900/5 flex items-start justify-between gap-3 sticky top-0 bg-[#FDFBF7] z-10">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-wider text-middo-orange">Build monthly package</p>
                        <h2 class="text-xl font-black text-gray-800 mt-0.5">{{ $package['name'] }}</h2>
                        <p class="text-sm font-semibold text-gray-500 mt-1">৳{{ number_format($package['price_per_day']) }}/day · prepaid · ops schedules dates</p>
                    </div>
                    <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-gray-700 font-bold text-lg leading-none">✕</button>
                </div>

                <div class="px-5 md:px-6 pt-4 space-y-3 sticky top-[76px] bg-[#FDFBF7] z-10 pb-3 border-b border-amber-900/5">
                    <div @class([
                        'rounded-xl border px-4 py-3 text-sm font-semibold',
                        'border-emerald-200 bg-emerald-50 text-emerald-900' => $fillsMonth,
                        'border-amber-200 bg-amber-50 text-amber-900' => ! $fillsMonth,
                    ])>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span>
                                Selected <span class="font-black">{{ $selectedDays }}</span>
                                /
                                <span class="font-black">{{ $workingDays }}</span>
                                working days
                            </span>
                            <span class="text-xs font-black uppercase tracking-wider">
                                @if($fillsMonth)
                                    Month filled
                                @else
                                    Fill every working day
                                @endif
                            </span>
                        </div>
                        <div class="mt-2 h-2 rounded-full bg-white/70 overflow-hidden">
                            @php
                                $pct = $workingDays > 0 ? min(100, (int) round(($selectedDays / $workingDays) * 100)) : 0;
                            @endphp
                            <div class="h-full rounded-full {{ $fillsMonth ? 'bg-emerald-600' : 'bg-amber-500' }}" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>

                    @if($errorMessage)
                        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800" wire:key="pkg-error">
                            {{ $errorMessage }}
                        </div>
                    @endif

                    @if($statusMessage)
                        <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm font-semibold text-sky-900" wire:key="pkg-status">
                            {{ $statusMessage }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">
                            <ul class="list-disc pl-4 space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
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
                            <p class="text-[11px] text-gray-500 mb-2">Total must equal all {{ $workingDays }} working days this month.</p>
                            <div class="bg-white border border-gray-200 rounded-xl max-h-72 overflow-y-auto divide-y divide-gray-100">
                                @forelse($menuCatalog as $menu)
                                    @php
                                        $count = (int) ($menuDayCounts[(string) $menu['id']] ?? 0);
                                        $atCapacity = $workingDays > 0 && $selectedDays >= $workingDays;
                                        $canIncrease = ! $isConfirmingOtp && ! $atCapacity;
                                    @endphp
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
                                            <button type="button" wire:click="changeMenuDays({{ $menu['id'] }}, -1)" wire:loading.attr="disabled" class="w-7 h-7 rounded-lg border border-gray-200 font-black disabled:opacity-40" {{ $isConfirmingOtp || $count < 1 ? 'disabled' : '' }}>−</button>
                                            <span class="font-black w-6 text-center text-sm">{{ $count }}</span>
                                            <button type="button" wire:click="changeMenuDays({{ $menu['id'] }}, 1)" wire:loading.attr="disabled" class="w-7 h-7 rounded-lg border border-gray-200 font-black disabled:opacity-40" {{ $canIncrease ? '' : 'disabled' }}>+</button>
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
                                {{ $selectedDays }} days × ৳{{ number_format($quote['price_per_day'] ?? ($package['price_per_day'] ?? 0)) }} × qty {{ $quantity }}
                            </div>
                            <div class="text-[11px] mt-2 text-emerald-100/70">Wallet: ৳{{ number_format($walletBalance) }}</div>
                            <div class="text-[11px] mt-1 {{ $fillsMonth ? 'text-emerald-200' : 'text-amber-200' }}">
                                {{ $selectedDays }} / {{ $workingDays }} working days
                                @if($fillsMonth)
                                    · ready
                                @elseif($selectedDays > $workingDays)
                                    · too many days
                                @else
                                    · incomplete
                                @endif
                            </div>
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
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Mobile</label>
                            <input wire:model="mobile" type="text" class="w-full border-gray-200 rounded-xl text-sm p-2.5" {{ $isConfirmingOtp ? 'disabled' : '' }}>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">City</label>
                                <select wire:model.live="city_id" class="w-full border-gray-200 rounded-xl text-sm p-2.5" {{ $isConfirmingOtp ? 'disabled' : '' }}>
                                    <option value="">Select city</option>
                                    @foreach($citiesList as $cityOption)
                                        <option value="{{ $cityOption['id'] }}">{{ $cityOption['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Area</label>
                                <select wire:model="area_id" class="w-full border-gray-200 rounded-xl text-sm p-2.5" {{ $isConfirmingOtp ? 'disabled' : '' }}>
                                    <option value="">Select area</option>
                                    @foreach($areasList as $areaOption)
                                        <option value="{{ $areaOption['id'] }}">{{ $areaOption['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Address</label>
                            <textarea wire:model="addressLine1" rows="2" class="w-full border-gray-200 rounded-xl text-sm p-2.5" {{ $isConfirmingOtp ? 'disabled' : '' }}></textarea>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Delivery window</label>
                            <select wire:model="deliveryWindow" class="w-full border-gray-200 rounded-xl text-sm p-2.5" {{ $isConfirmingOtp ? 'disabled' : '' }}>
                                @foreach($deliveryWindows as $window)
                                    <option value="{{ $window }}">{{ $window }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if(! $isConfirmingOtp)
                            @php
                                $totalDue = (int) ($quote['total_amount'] ?? 0);
                                $canPayWithWallet = $walletBalance > 0 && $walletBalance >= $totalDue && $totalDue > 0;
                            @endphp
                            <div class="flex flex-col gap-2">
                                @if($canPayWithWallet)
                                    <button type="button" wire:click="payWithWallet" wire:loading.attr="disabled"
                                            class="w-full bg-middo-orange hover:bg-[#733614] text-white font-black text-xs uppercase tracking-wider py-3 rounded-xl transition disabled:opacity-60">
                                        <span wire:loading.remove wire:target="payWithWallet">Pay with Middo wallet</span>
                                        <span wire:loading wire:target="payWithWallet">Checking…</span>
                                    </button>
                                    <button type="button" wire:click="startGatewayPayment" wire:loading.attr="disabled"
                                            class="w-full border border-gray-200 bg-white text-gray-700 font-black text-xs uppercase tracking-wider py-3 rounded-xl disabled:opacity-60">
                                        Pay online
                                    </button>
                                @else
                                    <button type="button" wire:click="startGatewayPayment" wire:loading.attr="disabled"
                                            class="w-full bg-middo-orange hover:bg-[#733614] text-white font-black text-xs uppercase tracking-wider py-3 rounded-xl transition disabled:opacity-60">
                                        <span wire:loading.remove wire:target="startGatewayPayment">Pay online</span>
                                        <span wire:loading wire:target="startGatewayPayment">Starting…</span>
                                    </button>
                                @endif
                                @if($gatewayPaymentUrl)
                                    <a href="{{ $gatewayPaymentUrl }}" target="_blank" class="text-center text-xs font-bold text-middo-orange underline">
                                        Open payment page
                                    </a>
                                @endif
                            </div>
                        @else
                            <div class="space-y-3">
                                <p class="text-xs font-semibold text-gray-600">Enter the 4-digit OTP sent to {{ $mobile }}</p>
                                @if($gatewayPaymentUrl)
                                    <a href="{{ $gatewayPaymentUrl }}" target="_blank" class="block text-center text-xs font-bold text-middo-orange underline">
                                        Open payment page
                                    </a>
                                @endif
                                @if($debugOtp)
                                    <p class="text-xs font-black text-emerald-800 bg-emerald-50 border border-emerald-200 rounded-xl px-3 py-2">
                                        Debug OTP: {{ $debugOtp }}
                                    </p>
                                @endif
                                <input wire:model.live="otpInput" type="text" inputmode="numeric" maxlength="4" class="w-full border-gray-200 rounded-xl text-sm p-2.5 tracking-[0.4em] text-center font-black" placeholder="••••">
                                @error('otpInput')
                                    <p class="text-xs font-semibold text-red-600">{{ $message }}</p>
                                @enderror
                                <button type="button" wire:click="finalizeSubscribe" wire:loading.attr="disabled"
                                        class="w-full bg-emerald-800 hover:bg-emerald-900 text-white font-black text-xs uppercase tracking-wider py-3 rounded-xl disabled:opacity-60">
                                    <span wire:loading.remove wire:target="finalizeSubscribe">Prepaid & create package</span>
                                    <span wire:loading wire:target="finalizeSubscribe">Creating…</span>
                                </button>
                                <button type="button" wire:click="resendOtp" class="w-full text-xs font-bold text-middo-orange underline">
                                    Resend OTP
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
