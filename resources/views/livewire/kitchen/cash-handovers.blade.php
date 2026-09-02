<div class="max-w-7xl mx-auto py-6 sm:py-10 px-4 sm:px-6 space-y-5 sm:space-y-6">
    <div class="space-y-1">
        <a href="{{ route('kitchen.dashboard') }}" class="text-sm font-semibold text-middo-orange hover:underline">← Dashboard</a>
        <h1 class="text-2xl sm:text-3xl font-bold text-middo-dark">Cash handovers</h1>
        <p class="text-sm font-semibold text-gray-500">
            Accept rider cash into your kitchen float. Your wallet is debited (cash received).
        </p>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm max-w-md">
        @if($walletBalance > 0)
            <p class="text-xs font-bold uppercase tracking-wider text-emerald-600 mb-1">Receivable from Middo</p>
            <p class="text-3xl font-black text-middo-dark tabular-nums">৳{{ number_format($walletBalance) }}</p>
            <a href="{{ route('kitchen.account') }}" class="inline-block mt-2 text-sm font-semibold text-middo-orange hover:underline">Request withdrawal →</a>
        @elseif($walletBalance < 0)
            <p class="text-xs font-bold uppercase tracking-wider text-rose-600 mb-1">Payable to Middo</p>
            <p class="text-3xl font-black text-rose-700 tabular-nums">৳{{ number_format(abs($walletBalance)) }}</p>
            <a href="{{ route('kitchen.account') }}" class="inline-block mt-2 text-sm font-semibold text-middo-orange hover:underline">Send money to Middo →</a>
        @else
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Settled with Middo</p>
            <p class="text-3xl font-black text-middo-dark tabular-nums">৳0</p>
        @endif
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ $statusMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errorMessage }}</div>
    @endif

    <div class="md:hidden space-y-3">
        @forelse($handovers as $handover)
            <div wire:key="handover-m-{{ $handover->id }}"
                 class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-mono font-bold text-middo-dark">#{{ $handover->id }}</p>
                        <p class="text-sm font-semibold text-gray-800">{{ $handover->rider?->name }}</p>
                        <p class="text-xs text-gray-500">{{ $handover->rider?->mobile }}</p>
                    </div>
                    <p class="shrink-0 text-lg font-black text-middo-dark tabular-nums">৳{{ number_format($handover->amount) }}</p>
                </div>
                <p class="text-xs text-gray-600 border-t border-gray-100 pt-2">
                    Orders: {{ $handover->items->pluck('order_id')->map(fn ($id) => '#'.$id)->implode(', ') ?: '—' }}
                </p>
                <div class="grid grid-cols-1 gap-2">
                    <button
                        type="button"
                        wire:click="accept({{ $handover->id }})"
                        wire:confirm="Accept this cash? Your kitchen wallet will be debited."
                        class="w-full inline-flex justify-center px-3 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">
                        Accept cash
                    </button>
                    <button
                        type="button"
                        wire:click="reject({{ $handover->id }})"
                        wire:confirm="Reject this handover? Rider can re-submit the orders."
                        class="w-full inline-flex justify-center px-3 py-2.5 rounded-xl border border-red-200 bg-red-50 text-red-700 text-xs font-bold">
                        Reject
                    </button>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-gray-100 bg-white p-10 text-center text-sm text-gray-400 italic">
                No pending cash handovers.
            </div>
        @endforelse
    </div>

    <div class="hidden md:block bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left min-w-[640px]">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                    <tr>
                        <th class="p-4">Handover</th>
                        <th class="p-4">Rider</th>
                        <th class="p-4">Orders</th>
                        <th class="p-4 text-right">Amount</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($handovers as $handover)
                        <tr wire:key="handover-{{ $handover->id }}">
                            <td class="p-4 font-mono font-bold">#{{ $handover->id }}</td>
                            <td class="p-4">
                                <div class="font-semibold">{{ $handover->rider?->name }}</div>
                                <div class="text-xs text-gray-500">{{ $handover->rider?->mobile }}</div>
                            </td>
                            <td class="p-4 text-gray-600">
                                {{ $handover->items->pluck('order_id')->map(fn ($id) => '#'.$id)->implode(', ') }}
                            </td>
                            <td class="p-4 text-right font-semibold">৳{{ number_format($handover->amount) }}</td>
                            <td class="p-4 text-right space-x-2 whitespace-nowrap">
                                <button
                                    type="button"
                                    wire:click="accept({{ $handover->id }})"
                                    wire:confirm="Accept this cash? Your kitchen wallet will be debited."
                                    class="inline-flex px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">
                                    Accept cash
                                </button>
                                <button
                                    type="button"
                                    wire:click="reject({{ $handover->id }})"
                                    wire:confirm="Reject this handover? Rider can re-submit the orders."
                                    class="inline-flex px-3 py-1.5 rounded-xl border border-red-200 bg-red-50 text-red-700 text-xs font-bold">
                                    Reject
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-12 text-center text-gray-400 italic">No pending cash handovers.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($handovers->hasPages())
            <div class="p-4 overflow-x-auto">{{ $handovers->links() }}</div>
        @endif
    </div>

    @if($handovers->hasPages())
        <div class="md:hidden overflow-x-auto">{{ $handovers->links() }}</div>
    @endif
</div>
