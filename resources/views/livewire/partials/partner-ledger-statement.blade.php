{{-- Shared partner wallet ledger: Summary / ± amount / new balance --}}
@php
    $filter = $ledgerFilter ?? \App\Support\PartnerLedgerPresentation::FILTER_ALL;
    $amountHeader = match ($filter) {
        \App\Support\PartnerLedgerPresentation::FILTER_CASH_IN => '+ Cash-in amount',
        \App\Support\PartnerLedgerPresentation::FILTER_CASH_OUT => '− Cash-out amount',
        default => '+/− Amount',
    };
@endphp
<div class="space-y-3">
    <div class="flex flex-wrap gap-2">
        @foreach(\App\Support\PartnerLedgerPresentation::filters() as $key)
            <button type="button" wire:click="$set('ledgerFilter', '{{ $key }}')"
                    @class([
                        'px-3 py-1.5 rounded-xl text-xs font-bold border',
                        'bg-middo-dark text-white border-middo-dark' => $filter === $key,
                        'bg-white text-gray-700 border-gray-200' => $filter !== $key,
                    ])>
                {{ \App\Support\PartnerLedgerPresentation::filterLabel($key) }}
            </button>
        @endforeach
    </div>

    <div class="md:hidden space-y-3">
        @forelse($rows as $row)
            <div wire:key="ledger-m-{{ $row->id }}" class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm space-y-2">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-800">{{ $row->summary }}</p>
                        <p class="text-xs text-gray-500">{{ $row->created_at?->timezone('Asia/Dhaka')->format('M d, Y H:i') }}</p>
                    </div>
                    <p @class([
                        'shrink-0 font-bold tabular-nums',
                        'text-emerald-700' => $row->direction === \App\Support\PartnerLedgerPresentation::FILTER_CASH_IN,
                        'text-rose-700' => $row->direction === \App\Support\PartnerLedgerPresentation::FILTER_CASH_OUT,
                    ])>
                        {{ $row->direction === \App\Support\PartnerLedgerPresentation::FILTER_CASH_IN ? '+' : '−' }}৳{{ number_format($row->amount) }}
                    </p>
                </div>
                <p @class(['text-xs font-mono', 'text-rose-700' => $row->balance_after < 0, 'text-gray-500' => $row->balance_after >= 0])>
                    New balance ৳{{ number_format($row->balance_after) }}
                </p>
            </div>
        @empty
            <div class="rounded-2xl border border-gray-100 bg-white p-10 text-center text-gray-400 italic text-sm">
                {{ $emptyMessage ?? 'No ledger entries yet.' }}
            </div>
        @endforelse
    </div>

    <div class="hidden md:block bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[520px]">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                    <tr>
                        <th class="p-3 text-left">Summary</th>
                        <th class="p-3 text-right">{{ $amountHeader }}</th>
                        <th class="p-3 text-right">New balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($rows as $row)
                        <tr wire:key="ledger-{{ $row->id }}">
                            <td class="p-3">
                                <p class="font-semibold text-middo-dark">{{ $row->summary }}</p>
                                <p class="text-xs text-gray-500">{{ $row->created_at?->timezone('Asia/Dhaka')->format('M d, Y H:i') }}</p>
                            </td>
                            <td @class([
                                'p-3 text-right font-bold tabular-nums',
                                'text-emerald-700' => $row->direction === \App\Support\PartnerLedgerPresentation::FILTER_CASH_IN,
                                'text-rose-700' => $row->direction === \App\Support\PartnerLedgerPresentation::FILTER_CASH_OUT,
                            ])>
                                {{ $row->direction === \App\Support\PartnerLedgerPresentation::FILTER_CASH_IN ? '+' : '−' }}৳{{ number_format($row->amount) }}
                            </td>
                            <td @class(['p-3 text-right font-mono', 'text-rose-700' => $row->balance_after < 0])>
                                ৳{{ number_format($row->balance_after) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="p-10 text-center text-gray-400 italic">
                                {{ $emptyMessage ?? 'No ledger entries yet.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if(isset($paginator) && $paginator->hasPages())
        <div class="overflow-x-auto">{{ $paginator->links() }}</div>
    @endif
</div>
