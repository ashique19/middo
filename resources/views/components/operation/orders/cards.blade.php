@props([
    'orders' => [],
    'emptyMessage' => 'No orders found.',
    'showGroup' => false,
])

<div class="space-y-3">
    @forelse($orders as $order)
        @php
            $menuName = $order['menu_item']['name'] ?? ($order['menu_name'] ?? 'Custom Selection');
            $groupName = $order['order_group']['name'] ?? ($order['group_name'] ?? null);
            $customerName = $order['customer_name']
                ?? ($order['account_holder_name'] ?? ($order['receiver_name'] ?? '—'));
        @endphp
        <div wire:key="staff-order-card-{{ $order['id'] }}"
             class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm space-y-2">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 space-y-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <x-orders.id-link :order-id="$order['id']" />
                        @if(!empty($order['is_package']) || !empty($order['package_subscription_id']))
                            <x-package-badge :title="$order['package_name'] ?? 'Meal package'" />
                        @endif
                        <span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-amber-50 text-amber-900 border border-amber-200/70">
                            {{ $order['order_status'] ?? 'pending' }}
                        </span>
                    </div>
                    <p class="text-sm font-semibold text-gray-800 break-words">{{ $menuName }}</p>
                    <p class="text-xs text-gray-500">{{ $customerName }}</p>
                </div>
                <p class="shrink-0 text-base font-black text-middo-orange tabular-nums">
                    ×{{ $order['quantity'] ?? 1 }}
                </p>
            </div>
            <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-500">
                <span>{{ \Carbon\Carbon::parse($order['delivery_date'])->format('M d, Y') }}</span>
                <span>{{ $order['delivery_time'] ?? '—' }}</span>
                <span class="font-mono font-semibold text-gray-800">৳{{ number_format($order['total_amount'] ?? 0, 0) }}</span>
                @if($showGroup && $groupName)
                    <span>Group: {{ $groupName }}</span>
                @endif
            </div>
        </div>
    @empty
        <div class="rounded-2xl border border-gray-100 bg-white p-10 text-center text-sm font-semibold text-gray-400 italic">
            {{ $emptyMessage }}
        </div>
    @endforelse
</div>
