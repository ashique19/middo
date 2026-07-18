<div class="max-w-7xl mx-auto py-10 px-6 space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
            <a href="{{ route('delivery.dashboard') }}" class="text-sm font-semibold text-middo-orange hover:underline">← Dashboard</a>
            <h1 class="text-3xl font-bold text-middo-dark">Delivered orders</h1>
            <p class="text-sm font-semibold text-gray-500">
                Collect payment and receive Middo boxes from the customer.
            </p>
        </div>
        <x-orders.view-mode-toggle :view-mode="$viewMode" />
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
            {{ $statusMessage }}
        </div>
    @endif

    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">
            {{ $errorMessage }}
        </div>
    @endif

    <livewire:delivery.payment-modal />

    @if($viewMode === 'list')
        <x-operation.orders.table :orders="$nodes" empty-message="No delivered orders yet." />
        @if($orders->hasPages())
            <div class="mt-4 px-1">{{ $orders->links() }}</div>
        @endif
    @else
    @forelse($nodes as $order)
        <div
            wire:key="delivered-order-{{ $order['id'] }}"
            class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="flex flex-wrap items-start justify-between gap-4 px-5 py-4">
                <div class="space-y-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-mono font-black text-middo-dark">#{{ $order['id'] }}</span>
                        <span class="text-sm font-bold text-gray-700">{{ $order['menu_name'] }}</span>
                        <span class="text-xs font-bold text-middo-orange">Qty {{ $order['quantity'] }}</span>
                        <span class="text-xs font-bold text-gray-700">৳{{ number_format($order['total_amount']) }}</span>
                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                            {{ $order['status_label'] }}
                        </span>
                        @if(!empty($order['payment_method_label']))
                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-bold bg-sky-50 text-sky-800 border border-sky-200">
                                {{ $order['payment_method_label'] }}
                            </span>
                        @endif
                    </div>
                    <p class="text-sm text-gray-600">{{ $order['date_label'] }} · {{ $order['delivery_time'] }}</p>
                    <p class="text-sm text-gray-500">
                        @if(!empty($order['has_separate_receiver']))
                            <span class="font-semibold text-gray-700">Receiver:</span> {{ $order['receiver_name'] }}
                            @if(!empty($order['receiver_mobile'])) · {{ $order['receiver_mobile'] }}@endif
                            <br>
                            <span class="text-xs">Account: {{ $order['account_holder_name'] }}</span>
                        @else
                            {{ $order['customer_name'] }}
                        @endif
                        · {{ $order['address'] }}
                    </p>
                    @if(($order['amount_due'] ?? 0) > 0 && !($order['is_paid'] ?? false))
                        <p class="text-xs font-bold text-middo-orange">Due ৳{{ number_format($order['amount_due']) }}
                            @if(($order['amount_paid'] ?? 0) > 0)
                                <span class="text-emerald-700 font-semibold">(prepaid ৳{{ number_format($order['amount_paid']) }})</span>
                            @endif
                        </p>
                    @endif
                    @if(count($order['box_codes']) > 0)
                        <p class="text-xs font-mono text-gray-400">Boxes: {{ implode(', ', $order['box_codes']) }}</p>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    @if($order['is_paid'])
                        <span class="inline-flex px-3 py-1.5 rounded-xl text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                            Paid
                        </span>
                    @else
                        <button
                            type="button"
                            @click="$dispatch('open-delivery-payment-modal', { orderId: {{ $order['id'] }} })"
                            class="inline-flex items-center px-4 py-2 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-sm font-bold transition">
                            Payment
                        </button>
                    @endif

                    @if($order['boxes_received'])
                        <span class="inline-flex px-3 py-1.5 rounded-xl text-xs font-bold bg-gray-100 text-gray-600 border border-gray-200">
                            Boxes received
                        </span>
                    @else
                        <button
                            type="button"
                            wire:click="receiveBoxes({{ $order['id'] }})"
                            wire:loading.attr="disabled"
                            wire:target="receiveBoxes({{ $order['id'] }})"
                            wire:confirm="Confirm you received the Middo boxes from the customer?"
                            class="inline-flex items-center px-4 py-2 rounded-xl border border-gray-300 bg-white text-sm font-bold text-middo-dark hover:border-middo-orange hover:text-middo-orange transition disabled:opacity-60">
                            <span wire:loading.remove wire:target="receiveBoxes({{ $order['id'] }})">Receive Boxes</span>
                            <span wire:loading wire:target="receiveBoxes({{ $order['id'] }})">Receiving...</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="bg-white border border-gray-200 rounded-2xl p-12 text-center shadow-sm">
            <p class="text-sm font-semibold text-gray-400 italic">No delivered orders yet.</p>
        </div>
    @endforelse

    @if($orders->hasPages())
        <div class="mt-4 px-1">
            {{ $orders->links() }}
        </div>
    @endif
    @endif
</div>
