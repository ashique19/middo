<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-middo-dark">Operating costs</h1>
            <p class="text-sm text-gray-500 mt-1">
                Box/custom rider commissions booked outside order <span class="font-mono">middo_rest</span>
                (P&amp;L cost ledger — separate from Middo cash and partner Due wallets).
            </p>
        </div>
        <select wire:model.live="runType" class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold">
            <option value="all">All run types</option>
            @foreach($runTypes as $type)
                <option value="{{ $type }}">{{ str_replace('_', ' ', $type) }}</option>
            @endforeach
        </select>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm sm:col-span-1">
            <p class="text-[11px] font-bold uppercase text-gray-400">Total booked</p>
            <p class="text-2xl font-black text-middo-dark mt-1">৳{{ number_format($total) }}</p>
        </div>
        @foreach($byRunType as $row)
            <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase text-gray-400">{{ str_replace('_', ' ', $row->run_type ?: '—') }}</p>
                <p class="text-xl font-black text-middo-dark mt-1">৳{{ number_format((int) $row->total) }}</p>
                <p class="text-xs text-gray-400">{{ $row->rows }} entries</p>
            </div>
        @endforeach
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[800px]">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                    <tr>
                        <th class="p-3 text-left">ID</th>
                        <th class="p-3 text-left">Run type</th>
                        <th class="p-3 text-left">Rider</th>
                        <th class="p-3 text-right">Amount</th>
                        <th class="p-3 text-left">Description</th>
                        <th class="p-3 text-left">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($costs as $cost)
                        <tr>
                            <td class="p-3 font-mono">#{{ $cost->id }}</td>
                            <td class="p-3">{{ str_replace('_', ' ', $cost->run_type ?: '—') }}</td>
                            <td class="p-3">
                                <div class="font-semibold">{{ $cost->rider?->name ?? '—' }}</div>
                                <div class="text-xs text-gray-500">{{ $cost->rider?->mobile }}</div>
                            </td>
                            <td class="p-3 text-right font-bold">৳{{ number_format($cost->amount) }}</td>
                            <td class="p-3 text-gray-600">{{ $cost->description ?: '—' }}</td>
                            <td class="p-3 text-gray-500">{{ $cost->created_at?->timezone('Asia/Dhaka')->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-10 text-center text-gray-400 italic">No operating costs booked yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($costs->hasPages())
            <div class="p-3">{{ $costs->links() }}</div>
        @endif
    </div>
</div>
