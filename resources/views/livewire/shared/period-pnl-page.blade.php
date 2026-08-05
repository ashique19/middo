<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-middo-dark">Period P&amp;L</h1>
            <p class="text-sm text-gray-500 mt-1">
                Order economics by delivery date, plus operating costs and EPS fees booked in the window.
                Cash / bank movements and positions are companion views.
            </p>
        </div>
        <button type="button" wire:click="exportExcel"
                class="px-4 py-2 rounded-xl bg-middo-orange text-white text-sm font-bold hover:bg-[#733614]">
            Export Excel
        </button>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-4 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">From</label>
            <input type="date" wire:model.live="fromDate" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
            @error('fromDate') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-[10px] font-bold uppercase text-gray-400 mb-1">To</label>
            <input type="date" wire:model.live="toDate" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
            @error('toDate') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        @if($report)
            <p class="text-xs text-gray-500 pb-2">
                {{ $report['order_count'] }} orders · {{ $report['from'] }} → {{ $report['to'] }} ({{ $report['timezone'] }})
            </p>
        @endif
    </div>

    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errorMessage }}</div>
    @endif

    @if($report)
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b text-sm font-bold text-middo-dark">P&amp;L lines</div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                    <tr>
                        <th class="p-3 text-left">Line</th>
                        <th class="p-3 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($report['lines'] as $line)
                        <tr @class(['bg-emerald-50/40' => $line['section'] === 'result', 'bg-amber-50/30' => $line['section'] === 'tax'])>
                            <td class="p-3">
                                <div class="font-semibold text-middo-dark">{{ $line['label'] }}</div>
                                @if($line['note'])
                                    <div class="text-[11px] text-gray-500">{{ $line['note'] }}</div>
                                @endif
                            </td>
                            <td @class([
                                'p-3 text-right font-mono font-bold',
                                'text-emerald-700' => $line['amount'] > 0,
                                'text-rose-700' => $line['amount'] < 0,
                            ])>
                                {{ $line['amount'] > 0 ? '+' : '' }}৳{{ number_format($line['amount']) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b text-sm font-bold text-middo-dark">Cash movements</div>
                <table class="w-full text-sm">
                    <tbody class="divide-y">
                        @forelse($report['cash_by_type'] as $row)
                            <tr>
                                <td class="p-3 font-semibold">{{ str($row['entry_type'])->replace('_', ' ')->headline() }}</td>
                                <td class="p-3 text-right font-mono">৳{{ number_format($row['amount']) }}</td>
                            </tr>
                        @empty
                            <tr><td class="p-6 text-center text-gray-400 italic" colspan="2">No cash ledger activity in range.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b text-sm font-bold text-middo-dark">Bank movements</div>
                <table class="w-full text-sm">
                    <tbody class="divide-y">
                        @forelse($report['bank_by_type'] as $row)
                            <tr>
                                <td class="p-3">
                                    <div class="font-semibold">{{ str($row['entry_type'])->replace('_', ' ')->headline() }}</div>
                                    @if($row['fee_amount'] > 0)
                                        <div class="text-[11px] text-gray-500">Fees ৳{{ number_format($row['fee_amount']) }}</div>
                                    @endif
                                </td>
                                <td class="p-3 text-right font-mono">৳{{ number_format($row['amount']) }}</td>
                            </tr>
                        @empty
                            <tr><td class="p-6 text-center text-gray-400 italic" colspan="2">No bank ledger activity in range.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-4">
            <h2 class="text-sm font-bold text-middo-dark mb-3">Cash positions (now)</h2>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3 text-xs">
                @foreach([
                    'cash_at_eps' => 'EPS',
                    'cash_receivable_kitchen' => 'Kitchen recv',
                    'cash_receivable_riders' => 'Rider Due',
                    'cash_in_hand' => 'Till',
                    'bank_float' => 'Bank',
                ] as $key => $label)
                    <div class="rounded-xl bg-gray-50 px-3 py-2">
                        <span class="text-gray-400 font-bold uppercase">{{ $label }}</span>
                        <div class="font-mono font-bold text-sm mt-1">৳{{ number_format($report['positions'][$key]['amount'] ?? 0) }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
