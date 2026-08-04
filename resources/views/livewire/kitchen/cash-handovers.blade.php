<div class="max-w-7xl mx-auto py-10 px-6 space-y-6">
    <div class="space-y-1">
        <a href="{{ route('kitchen.dashboard') }}" class="text-sm font-semibold text-middo-orange hover:underline">← Dashboard</a>
        <h1 class="text-3xl font-bold text-middo-dark">Cash handovers</h1>
        <p class="text-sm font-semibold text-gray-500">
            Accept rider cash into your kitchen float. Your wallet is debited (cash received).
            @if($walletBalance > 0)
                Middo currently owes you ৳{{ number_format($walletBalance) }}.
            @elseif($walletBalance < 0)
                You currently owe Middo ৳{{ number_format(abs($walletBalance)) }}.
            @else
                Wallet settled at ৳0.
            @endif
        </p>
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ $statusMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errorMessage }}</div>
    @endif

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left min-w-[800px]">
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
                        <tr>
                            <td class="p-4 font-mono font-bold">#{{ $handover->id }}</td>
                            <td class="p-4">
                                <div class="font-semibold">{{ $handover->rider?->name }}</div>
                                <div class="text-xs text-gray-500">{{ $handover->rider?->mobile }}</div>
                            </td>
                            <td class="p-4 text-gray-600">
                                {{ $handover->items->pluck('order_id')->map(fn ($id) => '#'.$id)->implode(', ') }}
                            </td>
                            <td class="p-4 text-right font-semibold">৳{{ number_format($handover->amount) }}</td>
                            <td class="p-4 text-right">
                                <button
                                    type="button"
                                    wire:click="accept({{ $handover->id }})"
                                    wire:confirm="Accept this cash? Your kitchen wallet will be debited."
                                    class="inline-flex px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">
                                    Accept cash
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
            <div class="p-4">{{ $handovers->links() }}</div>
        @endif
    </div>
</div>
