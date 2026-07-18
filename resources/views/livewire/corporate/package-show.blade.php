<div class="min-h-screen bg-[#F7F4EB] text-[#2B1A11] antialiased font-sans p-4 md:p-8">
    <div class="max-w-[900px] mx-auto space-y-6">
        <div>
            <a href="{{ route('corporates.packages.index') }}" class="text-xs font-bold text-middo-orange hover:underline">← All packages</a>
            <h1 class="text-3xl font-black tracking-tight mt-1">{{ $subscription['name'] }}</h1>
            <p class="text-sm font-semibold text-[#635347] mt-0.5">
                {{ \Carbon\Carbon::parse($subscription['start_date'])->format('M d') }} – {{ \Carbon\Carbon::parse($subscription['end_date'])->format('M d, Y') }}
                · qty {{ $subscription['quantity'] }} · {{ $subscription['status'] }}
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="bg-[#1E4630] text-white rounded-2xl p-4">
                <div class="text-[11px] font-bold uppercase text-emerald-200/70">Paid</div>
                <div class="text-2xl font-black mt-1">৳{{ number_format($subscription['amount_paid']) }}</div>
            </div>
            <div class="bg-white border border-[#DDD3BE] rounded-2xl p-4">
                <div class="text-[11px] font-bold uppercase text-[#635347]">Billable days</div>
                <div class="text-2xl font-black mt-1">{{ $subscription['billable_days'] }}</div>
            </div>
            <div class="bg-white border border-[#DDD3BE] rounded-2xl p-4">
                <div class="text-[11px] font-bold uppercase text-[#635347]">৳ / day</div>
                <div class="text-2xl font-black mt-1 text-middo-orange">{{ number_format($subscription['price_per_day']) }}</div>
            </div>
        </div>

        <div class="bg-white border border-[#DDD3BE] rounded-2xl p-4 text-sm">
            <div class="font-bold">{{ $subscription['receiver_name'] }}</div>
            <div class="text-[#635347] mt-0.5">{{ $subscription['address'] }}</div>
            <div class="text-[#635347] mt-0.5">Window: {{ $subscription['delivery_time'] }}</div>
            <p class="text-[11px] text-gray-500 mt-3">
                Skip any pending day before the order cutoff and the day amount is credited back to your Middo Balance.
            </p>
        </div>

        @if($errorMessage)
            <div class="rounded-xl border border-red-200 bg-red-50 text-red-800 text-sm font-semibold px-4 py-3">{{ $errorMessage }}</div>
        @endif
        @if($successMessage)
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-900 text-sm font-semibold px-4 py-3">{{ $successMessage }}</div>
        @endif

        <div class="bg-white border border-[#DDD3BE] rounded-2xl overflow-hidden">
            <div class="px-4 py-3 border-b border-[#EFE9DC] text-xs font-black uppercase tracking-wider text-[#635347]">
                Delivery calendar
            </div>
            <div class="divide-y divide-[#F0EBE0]">
                @foreach($days as $day)
                    <div wire:key="sub-day-{{ $day['id'] }}" class="px-4 py-3 flex items-center gap-3">
                        @if($day['thumbnail'])
                            <img src="{{ $day['thumbnail'] }}" alt="" class="w-12 h-12 rounded-xl object-cover border border-gray-100 shrink-0">
                        @else
                            <div class="w-12 h-12 rounded-xl bg-[#F7F4EB] shrink-0"></div>
                        @endif
                        <div class="flex-1 min-w-0">
                            <div class="font-bold text-sm truncate">{{ $day['menu_name'] }}</div>
                            <div class="text-xs text-[#635347]">
                                {{ $day['weekday'] }}, {{ \Carbon\Carbon::parse($day['date'])->format('M d, Y') }}
                                · x{{ $day['quantity'] }} · ৳{{ number_format($day['total_amount']) }}
                            </div>
                        </div>
                        <div class="shrink-0 text-right">
                            @if($day['order_status'] === 'cancelled')
                                <span class="text-[10px] font-black uppercase text-gray-400">Skipped</span>
                            @elseif($day['can_skip'])
                                <button type="button"
                                        wire:click="skipDay({{ $day['id'] }})"
                                        wire:confirm="Skip this day? ৳{{ number_format($day['amount_paid']) }} will be credited to your wallet."
                                        class="text-[11px] font-black uppercase tracking-wider text-middo-orange hover:underline">
                                    Skip day
                                </button>
                            @else
                                <span class="text-[10px] font-black uppercase text-emerald-800">{{ str_replace('_', ' ', $day['order_status']) }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
