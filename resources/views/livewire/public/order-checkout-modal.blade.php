<div>
    @if($showModal && !empty($dish))
        {{-- Fullscreen Overlay Backdrop with persistent key mapping --}}
        <div wire:key="order-checkout-modal-root" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 overflow-y-auto animate-in fade-in duration-200">

            {{-- Big-screen: left = dish + receiver, middle = dates + delivery window, right = finance only --}}
            <div class="bg-[#FDFBF7] rounded-[32px] shadow-2xl border border-amber-900/5 w-full max-w-6xl flex flex-col md:grid md:grid-cols-12 md:items-stretch text-amber-950 antialiased font-sans my-auto max-h-[90vh] overflow-y-auto md:overflow-hidden md:h-[min(90vh,880px)]">

                {{-- LEFT: dish, then receiver / address --}}
                <div class="w-full md:col-span-4 bg-[#F9F6F0] p-5 md:p-6 flex flex-col gap-4 border-b md:border-b-0 md:border-r border-amber-900/5 md:min-h-0 md:overflow-y-auto">
                    <div class="shrink-0">
                        <div class="flex items-center gap-2 mb-3 text-middo-orange font-bold text-lg">
                            <span class="text-xl">🍴</span> Middo
                        </div>
                        <h2 class="text-xl font-extrabold text-gray-800 uppercase tracking-wide mb-3">
                            Your Lunch Order
                        </h2>

                        <div class="rounded-2xl overflow-hidden bg-white shadow-sm border border-gray-100 p-3">
                            @if($dish['thumbnail'])
                                <img src="{{ asset($dish['thumbnail']) }}" alt="{{ $dish['name'] }}" class="w-full h-36 object-cover rounded-xl mb-3">
                            @else
                                <div class="w-full h-36 bg-gray-100 rounded-xl mb-3 flex items-center justify-center text-gray-400 font-medium">No Image Available</div>
                            @endif
                            <h3 class="text-lg font-bold text-gray-800 px-1">{{ $dish['name'] }}</h3>
                            <p class="text-sm font-medium text-gray-500 px-1 mt-1">Unit Price: <span class="text-gray-800 font-bold">৳{{ number_format($dish['price'], 2) }}</span></p>
                        </div>
                    </div>

                    <div class="shrink-0 space-y-3 pt-3 border-t border-amber-900/5">
                        <p class="text-xs font-black uppercase tracking-wider text-gray-400">Receiver and address</p>

                        <div class="space-y-3">
                            <div>
                                <label for="checkout-receiver-name" class="block text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-1.5">Desk receiver name</label>
                                <input id="checkout-receiver-name" wire:model.live="customerName" type="text" placeholder="Desk / contact person"
                                    class="w-full border-gray-200 bg-white rounded-xl text-sm px-3 py-2.5 shadow-sm font-semibold text-gray-800 focus:ring-blue-500 focus:border-blue-500"
                                    {{ $isConfirmingOtp ? 'disabled' : '' }}>
                                @error('customerName') <span class="text-red-500 text-xs mt-1.5 font-semibold block">{{ $message }}</span> @enderror
                                @if(auth()->check())
                                    <p class="text-[10px] text-gray-400 mt-1.5 leading-snug">
                                        Billing: <span class="font-bold text-gray-600">{{ auth()->user()->name ?? auth()->user()->first_name }}</span>
                                        @if(auth()->user()->company_name) — {{ auth()->user()->company_name }}@endif
                                    </p>
                                @endif
                            </div>

                            <div>
                                <label for="checkout-receiver-mobile" class="block text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-1.5">Mobile number</label>
                                <input id="checkout-receiver-mobile" wire:model="mobile" type="text" placeholder="e.g. 01710123456"
                                    class="w-full border-gray-200 bg-white rounded-xl text-sm px-3 py-2.5 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                    {{ $isConfirmingOtp ? 'disabled' : '' }}>
                                @error('mobile') <span class="text-red-500 text-xs mt-1.5 font-semibold block">{{ $message }}</span> @enderror
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div class="min-w-0">
                                    <label for="checkout-receiver-city" class="block text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-1.5">City</label>
                                    <select id="checkout-receiver-city" wire:model.live="city_id" class="w-full border-gray-200 bg-white rounded-xl text-sm px-3 py-2.5 shadow-sm focus:ring-blue-500 focus:border-blue-500" {{ $isConfirmingOtp ? 'disabled' : '' }}>
                                        @foreach($citiesList as $cityOption)
                                            <option value="{{ $cityOption['id'] }}">{{ $cityOption['name'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('city_id') <span class="text-red-500 text-xs font-semibold mt-1.5 block">{{ $message }}</span> @enderror
                                </div>

                                <div class="min-w-0">
                                    <label for="checkout-receiver-area" class="block text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-1.5">Area</label>
                                    <select id="checkout-receiver-area" wire:model.live="area_id" class="w-full border-gray-200 bg-white rounded-xl text-sm px-3 py-2.5 shadow-sm focus:ring-blue-500 focus:border-blue-500" {{ $isConfirmingOtp ? 'disabled' : '' }}>
                                        @if(count($areasList) === 0)
                                            <option value="">No areas available</option>
                                        @endif
                                        @foreach($areasList as $areaOption)
                                            <option value="{{ $areaOption['id'] }}">{{ $areaOption['name'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('area_id') <span class="text-red-500 text-xs font-semibold mt-1.5 block">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div>
                                <label for="checkout-receiver-address" class="block text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-1.5">Street address</label>
                                <input id="checkout-receiver-address" wire:model="addressLine1" type="text" placeholder="Building, Flat No., Street details"
                                    class="w-full border-gray-200 bg-white rounded-xl text-sm px-3 py-2.5 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                    {{ $isConfirmingOtp ? 'disabled' : '' }}>
                                @error('addressLine1') <span class="text-red-500 text-xs mt-1.5 block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- MIDDLE: date grid on top, delivery window pinned to bottom on desktop --}}
                <div class="w-full md:col-span-4 p-5 md:p-6 flex flex-col gap-3 bg-white border-b md:border-b-0 md:border-r border-amber-900/5 md:min-h-0 md:overflow-hidden">
                    <div class="flex-1 min-h-0 md:overflow-y-auto space-y-3 pr-0.5">
                        <div>
                            <h4 class="text-xs font-black uppercase tracking-wider text-gray-400 mb-2">Delivery Logistics</h4>
                            <label class="block text-base font-bold text-gray-800 mb-2">Order for Dates & Quantities:</label>

                            @if(!$isPastCutoff)
                                <div x-data="{
                                    timeLeft: '00h 00m 00s',
                                    init() {
                                        const updateTimer = () => {
                                            const now = new Date();
                                            const target = new Date();
                                            target.setHours({{ $cutoffHour }}, {{ $cutoffMinute }}, 0, 0);
                                            const diff = target.getTime() - now.getTime();
                                            if (diff <= 0) {
                                                this.timeLeft = 'Closed';
                                                $wire.call('loadOrderCheckout', { dishId: {{ $dish->id ?? 'null' }} });
                                                return;
                                            }
                                            const hrs = String(Math.floor(diff / 3600000)).padStart(2, '0');
                                            const mins = String(Math.floor((diff % 3600000) / 60000)).padStart(2, '0');
                                            const secs = String(Math.floor((diff % 60000) / 1000)).padStart(2, '0');
                                            this.timeLeft = `${hrs}h ${mins}m ${secs}s`;
                                        };
                                        updateTimer();
                                        const interval = setInterval(updateTimer, 1000);
                                        $cleanup(() => clearInterval(interval));
                                    }
                                }" class="mb-3 bg-amber-50/60 border border-amber-200/70 text-amber-900 rounded-2xl p-3 flex items-start gap-2.5 shadow-sm">
                                    <span class="text-base mt-0.5">⏳</span>
                                    <div class="text-xs">
                                        <p class="font-bold text-gray-800">Same-Day Ordering Is Open!</p>
                                        <p class="text-[11px] text-gray-500 mt-0.5">Today's lunch run closes in: <span class="font-mono text-middo-orange font-black" x-text="timeLeft">00h 00m 00s</span></p>
                                    </div>
                                </div>
                            @else
                                <div class="mb-3 bg-gray-100/80 border border-gray-200 rounded-2xl p-3 flex items-start gap-2.5">
                                    <span class="text-xs mt-0.5">🚫</span>
                                    <div class="text-[11px] text-gray-500 leading-tight">
                                        <p class="font-bold text-gray-700">Same-Day Cutoff Passed ({{ $this->cutoff_formatted }})</p>
                                        <p class="mt-0.5">Displaying next available Dhaka calendar routes.</p>
                                    </div>
                                </div>
                            @endif

                            <div class="bg-[#fcf8f2] border border-gray-200 rounded-xl p-1 shadow-inner grid grid-cols-3 divide-x divide-y divide-gray-200/80 text-center text-sm max-h-[240px] md:max-h-none overflow-y-auto">
                                @foreach($availableDates as $dateStr)
                                    @php
                                        $targetDate = Carbon\Carbon::parse($dateStr);
                                        $dateQty = $quantities[$dateStr] ?? 0;
                                        $isActive = ($dateQty > 0);
                                    @endphp
                                    <div
                                        wire:key="date-grid-card-{{ $dateStr }}"
                                        class="relative p-2 flex flex-col justify-between items-center transition-all duration-150 select-none border-t-0 border-l-0 border-gray-200/80 min-h-[100px]
                                            {{ $isActive ? 'bg-emerald-800 text-white shadow-sm' : 'bg-white text-gray-700 hover:bg-amber-50/20' }}"
                                    >
                                        <button
                                            type="button"
                                            wire:click="toggleDateSelection('{{ $dateStr }}')"
                                            class="w-full flex flex-col items-center focus:outline-none"
                                        >
                                            <span class="text-[10px] font-bold tracking-wide uppercase {{ $isActive ? 'text-emerald-200/90' : 'text-gray-400' }}">
                                                {{ $targetDate->format('M') }}
                                            </span>
                                            <span class="text-xl font-black my-0.5 {{ $isActive ? 'text-white' : 'text-gray-800' }}">
                                                {{ $targetDate->format('d') }}
                                            </span>
                                            <span class="text-[11px] font-medium tracking-wide lowercase block mb-1 {{ $isActive ? 'text-emerald-100/80' : 'text-gray-500' }}">
                                                {{ $targetDate->format('D') }}
                                            </span>
                                        </button>

                                        <div class="w-full max-w-[80px] h-6 mt-1 flex items-center justify-center">
                                            @if($isActive)
                                                <div class="flex items-center justify-between border border-emerald-700 rounded-lg bg-emerald-900/40 overflow-hidden w-full" wire:key="counter-{{ $dateStr }}">
                                                    <button type="button" wire:click="changeDateQuantity('{{ $dateStr }}', -1)" title="{{ $dateQty <= 1 ? 'Deselect date' : 'Decrease quantity' }}" class="px-2 py-0.5 hover:bg-emerald-700 text-white font-extrabold text-xs select-none transition">-</button>
                                                    <span class="text-xs font-black text-white px-1">{{ $dateQty }}</span>
                                                    <button type="button" wire:click="changeDateQuantity('{{ $dateStr }}', 1)" class="px-2 py-0.5 hover:bg-emerald-700 text-white font-extrabold text-xs select-none transition disabled:opacity-20" {{ $dateQty >= $this->remainingQtyForDate($dateStr) ? 'disabled' : '' }}>+</button>
                                                </div>
                                            @else
                                                <button type="button" wire:click="toggleDateSelection('{{ $dateStr }}')" class="text-[11px] font-bold text-amber-700/70 hover:text-amber-700 tracking-tight transition">Select</button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @error('quantities') <span class="text-red-500 text-xs mt-1 block font-semibold">{{ $message }}</span> @enderror
                            @error('selectedDate') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="shrink-0 pt-3 border-t border-gray-100 md:mt-auto">
                        <label class="block text-sm font-bold text-gray-800 mb-2">Delivery window</label>
                        <div class="space-y-1.5">
                            @foreach($deliveryWindows as $time)
                                @php
                                    $isTimeSelected = ($deliveryWindow === $time);
                                @endphp
                                <label
                                    wire:key="delivery-window-{{ Str::slug($time) }}"
                                    class="flex items-center gap-3 p-2.5 rounded-xl border cursor-pointer transition shadow-sm select-none
                                        {{ $isTimeSelected ? 'border-amber-800 bg-amber-900 text-white font-bold' : 'border-gray-200 bg-white hover:bg-gray-50 text-gray-800' }}"
                                >
                                    <input
                                        type="radio"
                                        name="delivery_window_option"
                                        value="{{ $time }}"
                                        wire:model.live="deliveryWindow"
                                        class="w-3.5 h-3.5 text-amber-900 focus:ring-amber-700 border-gray-300 {{ $isTimeSelected ? 'accent-white' : 'accent-amber-900' }}"
                                        {{ $isConfirmingOtp ? 'disabled' : '' }}
                                    >
                                    <span class="text-xs font-bold tracking-wide">Timeline - {{ $time }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- RIGHT: line items scroll; totals + payment + confirm stay fully visible (never nest payment in a clipped scroller) --}}
                <div class="w-full md:col-span-4 p-5 md:p-6 bg-[#FDFBF7] flex flex-col md:min-h-0 md:overflow-hidden">
                    <h4 class="shrink-0 text-xs font-black uppercase tracking-wider text-gray-400 mb-2">Order Summary</h4>

                    <div class="flex-1 min-h-0 overflow-y-auto space-y-1.5 text-xs text-gray-600 pr-1 mb-2" data-testid="checkout-line-items">
                        @foreach($quantities as $date => $qty)
                            @if($qty > 0)
                                <div class="flex justify-between items-center text-gray-700" wire:key="summary-row-{{ $date }}">
                                    <span class="font-medium truncate max-w-[170px]">{{ Carbon\Carbon::parse($date)->format('M d') }} ({{ strtolower(Carbon\Carbon::parse($date)->format('D')) }}): {{ $dish['name'] }} <b class="text-gray-900">[x{{ $qty }}]</b></span>
                                    <span class="font-bold text-gray-900">৳{{ number_format($dish['price'] * $qty, 2) }}</span>
                                </div>
                            @endif
                        @endforeach
                    </div>

                    <div class="shrink-0 space-y-2 pt-3 border-t border-gray-200/60 bg-[#FDFBF7]">
                        <div class="space-y-1 text-xs">
                            <div class="flex justify-between text-gray-500">
                                <span>Cumulative Subtotal:</span>
                                <span class="font-bold text-gray-900">৳{{ number_format($subtotal, 2) }}</span>
                            </div>
                            @forelse($chargeLines as $chargeLine)
                                <div class="flex justify-between text-gray-500" wire:key="fee-{{ $loop->index }}">
                                    <span>{{ $chargeLine['name'] }}:</span>
                                    <span class="font-bold text-gray-700">৳{{ number_format($chargeLine['amount'], 2) }}</span>
                                </div>
                            @empty
                                <div class="flex justify-between text-gray-400">
                                    <span>Charges:</span>
                                    <span class="font-bold text-gray-600">৳{{ number_format($taxesAndFees, 2) }}</span>
                                </div>
                            @endforelse
                            @if($couponDiscount > 0)
                                <div class="flex justify-between text-emerald-700">
                                    <span>Coupon ({{ $appliedCouponCode }}):</span>
                                    <span class="font-bold">−৳{{ number_format($couponDiscount) }}</span>
                                </div>
                            @endif
                            <div class="flex justify-between text-sm font-black text-gray-900 pt-1">
                                <span>TOTAL:</span>
                                <span class="text-base text-gray-900">৳{{ number_format($total, 2) }}</span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-1">Coupon code</label>
                            <div class="flex gap-2">
                                <input wire:model="couponCode" type="text" class="flex-1 border-gray-200 rounded-xl text-sm p-2 uppercase tracking-wider font-bold" placeholder="SAVE50" {{ $isConfirmingOtp ? 'disabled' : '' }}>
                                @if($appliedCouponCode !== '')
                                    <button type="button" wire:click="removeCoupon" class="px-3 py-2 rounded-xl border border-gray-200 text-[11px] font-black" {{ $isConfirmingOtp ? 'disabled' : '' }}>Remove</button>
                                @else
                                    <button type="button" wire:click="applyCoupon" class="px-3 py-2 rounded-xl bg-[#1E4630] text-white text-[11px] font-black" {{ $isConfirmingOtp ? 'disabled' : '' }}>Apply</button>
                                @endif
                            </div>
                            @error('couponCode') <span class="text-red-500 text-xs mt-1 font-semibold block">{{ $message }}</span> @enderror
                            @if($couponMessage)
                                <p class="text-[11px] font-semibold text-emerald-800 mt-1">{{ $couponMessage }}</p>
                            @endif
                        </div>

                        @if(!empty($prepayment['required']))
                            <div class="rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-2 text-[11px] text-amber-950 space-y-1">
                                <p class="font-bold">Prepayment required: ৳{{ number_format($prepayment['amount'] ?? 0) }}
                                    ({{ ($prepayment['ratio'] ?? 0) >= 1 ? '100%' : '50%' }}) — charged to your billing account</p>
                                <p>{{ $prepayment['message'] ?? '' }}</p>
                                <div class="flex items-center justify-between gap-2">
                                    <p>Middo Balance: <span class="font-bold">৳{{ number_format($prepayment['balance'] ?? 0) }}</span></p>
                                    @if(empty($prepayment['balance_sufficient']) || ($prepayment['balance'] ?? 0) < ($prepayment['amount'] ?? 0))
                                        <button type="button"
                                                @click="$dispatch('open-wallet-top-up-modal')"
                                                class="text-[10px] font-black text-white bg-middo-orange hover:bg-[#733614] px-2 py-1 rounded-lg transition shrink-0">
                                            + Add Money
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- Always-visible radio list (not a <select>); must stay outside any max-height scroller --}}
                        <div data-testid="checkout-payment-methods">
                            <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-1.5">Payment method</label>
                            <div
                                class="grid gap-1.5"
                                role="radiogroup"
                                aria-label="Payment method"
                                wire:key="payment-method-options-{{ count(array_filter($quantities, fn ($q) => $q > 0)) }}-{{ $this->codAllowed ? 'cod' : 'prepaid' }}"
                            >
                                @if($this->codAllowed)
                                    <label @class([
                                        'flex items-center gap-2.5 px-3 py-2.5 rounded-xl border cursor-pointer transition select-none',
                                        'border-emerald-700 bg-emerald-50 text-emerald-950 ring-1 ring-emerald-700/30' => $paymentMethod === 'cash_on_delivery',
                                        'border-gray-200 bg-white hover:bg-gray-50 text-gray-800' => $paymentMethod !== 'cash_on_delivery',
                                    ])>
                                        <input type="radio" name="checkout_payment_method" wire:model.live="paymentMethod" value="cash_on_delivery" class="shrink-0 w-4 h-4 text-emerald-800 focus:ring-emerald-700 border-gray-300" {{ $isConfirmingOtp ? 'disabled' : '' }}>
                                        <span class="min-w-0">
                                            <span class="block text-sm font-black leading-tight">Cash on Delivery</span>
                                            <span class="block text-[10px] font-medium text-gray-500 mt-0.5 leading-snug">Pay the rider on delivery. Available for up to {{ $this->codMaxActiveOrders }} meals.</span>
                                        </span>
                                    </label>
                                @endif

                                <label @class([
                                    'flex items-center gap-2.5 px-3 py-2.5 rounded-xl border transition select-none',
                                    'opacity-50 cursor-not-allowed border-gray-200 bg-gray-50 text-gray-500' => ! $this->balancePaymentAvailable,
                                    'cursor-pointer border-emerald-700 bg-emerald-50 text-emerald-950 ring-1 ring-emerald-700/30' => $this->balancePaymentAvailable && $paymentMethod === 'balance',
                                    'cursor-pointer border-gray-200 bg-white hover:bg-gray-50 text-gray-800' => $this->balancePaymentAvailable && $paymentMethod !== 'balance',
                                ])>
                                    <input type="radio" name="checkout_payment_method" wire:model.live="paymentMethod" value="balance" class="shrink-0 w-4 h-4 text-emerald-800 focus:ring-emerald-700 border-gray-300" {{ $isConfirmingOtp || ! $this->balancePaymentAvailable ? 'disabled' : '' }}>
                                    <span class="min-w-0">
                                        <span class="block text-sm font-black leading-tight">Middo Balance</span>
                                        @if(! $this->balancePaymentAvailable)
                                            <span class="block text-[10px] font-medium text-gray-500 mt-0.5 leading-snug">Unavailable — add money to your Middo wallet first.</span>
                                        @else
                                            <span class="block text-[10px] font-medium text-gray-500 mt-0.5 leading-snug">Wallet ৳{{ number_format($this->walletBalanceForDisplay) }}</span>
                                        @endif
                                    </span>
                                </label>

                                <label @class([
                                    'flex items-center gap-2.5 px-3 py-2.5 rounded-xl border cursor-pointer transition select-none',
                                    'border-emerald-700 bg-emerald-50 text-emerald-950 ring-1 ring-emerald-700/30' => $paymentMethod === 'gateway',
                                    'border-gray-200 bg-white hover:bg-gray-50 text-gray-800' => $paymentMethod !== 'gateway',
                                ])>
                                    <input type="radio" name="checkout_payment_method" wire:model.live="paymentMethod" value="gateway" class="shrink-0 w-4 h-4 text-emerald-800 focus:ring-emerald-700 border-gray-300" {{ $isConfirmingOtp ? 'disabled' : '' }}>
                                    <span class="min-w-0">
                                        <span class="block text-sm font-black leading-tight">Online payment</span>
                                        <span class="block text-[10px] font-medium text-gray-500 mt-0.5 leading-snug">Pay now by card or mobile banking.</span>
                                    </span>
                                </label>
                            </div>
                            @if(! $this->balancePaymentAvailable)
                                <p class="text-[10px] text-gray-500 mt-1">
                                    <button type="button" @click="$dispatch('open-wallet-top-up-modal')" class="font-bold text-middo-orange underline">Add money</button>
                                    to enable Middo Balance.
                                </p>
                            @endif
                            @error('paymentMethod') <span class="text-red-500 text-xs mt-1 font-semibold block">{{ $message }}</span> @enderror
                        </div>

                        @if(!$isConfirmingOtp)
                            <button
                                type="button"
                                wire:click="initiateOrderConfirmation"
                                wire:loading.attr="disabled"
                                class="w-full bg-middo-orange text-white py-3 rounded-xl font-bold hover:bg-amber-950 shadow-md transition text-sm tracking-wide"
                            >
                                <span wire:loading.remove wire:target="initiateOrderConfirmation">CONFIRM ORDER (Total: ৳{{ number_format($total, 2) }})</span>
                                <span wire:loading wire:target="initiateOrderConfirmation">Sending Confirmation SMS...</span>
                            </button>
                        @else
                            <div class="bg-amber-50/70 border border-amber-200 p-3 rounded-xl space-y-2.5 animate-in fade-in slide-in-from-bottom-2 duration-150" wire:key="checkout-confirmation-panel">
                                <div class="flex justify-between items-center gap-2">
                                    <p class="text-[11px] text-amber-950 font-bold">
                                        @if($paymentMethod === 'gateway' && $otpVerified)
                                            Payment step
                                        @else
                                            Verification SMS Sent! Enter 4-Digit Code:
                                        @endif
                                    </p>
                                    <button type="button" wire:click="cancelConfirmation" class="text-[10px] text-gray-400 hover:text-gray-600 underline font-semibold shrink-0">Change Info</button>
                                </div>

                                @if($paymentMethod === 'cash_on_delivery' && ! $otpVerified)
                                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-[11px] text-emerald-950">
                                        <p class="font-bold">Cash on Delivery selected</p>
                                        <p class="mt-0.5">Pay the rider when your meal arrives.</p>
                                    </div>
                                @endif

                                @if($paymentMethod === 'gateway' && $otpVerified)
                                    <div class="rounded-lg border border-amber-300 bg-white px-3 py-2.5 text-[11px] text-amber-950 space-y-2">
                                        <p class="font-bold">Code verified. Pay ৳{{ number_format(!empty($prepayment['required']) ? ($prepayment['amount'] ?? 0) : $total) }} online to place this order.</p>
                                        @if($gatewayPaymentUrl)
                                            <a
                                                href="{{ $gatewayPaymentUrl }}"
                                                target="_blank"
                                                rel="noopener"
                                                class="inline-flex w-full items-center justify-center rounded-xl bg-middo-orange px-3 py-2.5 text-sm font-black text-white hover:bg-amber-950 transition"
                                                data-testid="checkout-make-payment"
                                            >
                                                Make payment
                                            </a>
                                            <p class="text-[10px] text-gray-500 leading-snug">After paying, return here and confirm below.</p>
                                        @endif
                                    </div>

                                    <button type="button" wire:click="finalizeOrder" wire:loading.attr="disabled"
                                        class="w-full px-3 py-2.5 bg-emerald-800 text-white text-xs font-bold rounded-xl shadow-sm hover:bg-emerald-950 transition"
                                        data-testid="checkout-place-after-payment">
                                        <span wire:loading.remove wire:target="finalizeOrder">I've paid — Place Order</span>
                                        <span wire:loading wire:target="finalizeOrder">Processing Order...</span>
                                    </button>
                                @else
                                    <div class="flex gap-2">
                                        <input wire:model="otpInput" type="text" maxlength="4" placeholder="••••"
                                            class="w-24 border-gray-200 bg-white rounded-xl text-center text-sm font-bold tracking-widest p-2 shadow-sm focus:ring-emerald-500 focus:border-emerald-500">

                                        @if($paymentMethod === 'gateway')
                                            <button type="button" wire:click="verifyGatewayOtp" wire:loading.attr="disabled"
                                                class="flex-1 px-3 py-2 bg-emerald-800 text-white text-xs font-bold rounded-xl shadow-sm hover:bg-emerald-950 transition"
                                                data-testid="checkout-verify-otp">
                                                <span wire:loading.remove wire:target="verifyGatewayOtp">Verify code</span>
                                                <span wire:loading wire:target="verifyGatewayOtp">Verifying...</span>
                                            </button>
                                        @else
                                            <button type="button" wire:click="finalizeOrder" wire:loading.attr="disabled"
                                                class="flex-1 px-3 py-2 bg-emerald-800 text-white text-xs font-bold rounded-xl shadow-sm hover:bg-emerald-950 transition">
                                                <span wire:loading.remove wire:target="finalizeOrder">Verify & Place Order</span>
                                                <span wire:loading wire:target="finalizeOrder">Processing Order...</span>
                                            </button>
                                        @endif
                                    </div>
                                @endif

                                @error('otpInput') <span class="text-red-500 text-xs font-semibold block">{{ $message }}</span> @enderror
                                @error('paymentMethod') <span class="text-red-500 text-xs font-semibold block">{{ $message }}</span> @enderror
                            </div>
                        @endif

                        <button type="button" wire:click="$set('showModal', false)" class="w-full text-center text-xs font-bold text-gray-400 hover:text-gray-600 transition py-1">
                            Cancel & Close
                        </button>
                    </div>
                </div>

            </div>
        </div>
    @endif
</div>
