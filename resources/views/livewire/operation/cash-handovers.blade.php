<div class="max-w-7xl mx-auto py-10 px-6 space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
            <h1 class="text-3xl font-bold text-middo-dark">Rider Due handovers</h1>
            <p class="text-sm font-semibold text-gray-500">
                Accept or reject Due-to-Middo cash from riders. Middo cash balance: ৳{{ number_format($middoCashBalance) }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach(['pending' => 'Pending', 'accepted' => 'Accepted', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label)
                <button
                    type="button"
                    wire:click="$set('filter', '{{ $key }}')"
                    @class([
                        'px-3 py-1.5 rounded-xl text-xs font-bold border',
                        'bg-middo-orange text-white border-middo-orange' => $filter === $key,
                        'bg-white text-gray-600 border-gray-200 hover:border-middo-orange' => $filter !== $key,
                    ])>
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ $statusMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errorMessage }}</div>
    @endif

    @if($filter === 'pending')
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm space-y-2">
            <label class="text-xs font-bold uppercase text-gray-400">Reject reason (optional)</label>
            <input
                type="text"
                wire:model="rejectReason"
                maxlength="400"
                placeholder="e.g. Amount mismatch — ask rider to resubmit"
                class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" />
        </div>
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
                        <th class="p-4">Status</th>
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
                                {{ $handover->items->pluck('order_id')->map(fn ($id) => '#'.$id)->implode(', ') ?: '—' }}
                            </td>
                            <td class="p-4">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-gray-50 text-gray-700 border border-gray-200">
                                    {{ $handover->status }}
                                </span>
                                @if($handover->notes)
                                    <p class="text-[11px] text-gray-400 mt-1 max-w-xs">{{ $handover->notes }}</p>
                                @endif
                            </td>
                            <td class="p-4 text-right space-x-2 whitespace-nowrap">
                                @if($handover->isPending())
                                    <button
                                        type="button"
                                        wire:click="accept({{ $handover->id }})"
                                        wire:loading.attr="disabled"
                                        class="inline-flex px-4 py-2 rounded-xl bg-middo-orange text-white text-sm font-bold disabled:opacity-60">
                                        Accept
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="reject({{ $handover->id }})"
                                        wire:confirm="Reject this Due handover? Rider can re-submit the orders."
                                        wire:loading.attr="disabled"
                                        class="inline-flex px-4 py-2 rounded-xl border border-red-200 bg-red-50 text-red-700 text-sm font-bold disabled:opacity-60">
                                        Reject
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-10 text-center text-gray-400 italic">No Middo Due handovers in this filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($handovers->hasPages())
            <div class="p-4">{{ $handovers->links() }}</div>
        @endif
    </div>
</div>
