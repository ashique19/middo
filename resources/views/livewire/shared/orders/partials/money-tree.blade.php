@php($tree = $moneyTree ?? null)
@if($tree)
    <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-5 mt-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold text-middo-dark">Money flow</h2>
                <p class="text-xs text-gray-500 mt-0.5">Billing → partner shares → cash / payment movements. Middo cash balance shown when cash moves.</p>
            </div>
            @if($this->accountsHubRoute())
                <a href="{{ $this->accountsHubRoute() }}" class="text-xs font-bold text-middo-orange hover:underline">Accounts hub →</a>
            @endif
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
            <div class="rounded-xl bg-gray-50 p-3">
                <p class="text-[10px] font-bold uppercase text-gray-400">Bill total</p>
                <p class="font-mono font-black text-gray-900">৳{{ number_format($tree['summary']['total'] ?? 0) }}</p>
            </div>
            <div class="rounded-xl bg-amber-50 p-3">
                <p class="text-[10px] font-bold uppercase text-amber-700/70">Kitchen share</p>
                <p class="font-mono font-black text-amber-900">৳{{ number_format($tree['summary']['kitchen_share'] ?? 0) }}</p>
            </div>
            <div class="rounded-xl bg-sky-50 p-3">
                <p class="text-[10px] font-bold uppercase text-sky-700/70">Delivery share</p>
                <p class="font-mono font-black text-sky-900">৳{{ number_format($tree['summary']['delivery_share'] ?? 0) }}</p>
            </div>
            <div class="rounded-xl bg-emerald-50 p-3">
                <p class="text-[10px] font-bold uppercase text-emerald-700/70">Middo rest</p>
                <p class="font-mono font-black text-emerald-900">৳{{ number_format($tree['summary']['middo_rest'] ?? 0) }}</p>
            </div>
            @if(($tree['summary']['vat'] ?? 0) > 0)
                <div class="rounded-xl bg-violet-50 p-3 col-span-2 md:col-span-1">
                    <p class="text-[10px] font-bold uppercase text-violet-700/70">VAT ({{ number_format($tree['summary']['vat_rate_pct'] ?? 0, 2) }}%)</p>
                    <p class="font-mono font-black text-violet-900">৳{{ number_format($tree['summary']['vat'] ?? 0) }}</p>
                </div>
            @endif
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div>
                <h3 class="text-xs font-black uppercase tracking-wider text-gray-400 mb-2">1. Billing</h3>
                <div class="space-y-2 border-l-2 border-gray-200 pl-3">
                    @forelse($tree['billing'] as $node)
                        <div>
                            <p class="text-sm font-semibold text-gray-800">{{ $node['description'] }}</p>
                            <p class="text-xs font-mono {{ ($node['amount'] ?? 0) >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                                {{ ($node['amount'] ?? 0) >= 0 ? '+' : '' }}৳{{ number_format($node['amount'] ?? 0) }}
                            </p>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400">No billing events.</p>
                    @endforelse
                    <div class="text-xs text-gray-500 pt-1">
                        Food ৳{{ number_format($tree['summary']['food'] ?? 0) }}
                        @if(($tree['summary']['vat'] ?? 0) > 0)
                            (ex-VAT ৳{{ number_format($tree['summary']['food_ex_vat'] ?? 0) }}
                            · VAT ৳{{ number_format($tree['summary']['vat']) }})
                        @endif
                        · Charges ৳{{ number_format($tree['summary']['charges'] ?? 0) }}
                        · Discount ৳{{ number_format($tree['summary']['discount'] ?? 0) }}
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-xs font-black uppercase tracking-wider text-gray-400 mb-2">2. Shares &amp; costs</h3>
                <div class="space-y-2 border-l-2 border-amber-200 pl-3">
                    @forelse($tree['shares'] as $node)
                        <div>
                            <p class="text-sm font-semibold text-gray-800">{{ $node['description'] }}</p>
                            <p class="text-xs font-mono {{ ($node['amount'] ?? 0) >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                                {{ ($node['amount'] ?? 0) >= 0 ? '+' : '' }}৳{{ number_format($node['amount'] ?? 0) }}
                            </p>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400">Shares accrue when order is delivered &amp; paid.</p>
                    @endforelse
                    @if(($tree['summary']['direct_cost'] ?? 0) > 0)
                        <p class="text-[11px] text-gray-500">Direct cost (menu COGS memo): ৳{{ number_format($tree['summary']['direct_cost']) }}</p>
                    @endif
                </div>
            </div>

            <div>
                <h3 class="text-xs font-black uppercase tracking-wider text-gray-400 mb-2">3. Money movement</h3>
                <div class="space-y-2 border-l-2 border-emerald-200 pl-3">
                    @forelse($tree['movements'] as $node)
                        <div>
                            <p class="text-sm font-semibold text-gray-800">{{ $node['description'] }}</p>
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-xs font-mono {{ ($node['amount'] ?? 0) >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                                    {{ ($node['amount'] ?? 0) >= 0 ? '+' : '' }}৳{{ number_format($node['amount'] ?? 0) }}
                                    @if(!empty($node['channel']))
                                        <span class="text-gray-400 font-sans">· {{ $node['channel'] }}</span>
                                    @endif
                                </p>
                                @if(($node['middo_cash_balance_after'] ?? null) !== null)
                                    <p class="text-[10px] text-gray-400 whitespace-nowrap">Cash → ৳{{ number_format($node['middo_cash_balance_after']) }}</p>
                                @endif
                            </div>
                            @if(!empty($node['at']))
                                <p class="text-[10px] text-gray-400">{{ $node['at'] }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-gray-400">No payment / cash movements yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        @if(!empty($tree['payables']))
            <div class="border-t border-gray-100 pt-4">
                <h3 class="text-xs font-black uppercase tracking-wider text-gray-400 mb-2">Partner payables</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($tree['payables'] as $payable)
                        <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 px-3 py-1 text-xs font-semibold text-gray-700">
                            {{ ucfirst($payable['role']) }} ৳{{ number_format($payable['amount']) }}
                            <span class="text-[10px] uppercase text-gray-400">{{ $payable['status'] }}</span>
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        <p class="text-[11px] text-gray-400">Current Middo cash balance: ৳{{ number_format($tree['middo_cash_balance'] ?? 0) }}</p>
    </div>
@endif
