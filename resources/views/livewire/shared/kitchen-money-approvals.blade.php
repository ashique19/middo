<div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div>
        <h1 class="text-3xl font-bold text-middo-dark">Kitchen money</h1>
        <p class="text-sm text-gray-500 mt-1">Approve kitchen withdrawals and confirm transfers into Middo cash.</p>
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ $statusMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errorMessage }}</div>
    @endif

    <div class="flex flex-wrap gap-2">
        <button type="button" wire:click="$set('tab', 'withdrawals')"
                @class(['px-3 py-1.5 rounded-xl text-xs font-bold border', 'bg-middo-orange text-white border-middo-orange' => $tab === 'withdrawals', 'bg-white border-gray-200' => $tab !== 'withdrawals'])>
            Pending withdrawals
        </button>
        <button type="button" wire:click="$set('tab', 'transfers')"
                @class(['px-3 py-1.5 rounded-xl text-xs font-bold border', 'bg-middo-orange text-white border-middo-orange' => $tab === 'transfers', 'bg-white border-gray-200' => $tab !== 'transfers'])>
            Pending transfers
        </button>
    </div>

    @if($tab === 'withdrawals')
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <table class="w-full text-sm min-w-[720px]">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                    <tr>
                        <th class="p-3 text-left">Request</th>
                        <th class="p-3 text-left">Kitchen</th>
                        <th class="p-3 text-right">Amount</th>
                        <th class="p-3 text-left">Notes</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($withdrawals as $w)
                        <tr>
                            <td class="p-3 font-mono">#{{ $w->id }}</td>
                            <td class="p-3">
                                <div class="font-semibold">{{ $w->kitchen?->name }}</div>
                                <div class="text-xs text-gray-500">{{ $w->kitchen?->mobile }}</div>
                            </td>
                            <td class="p-3 text-right font-bold">৳{{ number_format($w->amount) }}</td>
                            <td class="p-3 text-gray-600">{{ $w->notes ?: '—' }}</td>
                            <td class="p-3 text-right whitespace-nowrap">
                                @if($w->status === 'pending')
                                    <button type="button" wire:click="approveWithdrawal({{ $w->id }})"
                                            wire:confirm="Approve withdrawal #{{ $w->id }} and pay from Middo cash?"
                                            class="px-3 py-1.5 rounded-xl bg-emerald-600 text-white text-xs font-bold">Approve</button>
                                    <button type="button" wire:click="rejectWithdrawal({{ $w->id }})"
                                            class="px-3 py-1.5 rounded-xl border border-red-200 text-red-600 text-xs font-bold">Reject</button>
                                @else
                                    <span class="capitalize text-xs font-bold">{{ $w->status }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-10 text-center text-gray-400 italic">No pending withdrawals.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if($withdrawals->hasPages()) <div class="p-3">{{ $withdrawals->links() }}</div> @endif
        </div>
    @endif

    @if($tab === 'transfers')
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <table class="w-full text-sm min-w-[800px]">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                    <tr>
                        <th class="p-3 text-left">Transfer</th>
                        <th class="p-3 text-left">Kitchen</th>
                        <th class="p-3 text-right">Amount</th>
                        <th class="p-3 text-left">Reference</th>
                        <th class="p-3 text-left">Proof</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($transfers as $t)
                        <tr>
                            <td class="p-3 font-mono">#{{ $t->id }}</td>
                            <td class="p-3">
                                <div class="font-semibold">{{ $t->kitchen?->name }}</div>
                                <div class="text-xs text-gray-500">{{ $t->kitchen?->mobile }}</div>
                            </td>
                            <td class="p-3 text-right font-bold">৳{{ number_format($t->amount) }}</td>
                            <td class="p-3 text-gray-600">{{ $t->reference_code ?: '—' }}</td>
                            <td class="p-3">
                                @if($t->proof_path)
                                    <a href="{{ asset($t->proof_path) }}" target="_blank" class="text-middo-orange font-semibold hover:underline">View</a>
                                @else —
                                @endif
                            </td>
                            <td class="p-3 text-right whitespace-nowrap">
                                @if($t->status === 'pending')
                                    <button type="button" wire:click="confirmTransfer({{ $t->id }})"
                                            wire:confirm="Confirm transfer #{{ $t->id }} into Middo cash?"
                                            class="px-3 py-1.5 rounded-xl bg-emerald-600 text-white text-xs font-bold">Confirm</button>
                                    <button type="button" wire:click="rejectTransfer({{ $t->id }})"
                                            class="px-3 py-1.5 rounded-xl border border-red-200 text-red-600 text-xs font-bold">Reject</button>
                                @else
                                    <span class="capitalize text-xs font-bold">{{ $t->status }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-10 text-center text-gray-400 italic">No pending transfers.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if($transfers->hasPages()) <div class="p-3">{{ $transfers->links() }}</div> @endif
        </div>
    @endif
</div>
