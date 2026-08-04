<div class="max-w-7xl mx-auto py-10 px-6 space-y-6">
    <div class="space-y-1">
        <a href="{{ route('delivery.dashboard') }}" class="text-sm font-semibold text-middo-orange hover:underline">← Dashboard</a>
        <h1 class="text-3xl font-bold text-middo-dark">Cash</h1>
        <p class="text-sm font-semibold text-gray-500">
            Hand over Due to Middo only (Collection − Commission). Keep commission from the bag.
        </p>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <div class="rounded-2xl border border-gray-100 bg-white px-5 py-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Due to Middo</p>
            <p class="mt-1 text-2xl font-black text-middo-orange">৳{{ number_format($dueBalance) }}</p>
            <p class="mt-1 text-xs text-gray-500">Cash to hand over</p>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white px-5 py-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Wallet — Middo owes you</p>
            <p class="mt-1 text-2xl font-black text-middo-dark">৳{{ number_format($walletOwed) }}</p>
            <p class="mt-1 text-xs text-gray-500">
                @if($walletOwed > 0 && $dueBalance === 0)
                    <a href="{{ route('delivery.account') }}" class="font-semibold text-middo-orange hover:underline">Request payment →</a>
                @else
                    Prepaid / unsettled commissions
                @endif
            </p>
        </div>
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ $statusMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errorMessage }}</div>
    @endif

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-bold text-middo-dark">Eligible Due handovers</h2>
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-sm font-semibold text-gray-600">Selected Due: ৳{{ number_format($selectedTotal) }}</span>
                <button
                    type="button"
                    wire:click="createHandover"
                    wire:loading.attr="disabled"
                    class="inline-flex px-4 py-2 rounded-xl bg-middo-orange text-white text-sm font-bold disabled:opacity-60">
                    Create handover
                </button>
            </div>
        </div>
        <div class="px-5 py-3 border-b border-gray-100 flex flex-wrap gap-4 text-sm">
            <label class="inline-flex items-center gap-2 font-semibold text-gray-700">
                <input type="radio" wire:model="target" value="kitchen" class="text-middo-orange focus:ring-middo-orange">
                Hand to kitchen
            </label>
            <label class="inline-flex items-center gap-2 font-semibold text-gray-700">
                <input type="radio" wire:model="target" value="middo" class="text-middo-orange focus:ring-middo-orange">
                Hand to Middo / ops
            </label>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left min-w-[720px]">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                    <tr>
                        <th class="p-4">Select</th>
                        <th class="p-4">Order</th>
                        <th class="p-4">Menu</th>
                        <th class="p-4 text-right">Collection</th>
                        <th class="p-4 text-right">Commission</th>
                        <th class="p-4 text-right">Due</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($eligibleOrders as $order)
                        <tr>
                            <td class="p-4">
                                <input type="checkbox" wire:click="toggleOrder({{ $order->id }})" @checked(in_array($order->id, $selectedOrderIds, true))>
                            </td>
                            <td class="p-4 font-mono font-bold">#{{ $order->id }}</td>
                            <td class="p-4">{{ $order->menuItem?->name }}</td>
                            <td class="p-4 text-right font-semibold">৳{{ number_format($order->cashCollectedAmount()) }}</td>
                            <td class="p-4 text-right text-gray-600">৳{{ number_format($order->commissionRetainedFromCashAmount()) }}</td>
                            <td class="p-4 text-right font-bold text-middo-orange">৳{{ number_format($order->dueToMiddoAmount()) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-10 text-center text-gray-400 italic">No Due amounts waiting for handover.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-4 border-t border-gray-100">
            <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Notes (optional)</label>
            <input type="text" wire:model="notes" class="w-full rounded-xl border-gray-200 text-sm" placeholder="Shift / batch note">
        </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-lg font-bold text-middo-dark">Your handovers</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left min-w-[720px]">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                    <tr>
                        <th class="p-4">ID</th>
                        <th class="p-4">Due amount</th>
                        <th class="p-4">Target</th>
                        <th class="p-4">Status</th>
                        <th class="p-4">Orders</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($handovers as $handover)
                        <tr>
                            <td class="p-4 font-mono font-bold">#{{ $handover->id }}</td>
                            <td class="p-4">৳{{ number_format($handover->amount) }}</td>
                            <td class="p-4">{{ $handover->isMiddoTarget() ? 'Middo / ops' : 'Kitchen' }}</td>
                            <td class="p-4">{{ str($handover->status)->title() }}</td>
                            <td class="p-4 text-gray-600">
                                {{ $handover->items->pluck('order_id')->map(fn ($id) => '#'.$id)->implode(', ') }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-10 text-center text-gray-400 italic">No handovers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($handovers->hasPages())
            <div class="p-4">{{ $handovers->links() }}</div>
        @endif
    </div>
</div>
