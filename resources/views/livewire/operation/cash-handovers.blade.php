<div class="max-w-7xl mx-auto py-10 px-6 space-y-6">
    <div class="space-y-1">
        <h1 class="text-3xl font-bold text-middo-dark">Rider Due handovers</h1>
        <p class="text-sm font-semibold text-gray-500">
            Accept Due-to-Middo cash from riders. Middo cash balance: ৳{{ number_format($middoCashBalance) }}
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
            <table class="w-full text-left min-w-[720px]">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                    <tr>
                        <th class="p-4">ID</th>
                        <th class="p-4">Rider</th>
                        <th class="p-4">Due amount</th>
                        <th class="p-4">Orders</th>
                        <th class="p-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($handovers as $handover)
                        <tr>
                            <td class="p-4 font-mono font-bold">#{{ $handover->id }}</td>
                            <td class="p-4">{{ $handover->rider?->name }}</td>
                            <td class="p-4 font-semibold">৳{{ number_format($handover->amount) }}</td>
                            <td class="p-4 text-gray-600">
                                {{ $handover->items->pluck('order_id')->map(fn ($id) => '#'.$id)->implode(', ') }}
                            </td>
                            <td class="p-4 text-right">
                                <button
                                    type="button"
                                    wire:click="accept({{ $handover->id }})"
                                    wire:loading.attr="disabled"
                                    class="inline-flex px-4 py-2 rounded-xl bg-middo-orange text-white text-sm font-bold disabled:opacity-60">
                                    Accept
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-10 text-center text-gray-400 italic">No pending Middo Due handovers.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($handovers->hasPages())
            <div class="p-4">{{ $handovers->links() }}</div>
        @endif
    </div>
</div>
