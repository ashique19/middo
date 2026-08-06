<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="space-y-2">
        <a href="{{ $this->backRoute() }}" class="text-sm font-semibold text-middo-orange hover:underline">← Active orders</a>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-3xl font-bold text-middo-dark">{{ $group->name }}</h1>
                <p class="text-sm text-gray-500 mt-1">
                    Order group #{{ $group->id }}
                    · {{ $group->delivery_date?->timezone('Asia/Dhaka')->format('D, M d, Y') }}
                </p>
            </div>
            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold uppercase bg-orange-50 text-middo-orange border border-orange-200">
                Qty {{ $totalQty }} · {{ $activeOrders->count() }} order(s)
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Menu</p>
            @if($menuUrl = $this->menuShowRoute())
                <a href="{{ $menuUrl }}" class="text-lg font-bold text-middo-orange hover:underline">
                    {{ $group->menuItem?->name ?? '—' }}
                </a>
            @else
                <p class="text-lg font-bold text-gray-800">{{ $group->menuItem?->name ?? '—' }}</p>
            @endif
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Kitchen</p>
            @if($kitchenUrl = $this->kitchenShowRoute())
                <a href="{{ $kitchenUrl }}" class="text-lg font-bold text-middo-orange hover:underline">
                    {{ $group->kitchenDisplayName() }}
                </a>
            @else
                <p class="text-lg font-bold text-gray-800">{{ $group->kitchenDisplayName() }}</p>
            @endif
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Area</p>
            <p class="text-lg font-bold text-gray-800">
                {{ $group->area?->name ?: '—' }}
                @if($group->area?->city)
                    <span class="text-sm font-semibold text-gray-500">· {{ $group->area->city->name }}</span>
                @endif
            </p>
        </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-lg font-bold text-middo-dark">Orders in this group</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-gray-50 text-xs font-bold uppercase tracking-wider text-gray-400">
                    <tr>
                        <th class="p-4">Order</th>
                        <th class="p-4">Customer</th>
                        <th class="p-4">Menu</th>
                        <th class="p-4">Time</th>
                        <th class="p-4">Status</th>
                        <th class="p-4 text-right">Qty</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($orders as $order)
                        <tr wire:key="group-order-{{ $order->id }}" @class(['bg-red-50/40' => $order->order_status === 'cancelled'])>
                            <td class="p-4 font-mono font-semibold">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <x-orders.id-link :order-id="$order->id" />
                                    @if($order->package_subscription_id)
                                        <x-package-badge :title="$order->packageSubscription?->package?->name ?? 'Meal package'" />
                                    @endif
                                </div>
                            </td>
                            <td class="p-4 font-medium text-gray-800">
                                {{ $order->receiver_name ?: ($order->user?->name ?? '—') }}
                            </td>
                            <td class="p-4 text-gray-700">{{ $order->menuItem?->name ?? '—' }}</td>
                            <td class="p-4 text-gray-600">{{ $order->delivery_time }}</td>
                            <td class="p-4 capitalize">{{ $order->order_status }}</td>
                            <td class="p-4 text-right font-bold text-middo-orange">{{ $order->quantity }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-sm text-gray-500">No orders in this group.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($group->events->isNotEmpty())
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-lg font-bold text-middo-dark">Group events</h2>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach($group->events as $event)
                    <li wire:key="group-event-{{ $event->id }}" class="px-5 py-3 text-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-bold text-middo-dark capitalize">{{ str_replace('_', ' ', $event->type) }}</span>
                            <span class="text-xs text-gray-400">
                                {{ $event->created_at?->timezone('Asia/Dhaka')->format('M d, Y g:i A') }}
                            </span>
                            @if($event->createdBy)
                                <span class="text-xs text-gray-400">· {{ $event->createdBy->name }}</span>
                            @endif
                        </div>
                        @if($event->reason)
                            <p class="text-gray-600 mt-1">{{ $event->reason }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
