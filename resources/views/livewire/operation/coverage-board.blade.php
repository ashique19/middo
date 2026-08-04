<div class="max-w-7xl mx-auto py-10 px-6 space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
            <h1 class="text-3xl font-bold text-middo-dark">Coverage vs demand</h1>
            <p class="text-sm font-semibold text-gray-500">
                Kitchens and riders per area against orders for the selected day.
                <a href="{{ route($rolePrefix.'.areas.index') }}" class="text-middo-orange hover:underline">Manage areas →</a>
            </p>
        </div>
        <div>
            <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-1">Delivery date</label>
            <input type="date" wire:model.live="deliveryDate"
                   class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold" />
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase text-gray-400">Areas</p>
            <p class="text-2xl font-black text-middo-dark mt-1">{{ count($rows) }}</p>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase text-gray-400">Orders</p>
            <p class="text-2xl font-black text-middo-dark mt-1">{{ number_format($demandOrders) }}</p>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase text-gray-400">Meals (qty)</p>
            <p class="text-2xl font-black text-middo-dark mt-1">{{ number_format($demandQty) }}</p>
        </div>
        <div class="rounded-2xl border {{ $gaps > 0 ? 'border-amber-200 bg-amber-50' : 'border-emerald-100 bg-emerald-50' }} p-4">
            <p class="text-[11px] font-bold uppercase {{ $gaps > 0 ? 'text-amber-700' : 'text-emerald-700' }}">Coverage gaps</p>
            <p class="text-2xl font-black {{ $gaps > 0 ? 'text-amber-900' : 'text-emerald-900' }} mt-1">{{ $gaps }}</p>
        </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[720px]">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                    <tr>
                        <th class="p-3 text-left">Area</th>
                        <th class="p-3 text-left">City</th>
                        <th class="p-3 text-right">Orders</th>
                        <th class="p-3 text-right">Qty</th>
                        <th class="p-3 text-right">Kitchens</th>
                        <th class="p-3 text-right">Riders</th>
                        <th class="p-3 text-left">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($rows as $row)
                        <tr @class(['bg-amber-50/50' => $row['gap']])>
                            <td class="p-3 font-semibold text-middo-dark">{{ $row['area_name'] }}</td>
                            <td class="p-3 text-gray-600">{{ $row['city_name'] }}</td>
                            <td class="p-3 text-right font-mono">{{ $row['orders'] }}</td>
                            <td class="p-3 text-right font-mono">{{ $row['quantity'] }}</td>
                            <td class="p-3 text-right font-mono {{ $row['orders'] > 0 && $row['kitchens'] === 0 ? 'text-amber-800 font-bold' : '' }}">
                                {{ $row['kitchens'] }}
                            </td>
                            <td class="p-3 text-right font-mono {{ $row['orders'] > 0 && $row['riders'] === 0 ? 'text-amber-800 font-bold' : '' }}">
                                {{ $row['riders'] }}
                            </td>
                            <td class="p-3 text-xs font-semibold {{ $row['gap'] ? 'text-amber-800' : 'text-emerald-700' }}">
                                {{ $row['gap'] ? ($row['gap_reason'] ?? 'Gap') : 'Covered' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-10 text-center text-gray-400 italic">No areas configured yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
