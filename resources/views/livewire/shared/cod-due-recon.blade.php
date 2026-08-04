<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-middo-dark">COD / Due recon</h1>
            <p class="text-sm text-gray-500 mt-1">Day × rider: Collection − Commission = Due, vs Middo handovers accepted.</p>
        </div>
        <div class="flex items-center gap-3">
            <label class="text-xs font-bold uppercase text-gray-400">Delivery date</label>
            <input type="date" wire:model.live="date" class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold" />
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase text-gray-400">Collected</p>
            <p class="text-2xl font-black text-middo-dark mt-1">৳{{ number_format($report['totals']['collected']) }}</p>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase text-gray-400">Commission kept</p>
            <p class="text-2xl font-black text-middo-dark mt-1">৳{{ number_format($report['totals']['commission']) }}</p>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase text-gray-400">Due (snapshot)</p>
            <p class="text-2xl font-black text-middo-dark mt-1">৳{{ number_format($report['totals']['due']) }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4">
            <p class="text-[11px] font-bold uppercase text-emerald-700">Accepted to Middo</p>
            <p class="text-2xl font-black text-emerald-900 mt-1">৳{{ number_format($report['totals']['accepted']) }}</p>
        </div>
    </div>

    <div class="flex flex-wrap gap-4 text-xs font-semibold text-gray-500">
        <span>Open Due still on riders: ৳{{ number_format($report['totals']['open_due']) }}</span>
        <span>Pending Middo handovers: ৳{{ number_format($report['totals']['pending_handover']) }}</span>
        <span>Variance (due − accepted − pending): ৳{{ number_format($report['totals']['variance']) }}</span>
        <span>Rider float now: ৳{{ number_format($report['rider_float_total']) }}</span>
        <span>Middo cash: ৳{{ number_format($report['middo_cash']) }}</span>
        <a href="{{ route($routePrefix.'.cash-handovers') }}" class="text-middo-orange hover:underline">Cash handovers →</a>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[960px]">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                    <tr>
                        <th class="p-3 text-left">Rider</th>
                        <th class="p-3 text-right">Orders</th>
                        <th class="p-3 text-right">Collected</th>
                        <th class="p-3 text-right">Commission</th>
                        <th class="p-3 text-right">Due</th>
                        <th class="p-3 text-right">Open Due</th>
                        <th class="p-3 text-right">Pending HO</th>
                        <th class="p-3 text-right">Accepted</th>
                        <th class="p-3 text-right">Variance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($report['rows'] as $row)
                        <tr @class(['bg-amber-50/40' => $row['variance'] !== 0 || $row['open_due'] > 0])>
                            <td class="p-3">
                                <div class="font-semibold">{{ $row['rider_name'] }}</div>
                                <div class="text-xs text-gray-500">{{ $row['rider_mobile'] }}</div>
                            </td>
                            <td class="p-3 text-right font-mono">{{ $row['order_count'] }}</td>
                            <td class="p-3 text-right font-mono">৳{{ number_format($row['collected']) }}</td>
                            <td class="p-3 text-right font-mono">৳{{ number_format($row['commission']) }}</td>
                            <td class="p-3 text-right font-mono font-bold">৳{{ number_format($row['due']) }}</td>
                            <td class="p-3 text-right font-mono">৳{{ number_format($row['open_due']) }}</td>
                            <td class="p-3 text-right font-mono">৳{{ number_format($row['pending_handover']) }}</td>
                            <td class="p-3 text-right font-mono text-emerald-700">৳{{ number_format($row['accepted']) }}</td>
                            <td class="p-3 text-right font-mono {{ $row['variance'] === 0 ? 'text-gray-500' : 'text-amber-800 font-bold' }}">
                                ৳{{ number_format($row['variance']) }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="p-10 text-center text-gray-400 italic">No COD activity for this date.</td></tr>
                    @endforelse
                </tbody>
                @if(count($report['rows']) > 0)
                    <tfoot class="bg-gray-50 text-sm font-bold">
                        <tr>
                            <td class="p-3">Totals</td>
                            <td class="p-3 text-right font-mono">{{ $report['totals']['order_count'] }}</td>
                            <td class="p-3 text-right font-mono">৳{{ number_format($report['totals']['collected']) }}</td>
                            <td class="p-3 text-right font-mono">৳{{ number_format($report['totals']['commission']) }}</td>
                            <td class="p-3 text-right font-mono">৳{{ number_format($report['totals']['due']) }}</td>
                            <td class="p-3 text-right font-mono">৳{{ number_format($report['totals']['open_due']) }}</td>
                            <td class="p-3 text-right font-mono">৳{{ number_format($report['totals']['pending_handover']) }}</td>
                            <td class="p-3 text-right font-mono">৳{{ number_format($report['totals']['accepted']) }}</td>
                            <td class="p-3 text-right font-mono">৳{{ number_format($report['totals']['variance']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
