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
