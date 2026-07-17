<div class="max-w-7xl mx-auto py-10 px-6 space-y-6">
    <div class="space-y-1">
        <h1 class="text-3xl font-bold text-middo-dark">Middo cash ledger</h1>
        <p class="text-sm font-semibold text-gray-500">
            System cash balance: <span class="text-middo-dark font-black">৳{{ number_format($balance) }}</span>
        </p>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left min-w-[800px]">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                    <tr>
                        <th class="p-4">ID</th>
                        <th class="p-4">Type</th>
                        <th class="p-4">Description</th>
                        <th class="p-4 text-right">Amount</th>
                        <th class="p-4 text-right">Balance</th>
                        <th class="p-4">By</th>
                        <th class="p-4">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($entries as $entry)
                        <tr>
                            <td class="p-4 font-mono">#{{ $entry->id }}</td>
                            <td class="p-4">{{ str($entry->entry_type)->replace('_', ' ')->title() }}</td>
                            <td class="p-4 text-gray-600">{{ $entry->description }}</td>
                            <td class="p-4 text-right font-semibold {{ $entry->amount >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                                {{ $entry->amount >= 0 ? '+' : '' }}৳{{ number_format($entry->amount) }}
                            </td>
                            <td class="p-4 text-right">৳{{ number_format($entry->balance_after) }}</td>
                            <td class="p-4">{{ $entry->createdByUser?->name ?? '—' }}</td>
                            <td class="p-4 text-gray-500">{{ $entry->created_at?->timezone('Asia/Dhaka')->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-12 text-center text-gray-400 italic">No ledger entries yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($entries->hasPages())
            <div class="p-4">{{ $entries->links() }}</div>
        @endif
    </div>
</div>
