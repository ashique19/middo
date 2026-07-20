<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Package Demand</h1>
            <p class="text-sm text-gray-500 mt-1">
                Kitchen prep forecast by menu for a delivery date — package vs à la carte quantities.
            </p>
        </div>
        <a href="{{ $this->activeOrdersUrl() }}" class="text-sm font-bold text-middo-orange hover:underline">Open active orders →</a>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Delivery date</label>
            <input type="date" wire:model.live="deliveryDate" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
        </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <table class="w-full text-left min-w-[720px]">
            <thead>
                <tr class="bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-500">
                    <th class="p-4">Menu</th>
                    <th class="p-4 text-center">Orders</th>
                    <th class="p-4 text-center">Package qty</th>
                    <th class="p-4 text-center">À la carte qty</th>
                    <th class="p-4 text-center">Total qty</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-sm">
                @forelse($rows as $row)
                    <tr wire:key="demand-{{ $row->menu_item_id }}-{{ $deliveryDate }}">
                        <td class="p-4 font-semibold text-gray-800">
                            <div class="flex items-center gap-2">
                                {{ $row->menuItem?->name ?? 'Custom Selection' }}
                                @if((int) $row->package_qty > 0)
                                    <x-package-badge />
                                @endif
                            </div>
                        </td>
                        <td class="p-4 text-center">{{ $row->order_count }}</td>
                        <td class="p-4 text-center font-bold text-sky-800">{{ $row->package_qty }}</td>
                        <td class="p-4 text-center">{{ $row->alacarte_qty }}</td>
                        <td class="p-4 text-center font-black text-middo-orange">{{ $row->total_qty }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-10 text-center text-sm text-gray-400 italic">No active orders for this date.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
