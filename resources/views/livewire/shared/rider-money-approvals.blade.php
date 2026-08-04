<div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div>
        <h1 class="text-3xl font-bold text-middo-dark">Rider money</h1>
        <p class="text-sm text-gray-500 mt-1">Approve rider payment requests from Middo cash when Due is cleared.</p>
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ $statusMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errorMessage }}</div>
    @endif

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <table class="w-full text-sm min-w-[720px]">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                <tr>
                    <th class="p-3 text-left">Request</th>
                    <th class="p-3 text-left">Rider</th>
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
                            <div class="font-semibold">{{ $w->rider?->name }}</div>
                            <div class="text-xs text-gray-500">{{ $w->rider?->mobile }}</div>
                        </td>
                        <td class="p-3 text-right font-bold">৳{{ number_format($w->amount) }}</td>
                        <td class="p-3 text-gray-600">{{ $w->notes ?: '—' }}</td>
                        <td class="p-3 text-right whitespace-nowrap">
                            <button type="button" wire:click="approveWithdrawal({{ $w->id }})"
                                    wire:confirm="Approve rider withdrawal #{{ $w->id }} and pay from Middo cash?"
                                    class="px-3 py-1.5 rounded-xl bg-emerald-600 text-white text-xs font-bold">Approve</button>
                            <button type="button" wire:click="rejectWithdrawal({{ $w->id }})"
                                    class="px-3 py-1.5 rounded-xl border border-red-200 text-red-600 text-xs font-bold">Reject</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-10 text-center text-gray-400 italic">No pending rider withdrawals.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($withdrawals->hasPages()) <div class="p-3">{{ $withdrawals->links() }}</div> @endif
    </div>
</div>
