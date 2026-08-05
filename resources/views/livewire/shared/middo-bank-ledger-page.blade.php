<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div>
        <h1 class="text-3xl font-bold text-middo-dark">Bank ledger</h1>
        <p class="text-sm text-gray-500 mt-1">
            Multi-account Middo bank float (separate from cash till). EPS settlements credit net of gateway fees.
        </p>
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ $statusMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errorMessage }}</div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        @forelse($accounts as $account)
            <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400">{{ $account->bank_name }}</p>
                <p class="font-bold text-middo-dark">{{ $account->name }}</p>
                <p class="font-mono text-2xl font-black text-gray-900 mt-2">৳{{ number_format($balances[$account->id] ?? 0) }}</p>
                @if($account->is_default)
                    <p class="text-[10px] font-black uppercase text-sky-700 mt-1">EPS default</p>
                @endif
            </div>
        @empty
            <div class="sm:col-span-3 rounded-xl border border-dashed border-gray-200 p-6 text-sm text-gray-400">
                No bank accounts yet. Admin can add them under Bank accounts.
            </div>
        @endforelse
    </div>

    <div class="flex flex-wrap gap-2 items-center">
        <label class="text-xs font-bold uppercase text-gray-400">Filter</label>
        <select wire:model.live="accountFilter" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
            <option value="">All accounts</option>
            @foreach($accounts as $account)
                <option value="{{ $account->id }}">{{ $account->label() }}</option>
            @endforeach
        </select>
    </div>

    @if($canWrite && $accounts->isNotEmpty())
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-5 space-y-3">
            <h2 class="text-lg font-bold text-middo-dark">Post adjustment</h2>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <select wire:model="adjustAccountId" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    @foreach($accounts->where('is_active', true) as $account)
                        <option value="{{ $account->id }}">{{ $account->label() }}</option>
                    @endforeach
                </select>
                <select wire:model="adjustDirection" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    <option value="credit">Credit</option>
                    <option value="debit">Debit</option>
                </select>
                <input type="number" min="1" wire:model="adjustAmount" placeholder="Amount ৳" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
                <input type="text" wire:model="adjustReason" placeholder="Reason" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
            </div>
            <button type="button" wire:click="postAdjustment" class="inline-flex px-4 py-2 rounded-xl bg-middo-orange text-white text-sm font-bold">Post</button>
        </div>
    @endif

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="p-4">When</th>
                    <th class="p-4">Account</th>
                    <th class="p-4">Type</th>
                    <th class="p-4">Detail</th>
                    <th class="p-4 text-right">Amount</th>
                    <th class="p-4 text-right">Balance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($entries as $entry)
                    <tr wire:key="ble-{{ $entry->id }}">
                        <td class="p-4 text-gray-600 whitespace-nowrap">{{ $entry->created_at?->timezone('Asia/Dhaka')->format('M j, g:i A') }}</td>
                        <td class="p-4">{{ $entry->bankAccount?->name ?? '—' }}</td>
                        <td class="p-4">
                            <span class="text-[10px] font-bold uppercase text-gray-500">{{ str_replace('_', ' ', $entry->entry_type) }}</span>
                            @if($entry->sub_gateway)
                                <div class="text-[10px] text-gray-400">{{ $entry->sub_gateway }}</div>
                            @endif
                        </td>
                        <td class="p-4 text-gray-700">
                            {{ $entry->description }}
                            @if($entry->fee_amount)
                                <div class="text-[11px] text-gray-400">Fee ৳{{ number_format($entry->fee_amount) }} of gross ৳{{ number_format($entry->gross_amount ?? 0) }}</div>
                            @endif
                        </td>
                        <td @class(['p-4 text-right font-mono font-bold', 'text-emerald-700' => $entry->amount >= 0, 'text-red-700' => $entry->amount < 0])>
                            {{ $entry->amount >= 0 ? '+' : '' }}৳{{ number_format($entry->amount) }}
                        </td>
                        <td class="p-4 text-right font-mono">৳{{ number_format($entry->balance_after) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-10 text-center text-gray-400 italic">No bank ledger entries yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($entries->hasPages())
        <div>{{ $entries->links() }}</div>
    @endif
</div>
