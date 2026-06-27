<div>
    @if($showModal && !empty($dish))
        {{-- Fullscreen Overlay Backdrop with persistent key mapping --}}
        <div wire:key="order-checkout-modal-root" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 overflow-y-auto animate-in fade-in duration-200">
            
            {{-- Main Dashboard Card Layout Container --}}
            <div class="bg-[#FDFBF7] rounded-[32px] shadow-2xl border border-amber-900/5 w-full max-w-5xl flex flex-col md:grid md:grid-cols-12 text-amber-950 antialiased font-sans my-auto max-h-[90vh] overflow-y-auto">                
                
                {{-- LEFT COLUMN: Dish Snapshot --}}
                <div class="w-full md:col-span-4 bg-[#F9F6F0] p-6 flex flex-col justify-between border-b md:border-b-0 md:border-r border-amber-900/5">
                    <div>
                        <div class="flex items-center gap-2 mb-4 text-middo-orange font-bold text-lg">
                            <span class="text-xl">🍴</span> Middo
                        </div>
                        <h2 class="text-xl font-extrabold text-gray-800 uppercase tracking-wide mb-4">
                            Your Lunch Order
                        </h2>
                        
                        <div class="rounded-2xl overflow-hidden bg-white shadow-sm border border-gray-100 p-3">
                            @if($dish['thumbnail'])
                                <img src="{{ asset($dish['thumbnail']) }}" alt="{{ $dish['name'] }}" class="w-full h-48 object-cover rounded-xl mb-3">
                            @else
                                <div class="w-full h-48 bg-gray-100 rounded-xl mb-3 flex items-center justify-center text-gray-400 font-medium">No Image Available</div>
                            @endif
                            <h3 class="text-lg font-bold text-gray-800 px-1">{{ $dish['name'] }}</h3>
                        </div>
                    </div>

                    <div class="mt-6 bg-white rounded-xl p-4 border border-gray-100 shadow-sm">
                        <p class="text-sm font-medium text-gray-500">Unit Price: <span class="text-gray-800 font-bold">৳{{ number_format($dish['price'], 2) }}</span></p>
                        <p class="text-[11px] text-gray-400 mt-1">Select and adjust individual quantities in the calendar dates.</p>
                    </div>
                </div>

                {{-- =========================================================================
                    CENTER COLUMN: Delivery Logistics (Dynamic Cutoff & Timelines)
                    ========================================================================= --}}
                <div class="w-full md:col-span-4 p-6 flex flex-col justify-between bg-white border-b md:border-b-0 md:border-r border-amber-900/5">
                    <div>
                        <h4 class="text-xs font-black uppercase tracking-wider text-gray-400 mb-3">Delivery Logistics</h4>
                        <label class="block text-base font-bold text-gray-800 mb-2">Order for Dates & Quantities:</label>
                        
                        {{-- Real-Time Same-Day Order Countdown Banner --}}
                        @if(!$isPastCutoff)
                            <div x-data="{ 
                                timeLeft: '00h 00m 00s',
                                init() {
                                    const updateTimer = () => {
                                        const now = new Date();
                                        
                                        // Create target deadline explicitly specifying local year, month, date, and cutoff configurations
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

                        {{-- Dynamic Multi-Select Grid Structure --}}
                        <div class="bg-[#fcf8f2] border border-gray-200 rounded-xl p-1 shadow-inner grid grid-cols-3 divide-x divide-y divide-gray-200/80 text-center text-sm max-h-[260px] overflow-y-auto">
                            @foreach($availableDates as $dateStr)
                                @php 
                                    $targetDate = Carbon\Carbon::parse($dateStr); 
                                    $dateQty = $quantities[$dateStr] ?? 0;
                                    $isActive = ($dateQty > 0);
                                @endphp
                                <div 
                                    wire:key="date-grid-card-{{ $dateStr }}"
                                    class="relative p-2.5 flex flex-col justify-between items-center transition-all duration-150 select-none border-t-0 border-l-0 border-gray-200/80 min-h-[120px]
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
                                                <button type="button" wire:click="changeDateQuantity('{{ $dateStr }}', -1)" class="px-2 py-0.5 hover:bg-emerald-700 text-white font-extrabold text-xs select-none transition disabled:opacity-20" {{ $dateQty <= 1 ? 'disabled' : '' }}>-</button>
                                                <span class="text-xs font-black text-white px-1">{{ $dateQty }}</span>
                                                <button type="button" wire:click="changeDateQuantity('{{ $dateStr }}', 1)" class="px-2 py-0.5 hover:bg-emerald-700 text-white font-extrabold text-xs select-none transition disabled:opacity-20" {{ $dateQty >= 5 ? 'disabled' : '' }}>+</button>
                                            </div>
                                        @else
                                            <button type="button" wire:click="toggleDateSelection('{{ $dateStr }}')" class="text-[11px] font-bold text-amber-700/70 hover:text-amber-700 tracking-tight transition">Select</button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @error('selectedDate') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    {{-- Timeline Selectors --}}
                    <div>
                        <label class="block text-base font-bold text-gray-800 mb-1">Delivery window</label>
                        <div class="mb-2 text-amber-800">
                            <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="1" y="3" width="15" height="13" rx="2" ry="2" />
                                <polygon points="16 8 20 8 23 11 23 16 16 16 16 8" />
                                <circle cx="5.5" cy="18.5" r="2.5" />
                                <circle cx="18.5" cy="18.5" r="2.5" />
                            </svg>
                        </div>

                        {{-- Timeline Selectors Section --}}
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

                {{-- =========================================================================
                     RIGHT COLUMN: Customer Details, Invoicing Summary, & Logistics Address
                     ========================================================================= --}}
                <div class="w-full md:col-span-4 p-6 bg-[#FDFBF7] flex flex-col justify-between">
                    <div>
                        <h4 class="text-xs font-black uppercase tracking-wider text-gray-400 mb-3">Order Summary & Customer Info</h4>
                        
                        {{-- Corporate Mini User Badge --}}
                        <div class="bg-white rounded-2xl p-3 border border-gray-100 flex items-center gap-3 shadow-sm mb-3">
                            <div class="w-10 h-10 rounded-full bg-middo-orange/10 border border-middo-orange/20 text-middo-orange flex items-center justify-center font-bold text-sm uppercase shadow-inner overflow-hidden shrink-0">
                                {{ substr(auth()->user()?->name ?? $customerName, 0, 2) }}
                            </div>
                            <div>
                                <p class="text-[11px] text-gray-400 leading-none font-medium">Order for:</p>
                                <p class="text-base font-extrabold text-gray-800 mt-1">{{ auth()->user()?->name ?? $customerName }}</p>
                            </div>
                        </div>

                        {{-- Calculation Bill Table Matrix showing multi-date breakouts --}}
                        <div class="space-y-1.5 text-xs text-gray-600 border-b border-gray-200/80 pb-3 mb-3 max-h-[120px] overflow-y-auto pr-1">
                            @foreach($quantities as $date => $qty)
                                @if($qty > 0)
                                    <div class="flex justify-between items-center text-gray-700" wire:key="summary-row-{{ $date }}">
                                        <span class="font-medium truncate max-w-[170px]">{{ Carbon\Carbon::parse($date)->format('M d') }} ({{ strtolower(Carbon\Carbon::parse($date)->format('D')) }}): {{ $dish['name'] }} <b class="text-gray-900">[x{{ $qty }}]</b></span>
                                        <span class="font-bold text-gray-900">৳{{ number_format($dish['price'] * $qty, 2) }}</span>
                                    </div>
                                @endif
                            @endforeach
                            
                            <div class="pt-2 border-t border-dashed border-gray-200 space-y-1">
                                <div class="flex justify-between text-gray-500">
                                    <span>Cumulative Subtotal:</span>
                                    <span class="font-bold text-gray-900">৳{{ number_format($subtotal, 2) }}</span>
                                </div>
                                <div class="flex justify-between text-gray-400">
                                    <span>Taxes & Fees:</span>
                                    <span class="font-bold text-gray-600">৳{{ number_format($taxesAndFees, 2) }}</span>
                                </div>
                                <div class="flex justify-between text-sm font-black text-gray-900 pt-1">
                                    <span>TOTAL:</span>
                                    <span class="text-base text-gray-900">৳{{ number_format($total, 2) }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- Delivery Logistics Address Segment --}}
                        <div class="space-y-3">
                            <label class="block text-xs font-black uppercase tracking-wider text-gray-400 mb-1">Delivery Logistics & Info</label>
                            
                            <div>
                                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-1">Mobile Number</label>
                                <input wire:model="mobile" type="text" placeholder="e.g. 01710123456" 
                                    class="w-full border-gray-200 bg-white rounded-xl text-sm p-2.5 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                    {{ $isConfirmingOtp ? 'disabled' : '' }}>
                                @error('mobile') <span class="text-red-500 text-xs mt-1 font-semibold block">{{ $message }}</span> @enderror
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-1">City</label>
                                    <select wire:model.live="city_id" class="w-full border-gray-200 bg-white rounded-xl text-sm p-2.5 shadow-sm focus:ring-blue-500 focus:border-blue-500" {{ $isConfirmingOtp ? 'disabled' : '' }}>
                                        @foreach($citiesList as $cityOption)
                                            <option value="{{ $cityOption['id'] }}">{{ $cityOption['name'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('city_id') <span class="text-red-500 text-xs font-semibold mt-0.5 block">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-1">Area</label>
                                    <select wire:model="area_id" class="w-full border-gray-200 bg-white rounded-xl text-sm p-2.5 shadow-sm focus:ring-blue-500 focus:border-blue-500" {{ $isConfirmingOtp ? 'disabled' : '' }}>
                                        @if(count($areasList) === 0)
                                            <option value="">No areas available</option>
                                        @endif
                                        @foreach($areasList as $areaOption)
                                            <option value="{{ $areaOption['id'] }}">{{ $areaOption['name'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('area_id') <span class="text-red-500 text-xs font-semibold mt-0.5 block">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-1">Street Address</label>
                                <input wire:model="addressLine1" type="text" placeholder="Building, Flat No., Street details" class="w-full border-gray-200 bg-white rounded-xl text-sm p-2.5 shadow-sm focus:ring-blue-500 focus:border-blue-500" {{ $isConfirmingOtp ? 'disabled' : '' }}>
                                @error('addressLine1') <span class="text-red-500 text-xs mt-0.5 block">{{ $message }}</span> @enderror
                            </div>
                        </div>


                    </div>

                    {{-- PLACE THE CODE BLOCK DIRECTLY INSIDE THIS ACTION CONTROLS SECTION --}}
                    <div class="space-y-2 pt-2 border-t border-gray-200/60">
                        
                        @if(!$isConfirmingOtp)
                            {{-- Normal State: Submit Details & Request Order OTP Token --}}
                            <button 
                                type="button" 
                                wire:click="initiateOrderConfirmation" 
                                wire:loading.attr="disabled"
                                class="w-full bg-middo-orange text-white py-3.5 rounded-xl font-bold hover:bg-amber-950 shadow-md transition text-sm tracking-wide"
                            >
                                <span wire:loading.remove wire:target="initiateOrderConfirmation">CONFIRM ORDER (Total: ৳{{ number_format($total, 2) }})</span>
                                <span wire:loading wire:target="initiateOrderConfirmation">Sending Confirmation SMS...</span>
                            </button>
                        @else
                            {{-- Confirmation State: Prompt entry panel to execute transactional creation --}}
                            <div class="bg-amber-50/70 border border-amber-200 p-3 rounded-xl space-y-2 mb-2 animate-in fade-in slide-in-from-bottom-2 duration-150" wire:key="checkout-confirmation-panel">
                                <div class="flex justify-between items-center">
                                    <p class="text-[11px] text-amber-950 font-bold">Verification SMS Sent! Enter 4-Digit Code:</p>
                                    <button type="button" wire:click="$set('isConfirmingOtp', false)" class="text-[10px] text-gray-400 hover:text-gray-600 underline font-semibold">Change Info</button>
                                </div>
                                
                                <div class="flex gap-2">
                                    <input wire:model="otpInput" type="text" maxlength="4" placeholder="••••" 
                                        class="w-24 border-gray-200 bg-white rounded-xl text-center text-sm font-bold tracking-widest p-2 shadow-sm focus:ring-emerald-500 focus:border-emerald-500">
                                    
                                    <button type="button" wire:click="finalizeOrder" wire:loading.attr="disabled"
                                        class="flex-1 px-3 py-2 bg-emerald-800 text-white text-xs font-bold rounded-xl shadow-sm hover:bg-emerald-950 transition">
                                        <span wire:loading.remove wire:target="finalizeOrder">Verify & Place Order</span>
                                        <span wire:loading wire:target="finalizeOrder">Processing Order...</span>
                                    </button>
                                </div>
                                @error('otpInput') <span class="text-red-500 text-xs font-semibold block mt-0.5">{{ $message }}</span> @enderror
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