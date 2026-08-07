<div class="max-w-5xl mx-auto py-6 sm:py-8 px-4 sm:px-6 space-y-5 sm:space-y-6">
    <div class="space-y-1">
        <a href="{{ route('delivery.dashboard') }}" class="text-sm font-semibold text-middo-orange hover:underline">← Dashboard</a>
        <h1 class="text-2xl sm:text-3xl font-bold text-middo-dark">Rider account</h1>
        <p class="text-sm text-gray-500">
            Commission credits your wallet (Middo owes you). Cash Due is handed over separately. Request payment when the wallet is positive and Due is cleared.
        </p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-middo-orange mb-1">Due to Middo</p>
            <p class="text-3xl font-black text-middo-orange">৳{{ number_format($due) }}</p>
            <p class="text-xs text-gray-500 mt-1">
                @if($due > 0)
                    <a href="{{ route('delivery.cash-handovers') }}" class="font-semibold text-middo-orange hover:underline">Hand over Due →</a>
                @else
                    No cash Due
                @endif
            </p>
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
            @if($wallet > 0)
                <p class="text-xs font-bold uppercase tracking-wider text-emerald-600 mb-1">Wallet — Middo owes you</p>
                <p class="text-3xl font-black text-middo-dark">৳{{ number_format($wallet) }}</p>
            @else
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Wallet — Middo owes you</p>
                <p class="text-3xl font-black text-middo-dark">৳0</p>
            @endif
            @if($openPayableTotal > 0)
                <p class="text-xs text-gray-500 mt-1">Open lunch payables ৳{{ number_format($openPayableTotal) }}</p>
            @endif
            @if($canRequestPayment || $due > 0)
                <div class="flex flex-col sm:flex-row sm:flex-wrap gap-2 mt-3">
                    @if($canRequestPayment)
                        <button type="button" wire:click="openWithdrawForm" class="w-full sm:w-auto px-3 py-2.5 sm:py-1.5 rounded-xl bg-middo-orange text-white text-xs font-bold">Request payment</button>
                    @endif
                    @if($due > 0)
                        <a href="{{ route('delivery.cash-handovers') }}" class="w-full sm:w-auto inline-flex justify-center px-3 py-2.5 sm:py-1.5 rounded-xl border border-sky-200 text-sky-800 text-xs font-bold bg-sky-50">Cash handovers →</a>
                    @endif
                </div>
            @endif
        </div>
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ $statusMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errorMessage }}</div>
    @endif

    @php
        $accountTabs = [
            'statement' => 'Statement',
            'commissions' => 'Commissions',
        ];
        if ($canRequestPayment) {
            $accountTabs['withdraw'] = 'Request payment';
        }
        $accountTabs['withdrawals'] = 'My requests';
    @endphp
    <div class="flex flex-wrap gap-2">
        @foreach($accountTabs as $key => $label)
            <button type="button"
                    wire:click="{{ $key === 'withdraw' ? 'openWithdrawForm' : "\$set('tab', '{$key}')" }}"
                    @class(['px-3 py-1.5 rounded-xl text-xs font-bold border', 'bg-middo-orange text-white border-middo-orange' => $tab === $key, 'bg-white text-gray-700 border-gray-200' => $tab !== $key])>
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if($tab === 'statement')
        <div class="space-y-3">
            <div class="px-1 text-sm font-bold text-middo-dark">Wallet ledger</div>
            <div class="md:hidden space-y-3">
                @forelse($statement as $row)
                    <div wire:key="stmt-m-{{ $row->id }}" class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm space-y-2">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-800">{{ str($row->entry_type)->replace('_', ' ')->headline() }}</p>
                                <p class="text-xs text-gray-500">{{ $row->created_at?->timezone('Asia/Dhaka')->format('M d, Y H:i') }}</p>
                            </div>
                            <p @class(['shrink-0 font-bold tabular-nums', 'text-emerald-700' => $row->amount > 0, 'text-rose-700' => $row->amount < 0])>
                                {{ $row->amount > 0 ? '+' : '' }}৳{{ number_format($row->amount) }}
                            </p>
                        </div>
                        @if($row->description)
                            <p class="text-sm text-gray-600">{{ $row->description }}</p>
                        @endif
                        <p class="text-xs font-mono text-gray-500">Balance ৳{{ number_format($row->balance_after) }}</p>
                    </div>
                @empty
                    <div class="rounded-2xl border border-gray-100 bg-white p-10 text-center text-gray-400 italic text-sm">
                        No ledger entries yet. Commission credits when you start a run.
                    </div>
                @endforelse
            </div>
            <div class="hidden md:block bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[560px]">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                            <tr>
                                <th class="p-3 text-left">When</th>
                                <th class="p-3 text-left">Type</th>
                                <th class="p-3 text-left">Description</th>
                                <th class="p-3 text-right">Amount</th>
                                <th class="p-3 text-right">Balance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($statement as $row)
                                <tr wire:key="stmt-{{ $row->id }}">
                                    <td class="p-3 text-gray-500 whitespace-nowrap">{{ $row->created_at?->timezone('Asia/Dhaka')->format('M d, Y H:i') }}</td>
                                    <td class="p-3 font-semibold">{{ str($row->entry_type)->replace('_', ' ')->headline() }}</td>
                                    <td class="p-3 text-gray-600">{{ $row->description ?: '—' }}</td>
                                    <td @class(['p-3 text-right font-bold', 'text-emerald-700' => $row->amount > 0, 'text-rose-700' => $row->amount < 0])>
                                        {{ $row->amount > 0 ? '+' : '' }}৳{{ number_format($row->amount) }}
                                    </td>
                                    <td class="p-3 text-right font-mono">৳{{ number_format($row->balance_after) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="p-10 text-center text-gray-400 italic">No ledger entries yet. Commission credits when you start a run.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($statement->hasPages()) <div class="overflow-x-auto">{{ $statement->links() }}</div> @endif
        </div>
    @endif

    @if($tab === 'commissions')
        <div class="space-y-3">
            <div class="px-1 text-sm font-bold text-middo-dark">Commission activity</div>
            <div class="md:hidden space-y-3">
                @forelse($commissionEntries as $row)
                    <div wire:key="comm-m-{{ $row->id }}" class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm space-y-1">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-800">{{ str($row->entry_type)->replace('_', ' ')->headline() }}</p>
                                <p class="text-xs text-gray-500">{{ $row->created_at?->timezone('Asia/Dhaka')->format('M d, Y H:i') }}</p>
                            </div>
                            <p @class(['shrink-0 font-bold tabular-nums', 'text-emerald-700' => $row->amount > 0, 'text-rose-700' => $row->amount < 0])>
                                {{ $row->amount > 0 ? '+' : '' }}৳{{ number_format($row->amount) }}
                            </p>
                        </div>
                        @if($row->description)
                            <p class="text-sm text-gray-600">{{ $row->description }}</p>
                        @endif
                    </div>
                @empty
                    <div class="rounded-2xl border border-gray-100 bg-white p-10 text-center text-gray-400 italic text-sm">
                        No commission lines yet (rates of ৳0 are hidden).
                    </div>
                @endforelse
            </div>
            <div class="hidden md:block bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[520px]">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                            <tr>
                                <th class="p-3 text-left">When</th>
                                <th class="p-3 text-left">Type</th>
                                <th class="p-3 text-left">Description</th>
                                <th class="p-3 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($commissionEntries as $row)
                                <tr wire:key="comm-{{ $row->id }}">
                                    <td class="p-3 text-gray-500 whitespace-nowrap">{{ $row->created_at?->timezone('Asia/Dhaka')->format('M d, Y H:i') }}</td>
                                    <td class="p-3 font-semibold">{{ str($row->entry_type)->replace('_', ' ')->headline() }}</td>
                                    <td class="p-3 text-gray-600">{{ $row->description ?: '—' }}</td>
                                    <td @class(['p-3 text-right font-bold', 'text-emerald-700' => $row->amount > 0, 'text-rose-700' => $row->amount < 0])>
                                        {{ $row->amount > 0 ? '+' : '' }}৳{{ number_format($row->amount) }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="p-10 text-center text-gray-400 italic">No commission lines yet (rates of ৳0 are hidden).</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if($openPayables->isNotEmpty())
            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-4">
                <h2 class="text-sm font-bold text-middo-dark mb-2">Open lunch payables</h2>
                <ul class="text-sm text-gray-600 space-y-1">
                    @foreach($openPayables as $p)
                        <li class="flex justify-between gap-3">
                            <span>Order #{{ $p->order_id }}</span>
                            <span class="font-semibold">৳{{ number_format($p->amount) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif

    @if($tab === 'withdraw')
        <form id="account-withdraw-panel" wire:submit="requestWithdrawal" class="scroll-mt-24 bg-white border border-gray-100 rounded-2xl shadow-sm p-4 sm:p-5 space-y-4">
            <h2 class="text-lg font-bold text-middo-dark">Request payment</h2>
            <p class="text-sm text-gray-500">
                Available when Middo owes you and you have no Due cash to hand over. Choose a payout channel — Bank / bKash / Nagad details come from your profile.
            </p>
            @if($due > 0)
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900">
                    You still have Due to Middo ৳{{ number_format($due) }}.
                    <a href="{{ route('delivery.cash-handovers') }}" class="underline">Hand it over first</a>.
                </div>
            @elseif($wallet < 1)
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                    Wallet is ৳0 — nothing to request.
                </div>
            @endif
            <div>
                <label class="block text-xs font-bold uppercase text-gray-400 mb-1">Amount (৳)</label>
                <input type="number" min="1" max="{{ max(1, $wallet) }}" wire:model="withdrawAmount" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" @disabled(! $canRequestPayment)>
                @error('withdrawAmount') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            @include('livewire.partials.payout-channel-fields', ['disabled' => ! $canRequestPayment])
            <div>
                <label class="block text-xs font-bold uppercase text-gray-400 mb-1">Notes</label>
                <textarea wire:model="withdrawNotes" rows="2" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" @disabled(! $canRequestPayment)></textarea>
            </div>
            <button type="submit" class="w-full sm:w-auto px-4 py-2.5 sm:py-2 rounded-xl bg-middo-orange text-white text-sm font-bold disabled:opacity-50" @disabled(! $canRequestPayment)>
                Submit request
            </button>
        </form>
    @endif

    @if($tab === 'withdrawals')
        <div class="md:hidden space-y-3">
            @forelse($withdrawals as $w)
                <div wire:key="wd-m-{{ $w->id }}" class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm space-y-1">
                    <div class="flex items-start justify-between gap-3">
                        <p class="font-mono font-semibold">#{{ $w->id }}</p>
                        <p class="font-bold tabular-nums">৳{{ number_format($w->amount) }}</p>
                    </div>
                    <p class="text-sm font-semibold text-gray-800">{{ $w->payoutChannelLabel() }}</p>
                    <p class="text-xs text-gray-500">{{ $w->payoutDetailsSummary() }}</p>
                    <p class="text-xs capitalize text-gray-600">{{ $w->status }} · {{ $w->created_at?->timezone('Asia/Dhaka')->format('M d, Y H:i') }}</p>
                </div>
            @empty
                <div class="rounded-2xl border border-gray-100 bg-white p-8 text-center text-gray-400 italic text-sm">No payment requests yet.</div>
            @endforelse
        </div>
        <div class="hidden md:block bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[560px]">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                        <tr>
                            <th class="p-3 text-left">ID</th>
                            <th class="p-3 text-right">Amount</th>
                            <th class="p-3 text-left">Channel</th>
                            <th class="p-3 text-left">Status</th>
                            <th class="p-3 text-left">When</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($withdrawals as $w)
                            <tr wire:key="wd-{{ $w->id }}">
                                <td class="p-3 font-mono">#{{ $w->id }}</td>
                                <td class="p-3 text-right font-bold">৳{{ number_format($w->amount) }}</td>
                                <td class="p-3">
                                    <div class="font-semibold">{{ $w->payoutChannelLabel() }}</div>
                                    <div class="text-xs text-gray-500">{{ $w->payoutDetailsSummary() }}</div>
                                </td>
                                <td class="p-3 capitalize">{{ $w->status }}</td>
                                <td class="p-3 text-gray-500">{{ $w->created_at?->timezone('Asia/Dhaka')->format('M d, Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-8 text-center text-gray-400 italic">No payment requests yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($withdrawals->hasPages()) <div class="overflow-x-auto">{{ $withdrawals->links() }}</div> @endif
    @endif
</div>
