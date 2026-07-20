@php
    $status = $order->order_status ?? 'pending';
    $payment = $order->payment_status ?? 'pending';
@endphp

<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="space-y-2">
        <a href="{{ $this->backRoute() }}" class="text-sm font-semibold text-middo-orange hover:underline">← Back to orders</a>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-3xl font-bold text-middo-dark font-mono">Order #{{ $order->id }}</h1>
                <p class="text-sm text-gray-500 mt-1">
                    {{ $order->delivery_date?->timezone('Asia/Dhaka')->format('l, M d, Y') }}
                    · {{ $order->delivery_time ?: '—' }}
                    · Qty {{ $order->quantity }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold uppercase bg-amber-50 text-amber-900 border border-amber-200">
                    {{ str_replace('_', ' ', $status) }}
                </span>
                <span @class([
                    'inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold uppercase border',
                    'bg-emerald-50 text-emerald-800 border-emerald-200' => $payment === 'paid',
                    'bg-red-50 text-red-700 border-red-200' => $payment !== 'paid',
                ])>
                    {{ $payment }}
                </span>
                @if($order->isPackageOrder())
                    <x-package-badge :title="$order->packageSubscription?->package?->name ?? 'Meal package'" />
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        {{-- Corporate --}}
        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-bold uppercase tracking-wider text-gray-400">Corporate</h2>
                @if($this->corporateShowRoute())
                    <a href="{{ $this->corporateShowRoute() }}" class="text-xs font-bold text-middo-orange hover:underline">Profile →</a>
                @endif
            </div>
            @if($corporate)
                <p class="text-lg font-black text-middo-dark">
                    {{ $corporate->company_name ?: trim($corporate->first_name.' '.$corporate->last_name) }}
                </p>
                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="text-[11px] font-bold uppercase text-gray-400">Contact</dt>
                        <dd class="font-semibold text-gray-800">{{ $corporate->first_name }} {{ $corporate->last_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-bold uppercase text-gray-400">Mobile</dt>
                        <dd class="font-mono font-semibold text-gray-800">{{ $corporate->mobile }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-bold uppercase text-gray-400">Balance</dt>
                        <dd class="font-mono font-bold text-middo-dark">৳{{ number_format((int) $corporate->balance) }}</dd>
                    </div>
                </dl>
            @else
                <p class="text-sm text-gray-400 italic">No corporate account linked.</p>
            @endif
        </div>

        {{-- Kitchen --}}
        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-bold uppercase tracking-wider text-gray-400">Kitchen</h2>
                <div class="flex items-center gap-3">
                    @if($this->kitchenShowRoute())
                        <a href="{{ $this->kitchenShowRoute() }}" class="text-xs font-bold text-middo-orange hover:underline">Profile →</a>
                    @endif
                    @if($this->kitchenOrdersRoute())
                        <a href="{{ $this->kitchenOrdersRoute() }}" class="text-xs font-bold text-gray-500 hover:text-middo-orange hover:underline">Orders →</a>
                    @endif
                </div>
            </div>
            @if($kitchen)
                <p class="text-lg font-black text-middo-dark">{{ $kitchen->name ?: trim($kitchen->first_name.' '.$kitchen->last_name) }}</p>
                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="text-[11px] font-bold uppercase text-gray-400">Mobile</dt>
                        <dd class="font-mono font-semibold text-gray-800">{{ $kitchen->mobile ?: '—' }}</dd>
                    </div>
                    @if($group)
                        <div>
                            <dt class="text-[11px] font-bold uppercase text-gray-400">Order group</dt>
                            <dd class="font-semibold text-gray-800">{{ $group->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-bold uppercase text-gray-400">Group menu</dt>
                            <dd class="font-semibold text-gray-800">
                                @if($group->menuItem && $this->menuShowRoute($group->menuItem))
                                    <a href="{{ $this->menuShowRoute($group->menuItem) }}" class="text-middo-orange hover:underline">
                                        {{ $group->menuItem->name }} →
                                    </a>
                                @else
                                    {{ $group->menuItem?->name ?: '—' }}
                                @endif
                            </dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-[11px] font-bold uppercase text-gray-400">Dispatched</dt>
                        <dd class="font-semibold text-gray-800">
                            {{ $order->dispatched_at?->timezone('Asia/Dhaka')->format('M d, Y g:i A') ?: 'Not yet' }}
                        </dd>
                    </div>
                </dl>
            @else
                <p class="text-sm text-gray-400 italic">No kitchen assigned yet.</p>
                @if($group)
                    <p class="text-xs text-gray-500">Group: {{ $group->name }} (unassigned kitchen)</p>
                @endif
            @endif
        </div>

        {{-- Delivery --}}
        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-bold uppercase tracking-wider text-gray-400">Delivery</h2>
                @if($this->deliveryShowRoute())
                    <a href="{{ $this->deliveryShowRoute() }}" class="text-xs font-bold text-middo-orange hover:underline">Profile →</a>
                @endif
            </div>
            @if($rider)
                <p class="text-lg font-black text-middo-dark">{{ $rider->name ?: trim($rider->first_name.' '.$rider->last_name) }}</p>
                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="text-[11px] font-bold uppercase text-gray-400">Mobile</dt>
                        <dd class="font-mono font-semibold text-gray-800">{{ $rider->mobile ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-bold uppercase text-gray-400">Status</dt>
                        <dd class="font-semibold text-gray-800">{{ str_replace('_', ' ', $status) }}</dd>
                    </div>
                </dl>
            @elseif($order->isAwaitingRiderAccept())
                <p class="text-sm font-semibold text-amber-800">Awaiting rider accept</p>
                <p class="text-xs text-gray-500">Kitchen has dispatched; no rider assigned yet.</p>
            @else
                <p class="text-sm text-gray-400 italic">No delivery rider linked.</p>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-4">
            <h2 class="text-lg font-bold text-middo-dark">Order details</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-[11px] font-bold uppercase text-gray-400">Menu</dt>
                    <dd class="mt-0.5">
                        @if($this->menuShowRoute())
                            <a href="{{ $this->menuShowRoute() }}" class="font-semibold text-middo-orange hover:underline">
                                {{ $order->menuItem?->name ?? 'Custom Selection' }} →
                            </a>
                        @else
                            <span class="font-semibold text-gray-800">{{ $order->menuItem?->name ?? 'Custom Selection' }}</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-[11px] font-bold uppercase text-gray-400">Quantity</dt>
                    <dd class="font-mono font-bold text-middo-orange mt-0.5">{{ $order->quantity }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-bold uppercase text-gray-400">Total</dt>
                    <dd class="font-mono font-bold text-gray-900 mt-0.5">৳{{ number_format((int) $order->total_amount) }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-bold uppercase text-gray-400">Payment method</dt>
                    <dd class="font-semibold text-gray-800 mt-0.5">{{ $paymentMethodLabel }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-bold uppercase text-gray-400">Paid / Due</dt>
                    <dd class="font-mono font-semibold text-gray-800 mt-0.5">
                        ৳{{ number_format($party['amount_paid'] ?? 0) }}
                        / ৳{{ number_format($party['amount_due'] ?? 0) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-[11px] font-bold uppercase text-gray-400">Cash collected</dt>
                    <dd class="font-mono font-semibold text-gray-800 mt-0.5">৳{{ number_format($party['cash_collected'] ?? 0) }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-[11px] font-bold uppercase text-gray-400">Delivery address</dt>
                    <dd class="font-semibold text-gray-800 mt-0.5">{{ $order->address ?: '—' }}</dd>
                    <dd class="text-xs text-gray-500 mt-1">
                        {{ $order->area?->name ?: '—' }}@if($order->area?->city), {{ $order->area->city->name }}@endif
                    </dd>
                </div>
                <div>
                    <dt class="text-[11px] font-bold uppercase text-gray-400">Receiver</dt>
                    <dd class="font-semibold text-gray-800 mt-0.5">
                        {{ $party['receiver_name'] ?: '—' }}
                        @if(!empty($party['receiver_mobile']))
                            · {{ $party['receiver_mobile'] }}
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-[11px] font-bold uppercase text-gray-400">Account holder</dt>
                    <dd class="font-semibold text-gray-800 mt-0.5">
                        {{ $party['account_holder_name'] ?: '—' }}
                        @if(!empty($party['account_holder_mobile']))
                            · {{ $party['account_holder_mobile'] }}
                        @endif
                    </dd>
                </div>
                @if($this->subscriptionShowRoute())
                    <div class="sm:col-span-2">
                        <dt class="text-[11px] font-bold uppercase text-gray-400">Package subscription</dt>
                        <dd class="mt-0.5">
                            <a href="{{ $this->subscriptionShowRoute() }}" class="text-sm font-bold text-middo-orange hover:underline">
                                #{{ $order->package_subscription_id }} · {{ $order->packageSubscription?->package?->name ?? 'Package' }} →
                            </a>
                        </dd>
                    </div>
                @endif
            </dl>
        </div>

        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-lg font-bold text-middo-dark">Tracking log</h2>
                <p class="text-xs font-semibold text-gray-400 mt-0.5">Newest first</p>
            </div>
            <div class="divide-y divide-gray-100 max-h-[32rem] overflow-y-auto">
                @forelse($logs as $log)
                    <div class="px-5 py-4 flex gap-3">
                        <div class="mt-1.5 w-2.5 h-2.5 rounded-full bg-middo-orange shrink-0"></div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm font-bold text-middo-dark">{{ $log['title'] }}</p>
                                <p class="text-[11px] font-semibold text-gray-400 whitespace-nowrap">{{ $log['at_label'] }}</p>
                            </div>
                            <p class="text-sm text-gray-600 mt-0.5">{{ $log['description'] }}</p>
                            @if(!empty($log['performer_name']))
                                <p class="text-[11px] font-semibold text-gray-400 mt-1">by {{ $log['performer_name'] }}</p>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-sm font-semibold text-gray-400 italic">
                        No tracking events yet.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
