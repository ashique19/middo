@props([
    'orders' => [],
    'emptyMessage' => 'No orders found.',
    'showGroup' => false,
])

<div class="bg-white shadow-md border border-gray-100 rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-[960px]">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wider text-gray-500">
                    <th class="p-4">Order #</th>
                    @if($showGroup)
                        <th class="p-4">Group</th>
                    @endif
                    <th class="p-4">Date</th>
                    <th class="p-4">Window</th>
                    <th class="p-4">Customer</th>
                    <th class="p-4">Menu</th>
                    <th class="p-4 text-center">Qty</th>
                    <th class="p-4">Address</th>
                    <th class="p-4">Status</th>
                    <th class="p-4">Payment</th>
                    <th class="p-4 text-right">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-sm">
                @forelse($orders as $order)
                    <tr wire:key="operation-order-row-{{ $order['id'] }}" class="hover:bg-gray-50/70 transition">
                        <td class="p-4 font-mono font-semibold text-gray-800">#{{ $order['id'] }}</td>
                        @if($showGroup)
                            <td class="p-4 text-xs font-semibold text-middo-orange">
                                {{ $order['order_group']['name'] ?? '—' }}
                            </td>
                        @endif
                        <td class="p-4 font-medium text-gray-700">
                            {{ \Carbon\Carbon::parse($order['delivery_date'])->format('M d, Y') }}
                        </td>
                        <td class="p-4 text-gray-600">{{ $order['delivery_time'] ?? '—' }}</td>
                        <td class="p-4 font-medium text-gray-800">
                            @if(!empty($order['user']))
                                {{ trim(($order['user']['first_name'] ?? '') . ' ' . ($order['user']['last_name'] ?? '')) ?: 'N/A' }}
                            @else
                                N/A
                            @endif
                        </td>
                        <td class="p-4 font-semibold text-gray-800">
                            {{ $order['menu_item']['name'] ?? 'Custom Selection' }}
                        </td>
                        <td class="p-4 text-center font-mono font-bold text-middo-orange">{{ $order['quantity'] ?? 1 }}</td>
                        <td class="p-4 text-gray-600 max-w-xs truncate" title="{{ $order['address'] ?? '' }}">
                            {{ $order['address'] ?? '—' }}
                        </td>
                        <td class="p-4">
                            <span class="inline-flex px-2 py-1 rounded-md text-[11px] font-bold uppercase tracking-wide bg-amber-50 text-amber-900 border border-amber-200/70">
                                {{ $order['order_status'] ?? 'pending' }}
                            </span>
                        </td>
                        <td class="p-4">
                            <span class="inline-flex px-2 py-1 rounded-md text-[11px] font-bold uppercase tracking-wide {{ ($order['payment_status'] ?? 'pending') === 'paid' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200/70' : 'bg-red-50 text-red-700 border border-red-200/70' }}">
                                {{ $order['payment_status'] ?? 'pending' }}
                            </span>
                        </td>
                        <td class="p-4 text-right font-mono font-bold text-gray-900">
                            ৳{{ number_format($order['total_amount'] ?? 0, 0) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $showGroup ? 11 : 10 }}" class="p-12 text-center text-sm font-semibold text-gray-400 italic">
                            {{ $emptyMessage }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
