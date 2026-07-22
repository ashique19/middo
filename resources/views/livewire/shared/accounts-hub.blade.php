<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-middo-dark">Accounts</h1>
            <p class="text-sm text-gray-500 mt-1">Money flow across orders — revenue, shares, cash, and Middo balance.</p>
        </div>
        <a href="{{ route($orderShowRoutePrefix.'.middo-cash') }}" class="text-sm font-bold text-middo-orange hover:underline">Middo cash ledger →</a>
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-900">{{ $statusMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-900">{{ $errorMessage }}</div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Middo cash on hand</p>
            <p class="mt-2 text-3xl font-black text-middo-dark">৳{{ number_format($middoCash) }}</p>
            <p class="mt-1 text-xs text-gray-500">Physical cash after rider handovers & settlements</p>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Open kitchen payables</p>
            <p class="mt-2 text-3xl font-black text-amber-800">৳{{ number_format($openKitchen) }}</p>
            <p class="mt-1 text-xs text-gray-500">Accrued kitchen commission not yet settled</p>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Open delivery payables</p>
            <p class="mt-2 text-3xl font-black text-sky-800">৳{{ number_format($openDelivery) }}</p>
            <p class="mt-1 text-xs text-gray-500">Accrued delivery commission not yet settled</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="rounded-2xl border border-gray-100 bg-white shadow-sm overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-gray-50">
                <h2 class="text-lg font-bold text-middo-dark">Partner payables</h2>
                <select wire:model.live="payableFilter" class="text-xs font-semibold border-gray-200 rounded-lg">
                    <option value="open">Open</option>
                    <option value="settled">Settled</option>
                    <option value="void">Void</option>
                    <option value="all">All</option>
                </select>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($payables as $payable)
                    <div class="px-5 py-3 flex items-start justify-between gap-3" wire:key="payable-{{ $payable->id }}">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-gray-900">
                                {{ ucfirst($payable->beneficiary_role) }} · ৳{{ number_format($payable->amount) }}
                                <span class="ml-1 text-[10px] uppercase font-bold text-gray-400">{{ $payable->status }}</span>
                            </p>
                            <p class="text-xs text-gray-500 truncate">
                                Order
                                <a href="{{ route($orderShowRoutePrefix.'.orders.show', $payable->order_id) }}" class="font-semibold text-middo-orange hover:underline">#{{ $payable->order_id }}</a>
                                · {{ $payable->order?->menuItem?->name ?? '—' }}
                                @if($payable->beneficiary)
                                    · {{ trim(($payable->beneficiary->first_name ?? '').' '.($payable->beneficiary->last_name ?? '')) }}
                                @endif
                            </p>
                        </div>
                        @if($payable->status === 'open')
                            <button type="button"
                                    wire:click="settlePayable({{ $payable->id }})"
                                    wire:confirm="Pay ৳{{ number_format($payable->amount) }} from Middo cash for this {{ $payable->beneficiary_role }} share?"
                                    class="shrink-0 rounded-lg bg-middo-orange px-3 py-1.5 text-[11px] font-black uppercase text-white hover:bg-[#733614]">
                                Settle
                            </button>
                        @endif
                    </div>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-gray-400">No payables in this filter.</p>
                @endforelse
            </div>
            @if($payables->hasPages())
                <div class="p-4">{{ $payables->links() }}</div>
            @endif
        </div>

        <div class="rounded-2xl border border-gray-100 bg-white shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-50">
                <h2 class="text-lg font-bold text-middo-dark">Recent money events</h2>
            </div>
            <div class="divide-y divide-gray-50 max-h-[480px] overflow-y-auto">
                @forelse($recentEvents as $event)
                    <div class="px-5 py-3" wire:key="event-{{ $event->id }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ $event->description ?: str_replace('_', ' ', $event->event_type) }}</p>
                                <p class="text-xs text-gray-500">
                                    <a href="{{ route($orderShowRoutePrefix.'.orders.show', $event->order_id) }}" class="font-semibold text-middo-orange hover:underline">Order #{{ $event->order_id }}</a>
                                    · {{ str_replace('_', ' ', $event->bucket) }}
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-mono font-bold {{ $event->amount >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                                    {{ $event->amount >= 0 ? '+' : '' }}৳{{ number_format($event->amount) }}
                                </p>
                                @if($event->middo_cash_balance_after !== null)
                                    <p class="text-[10px] text-gray-400">Cash ৳{{ number_format($event->middo_cash_balance_after) }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-gray-400">No money events yet. They appear as orders are placed, paid, delivered, and settled.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-100 bg-white shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-50">
            <h2 class="text-lg font-bold text-middo-dark">Orders with money activity</h2>
            <p class="text-xs text-gray-500 mt-0.5">Open an order to see the full flow tree (billing → shares → movements).</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[720px]">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                    <tr>
                        <th class="p-3 text-left">Order</th>
                        <th class="p-3 text-left">Corporate</th>
                        <th class="p-3 text-right">Total</th>
                        <th class="p-3 text-right">Kitchen</th>
                        <th class="p-3 text-right">Delivery</th>
                        <th class="p-3 text-right">Middo rest</th>
                        <th class="p-3 text-right">Cash</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($recentOrders as $order)
                        <tr class="hover:bg-gray-50/70">
                            <td class="p-3">
                                <a href="{{ route($orderShowRoutePrefix.'.orders.show', $order) }}" class="font-mono font-bold text-middo-orange hover:underline">#{{ $order->id }}</a>
                                <div class="text-xs text-gray-500">{{ $order->menuItem?->name }}</div>
                            </td>
                            <td class="p-3 text-gray-700">
                                {{ $order->user?->company_name ?: trim(($order->user?->first_name.' '.$order->user?->last_name)) }}
                            </td>
                            <td class="p-3 text-right font-mono">৳{{ number_format((int) $order->total_amount) }}</td>
                            <td class="p-3 text-right font-mono">৳{{ number_format((int) $order->kitchen_share_amount) }}</td>
                            <td class="p-3 text-right font-mono">৳{{ number_format((int) $order->delivery_share_amount) }}</td>
                            <td class="p-3 text-right font-mono font-semibold">৳{{ number_format((int) $order->middo_rest_amount) }}</td>
                            <td class="p-3 text-right font-mono">৳{{ number_format((int) $order->cash_collected) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-10 text-center text-gray-400">No orders yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50/80 p-5 space-y-2 text-sm text-gray-600">
        <p class="font-bold text-gray-800">Also manage here (roadmap)</p>
        <ul class="list-disc pl-5 space-y-1 text-xs">
            <li>Bank / EPS ledger separate from Middo cash (online collections, top-ups, payouts)</li>
            <li>Coupon & discount audit tied to each order’s billing branch</li>
            <li>Period P&amp;L (day/week/month) and kitchen settlement batches</li>
            <li>Rider wage vs cash-float separation (today rider balance = cash liability only)</li>
            <li>VAT/tax lines and supplier / packaging purchase costs</li>
            <li>Corporate credit / invoice aging for billed accounts</li>
        </ul>
    </div>
</div>
