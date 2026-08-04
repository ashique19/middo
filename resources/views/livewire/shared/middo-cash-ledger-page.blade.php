<div class="max-w-7xl mx-auto py-10 px-6 space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
            <h1 class="text-3xl font-bold text-middo-dark">Middo cash ledger</h1>
            <p class="text-sm font-semibold text-gray-500">
                System cash balance: <span class="text-middo-dark font-black">৳{{ number_format($balance) }}</span>
            </p>
        </div>
        <select wire:model.live="entryFilter" class="text-sm border border-gray-200 rounded-xl px-3 py-2 font-semibold text-gray-700">
            <option value="all">All entries</option>
            <option value="package">Package-related</option>
            <option value="adjustment">Adjustments</option>
        </select>
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ $statusMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errorMessage }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        @if($canWriteMoney)
            <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm space-y-3">
                <h2 class="text-sm font-bold uppercase tracking-wider text-gray-400">Post adjustment</h2>
                <p class="text-xs text-gray-500">Accounts-owned. Ops can view the ledger but not post adjustments.</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="$set('adjustDirection', 'credit')"
                        @class(['px-3 py-1.5 rounded-xl text-xs font-bold border', 'bg-emerald-600 text-white border-emerald-600' => $adjustDirection === 'credit', 'bg-white border-gray-200' => $adjustDirection !== 'credit'])>
                        Credit (+)
                    </button>
                    <button type="button" wire:click="$set('adjustDirection', 'debit')"
                        @class(['px-3 py-1.5 rounded-xl text-xs font-bold border', 'bg-red-600 text-white border-red-600' => $adjustDirection === 'debit', 'bg-white border-gray-200' => $adjustDirection !== 'debit'])>
                        Debit (−)
                    </button>
                </div>
                <input type="number" min="1" wire:model="adjustAmount" placeholder="Amount ৳" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" />
                <input type="text" wire:model="adjustReason" maxlength="400" placeholder="Reason (required)" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" />
                <button type="button" wire:click="postAdjustment" wire:confirm="Post cash adjustment to Middo ledger?"
                    class="inline-flex px-4 py-2 rounded-xl bg-middo-orange text-white text-sm font-bold">
                    Post adjustment
                </button>
            </div>
        @else
            <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm space-y-2">
                <h2 class="text-sm font-bold uppercase tracking-wider text-gray-400">Adjustments</h2>
                <p class="text-sm text-gray-500">Cash adjustments are accounts-owned. Ops has read-only ledger access.</p>
            </div>
        @endif

        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm space-y-3">
            <h2 class="text-sm font-bold uppercase tracking-wider text-gray-400">Day-end variance</h2>
            <p class="text-xs text-gray-500">Compare counted physical cash to system balance (does not post). Accounts owns close-out; ops may spot-check.</p>
            <input type="number" wire:model.live="countedCash" placeholder="Counted cash ৳" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" />
            <dl class="text-sm space-y-2">
                <div class="flex justify-between"><dt class="text-gray-500">System</dt><dd class="font-mono font-bold">৳{{ number_format($balance) }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Counted</dt><dd class="font-mono font-bold">{{ $countedCash !== '' ? '৳'.number_format((int) $countedCash) : '—' }}</dd></div>
                <div class="flex justify-between border-t border-gray-50 pt-2">
                    <dt class="text-gray-500">Variance</dt>
                    <dd @class(['font-mono font-bold', 'text-emerald-700' => $variance === 0, 'text-amber-800' => $variance !== null && $variance !== 0])>
                        @if($variance === null)
                            —
                        @else
                            {{ $variance > 0 ? '+' : '' }}৳{{ number_format($variance) }}
                        @endif
                    </dd>
                </div>
            </dl>
        </div>
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
