<div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Package Insights</h1>
        <p class="text-sm text-gray-500 mt-1">Prepaid revenue, refunds, and wallet activity tied to meal packages.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Active subscriptions</p>
            <p class="text-3xl font-black text-middo-dark mt-1">{{ $activeSubscribers }}</p>
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Prepaid collected</p>
            <p class="text-3xl font-black text-middo-dark mt-1">৳{{ number_format($prepaidRevenue) }}</p>
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Package refunds</p>
            <p class="text-3xl font-black text-middo-dark mt-1">৳{{ number_format($packageRefunds) }}</p>
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Pending package days</p>
            <p class="text-3xl font-black text-middo-dark mt-1">{{ $pendingPackageOrders }}</p>
        </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-lg font-bold text-gray-800">Top packages by prepaid revenue</h2>
        </div>
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                    <th class="p-4">Package</th>
                    <th class="p-4 text-center">Subscriptions</th>
                    <th class="p-4 text-right">Revenue</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($byPackage as $row)
                    <tr>
                        <td class="p-4 font-semibold">{{ $row->package?->name ?? '—' }}</td>
                        <td class="p-4 text-center">{{ $row->sub_count }}</td>
                        <td class="p-4 text-right font-bold text-middo-orange">৳{{ number_format((int) $row->revenue) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="p-8 text-center text-gray-400 italic">No subscription revenue yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-bold text-gray-800">Wallet activity</h2>
            <select wire:model.live="walletFilter" class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold">
                <option value="package">Package-related</option>
                <option value="all">All wallet txs</option>
            </select>
        </div>
        <table class="w-full text-left text-sm min-w-[720px]">
            <thead>
                <tr class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                    <th class="p-4">When</th>
                    <th class="p-4">User</th>
                    <th class="p-4">Type</th>
                    <th class="p-4">Description</th>
                    <th class="p-4 text-right">Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($walletEntries as $entry)
                    <tr>
                        <td class="p-4 text-xs text-gray-500">{{ $entry->created_at?->format('M d, Y H:i') }}</td>
                        <td class="p-4">{{ $entry->user?->mobile ?? '—' }}</td>
                        <td class="p-4 capitalize">{{ $entry->type }}</td>
                        <td class="p-4 text-gray-700">{{ $entry->description }}</td>
                        <td class="p-4 text-right font-bold">৳{{ number_format($entry->amount) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-8 text-center text-gray-400 italic">No matching wallet transactions.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-100">{{ $walletEntries->links() }}</div>
    </div>
</div>
