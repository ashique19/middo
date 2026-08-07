@props([
    'orders' => [],
    'emptyMessage' => 'No orders found.',
    'showGroup' => false,
    'showViewAction' => true,
    'complaintClickable' => false,
])

@php
    $emptyColspan = ($showGroup ? 12 : 11) + ($showViewAction ? 1 : 0);
@endphp

{{-- Mobile cards --}}
<div class="md:hidden space-y-3">
    @forelse($orders as $order)
        @php
            $methodLabel = $order['payment_method_label']
                ?? \App\Support\OrderPaymentMethod::label($order['payment_method'] ?? null);
            if ($methodLabel === '—' && ($order['payment_status'] ?? 'pending') === 'pending') {
                $methodLabel = 'Cash on Delivery';
            }
            $customerName = $order['customer_name']
                ?? (! empty($order['user'])
                    ? (trim(($order['user']['first_name'] ?? '').' '.($order['user']['last_name'] ?? '')) ?: 'N/A')
                    : ($order['receiver_name'] ?? 'N/A'));
            $menuName = $order['menu_item']['name'] ?? ($order['menu_name'] ?? 'Custom Selection');
            $groupName = $order['order_group']['name'] ?? ($order['group_name'] ?? '—');
            $groupId = $order['order_group']['id'] ?? ($order['order_group_id'] ?? null);
        @endphp
        <div wire:key="operation-order-card-{{ $order['id'] }}"
             class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm space-y-3">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 space-y-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        @if($showViewAction)
                            <x-orders.id-link :order-id="$order['id']" />
                        @else
                            <span class="font-mono font-bold text-gray-800">#{{ $order['id'] }}</span>
                        @endif
                        @if(!empty($order['is_package']) || !empty($order['package_subscription_id']))
                            <x-package-badge :title="$order['package_name'] ?? 'Meal package'" />
                        @endif
                        @if(!empty($order['has_complaint']))
                            <span class="inline-flex items-center rounded-full bg-rose-50 text-rose-700 border border-rose-200 px-2 py-0.5 text-[10px] font-black uppercase tracking-wider">
                                Complaint
                            </span>
                        @endif
                    </div>
                    <p class="text-sm font-semibold text-gray-800 break-words">{{ $menuName }}</p>
                    <p class="text-xs text-gray-500">{{ $customerName }}</p>
                </div>
                <p class="shrink-0 text-base font-black text-middo-orange tabular-nums">
                    ×{{ $order['quantity'] ?? 1 }}
                </p>
            </div>

            <div class="grid grid-cols-2 gap-2 text-xs border-t border-gray-100 pt-3">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Date</p>
                    <p class="font-semibold text-gray-700">{{ \Carbon\Carbon::parse($order['delivery_date'])->format('M d') }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Window</p>
                    <p class="font-semibold text-gray-700">{{ $order['delivery_time'] ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Status</p>
                    <p class="font-semibold text-gray-700">{{ $order['order_status'] ?? 'pending' }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Total</p>
                    <p class="font-bold text-gray-900">৳{{ number_format($order['total_amount'] ?? 0, 0) }}</p>
                </div>
            </div>

            @if($showGroup && $groupName !== '—')
                <p class="text-xs text-gray-500">
                    Group:
                    @if($groupId && $groupName !== 'Ungrouped')
                        <x-orders.group-link :group-id="$groupId" :name="$groupName" class="text-xs" />
                    @else
                        <span class="font-semibold text-middo-orange">{{ $groupName }}</span>
                    @endif
                </p>
            @endif

            <p class="text-xs text-gray-500 line-clamp-2">{{ $order['address'] ?? '—' }}</p>

            <div class="flex flex-wrap gap-2 text-[11px]">
                <span class="inline-flex px-2 py-1 rounded-md font-bold uppercase tracking-wide {{ ($order['payment_status'] ?? 'pending') === 'paid' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200/70' : 'bg-red-50 text-red-700 border border-red-200/70' }}">
                    {{ $order['payment_status'] ?? 'pending' }}
                </span>
                <span class="inline-flex px-2 py-1 rounded-md font-bold tracking-wide bg-sky-50 text-sky-800 border border-sky-200/70">
                    {{ $methodLabel }}
                </span>
            </div>

            @if($showViewAction)
                <x-orders.view-link :order-id="$order['id']" class="w-full inline-flex justify-center" />
            @endif
        </div>
    @empty
        <div class="rounded-2xl border border-gray-100 bg-white p-10 text-center text-sm font-semibold text-gray-400 italic">
            {{ $emptyMessage }}
        </div>
    @endforelse
</div>

{{-- Desktop table --}}
<div class="hidden md:block bg-white shadow-md border border-gray-100 rounded-2xl overflow-hidden">
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
                    <th class="p-4">Method</th>
                    <th class="p-4 text-right">Total</th>
                    @if($showViewAction)
                        <th class="p-4 text-right sticky right-0 bg-gray-50">Action</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-sm">
                @forelse($orders as $order)
                    @php
                        $methodLabel = $order['payment_method_label']
                            ?? \App\Support\OrderPaymentMethod::label($order['payment_method'] ?? null);
                        if ($methodLabel === '—' && ($order['payment_status'] ?? 'pending') === 'pending') {
                            $methodLabel = 'Cash on Delivery';
                        }
                        $customerName = $order['customer_name']
                            ?? (! empty($order['user'])
                                ? (trim(($order['user']['first_name'] ?? '').' '.($order['user']['last_name'] ?? '')) ?: 'N/A')
                                : ($order['receiver_name'] ?? 'N/A'));
                        $menuName = $order['menu_item']['name'] ?? ($order['menu_name'] ?? 'Custom Selection');
                        $groupName = $order['order_group']['name'] ?? ($order['group_name'] ?? '—');
                        $groupId = $order['order_group']['id'] ?? ($order['order_group_id'] ?? null);
                    @endphp
                    <tr wire:key="operation-order-row-{{ $order['id'] }}" class="hover:bg-gray-50/70 transition">
                        <td class="p-4 font-mono font-semibold text-gray-800">
                            <div class="flex items-center gap-2 flex-wrap">
                                @if($showViewAction)
                                    <x-orders.id-link :order-id="$order['id']" />
                                @else
                                    <span class="font-mono font-bold text-gray-800">#{{ $order['id'] }}</span>
                                @endif
                                @if(!empty($order['is_package']) || !empty($order['package_subscription_id']))
                                    <x-package-badge :title="$order['package_name'] ?? 'Meal package'" />
                                @endif
                                @if(!empty($order['has_complaint']))
                                    @if($complaintClickable)
                                        <button
                                            type="button"
                                            @click="$dispatch('open-complaint-support-modal', { orderId: {{ (int) $order['id'] }} })"
                                            class="inline-flex items-center gap-1 rounded-full bg-rose-50 text-rose-700 border border-rose-200 px-2 py-0.5 text-[10px] font-black uppercase tracking-wider hover:bg-rose-100 transition"
                                            title="View complaint conversation"
                                        >
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                                            </svg>
                                            Complaint
                                        </button>
                                    @else
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-rose-50 text-rose-700 border border-rose-200 px-2 py-0.5 text-[10px] font-black uppercase tracking-wider"
                                            title="Complaint raised on this order"
                                        >
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                                            </svg>
                                            Complaint
                                        </span>
                                    @endif
                                @endif
                            </div>
                        </td>
                        @if($showGroup)
                            <td class="p-4 text-xs">
                                @if($groupId && $groupName !== '—' && $groupName !== 'Ungrouped')
                                    <x-orders.group-link :group-id="$groupId" :name="$groupName" class="text-xs" />
                                @else
                                    <span class="font-semibold text-middo-orange">{{ $groupName }}</span>
                                @endif
                            </td>
                        @endif
                        <td class="p-4 font-medium text-gray-700">
                            {{ \Carbon\Carbon::parse($order['delivery_date'])->format('M d, Y') }}
                        </td>
                        <td class="p-4 text-gray-600">{{ $order['delivery_time'] ?? '—' }}</td>
                        <td class="p-4 font-medium text-gray-800">
                            @php
                                $holder = !empty($order['user'])
                                    ? (trim(($order['user']['first_name'] ?? '').' '.($order['user']['last_name'] ?? '')) ?: 'N/A')
                                    : ($order['account_holder_name'] ?? $customerName);
                                $receiverName = trim((string) ($order['receiver_name'] ?? ''));
                                $receiverMobile = trim((string) ($order['receiver_mobile'] ?? ''));
                                $separate = !empty($order['has_separate_receiver'])
                                    || ($receiverName !== '' && mb_strtolower($receiverName) !== mb_strtolower($holder));
                            @endphp
                            @if($separate)
                                <div class="space-y-0.5">
                                    <div><span class="text-[10px] uppercase text-gray-400 font-bold">Receiver</span> {{ $receiverName !== '' ? $receiverName : $customerName }}@if($receiverMobile) · {{ $receiverMobile }}@endif</div>
                                    <div class="text-xs text-gray-500"><span class="text-[10px] uppercase text-gray-400 font-bold">Account</span> {{ $holder }}</div>
                                </div>
                            @else
                                {{ $holder }}
                            @endif
                        </td>
                        <td class="p-4 font-semibold text-gray-800">
                            {{ $menuName }}
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
                        <td class="p-4">
                            <div class="flex flex-col items-start gap-1.5">
                                <span class="inline-flex px-2 py-1 rounded-md text-[11px] font-bold tracking-wide bg-sky-50 text-sky-800 border border-sky-200/70">
                                    {{ $methodLabel }}
                                </span>
                                @if(!empty($order['can_pay_online']) && !empty($order['online_payment_url']))
                                    @php
                                        $payLabel = 'Make Payment';
                                        if (! empty($order['amount_due'])) {
                                            $payLabel .= ' · ৳'.number_format((int) $order['amount_due'], 0);
                                        }
                                    @endphp
                                    <a
                                        href="{{ $order['online_payment_url'] }}"
                                        data-testid="make-payment-button"
                                        class="inline-flex items-center gap-1 rounded-lg bg-middo-orange hover:bg-[#733614] text-white text-[10px] font-black uppercase tracking-wider px-2.5 py-1.5 shadow-sm transition"
                                    >
                                        {{ $payLabel }}
                                    </a>
                                @endif
                            </div>
                        </td>
                        <td class="p-4 text-right font-mono font-bold text-gray-900">
                            ৳{{ number_format($order['total_amount'] ?? 0, 0) }}
                        </td>
                        @if($showViewAction)
                            <td class="p-4 text-right sticky right-0 bg-white">
                                <x-orders.view-link :order-id="$order['id']" />
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $emptyColspan }}" class="p-12 text-center text-sm font-semibold text-gray-400 italic">
                            {{ $emptyMessage }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
