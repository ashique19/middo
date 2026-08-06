@props([
    'order',
    'isHistory' => false,
    'showActions' => true,
    'showCustomer' => false,
])

<div class="bg-[#FDFBF7] border border-[#EBE3D3] rounded-2xl overflow-hidden shadow-sm flex flex-col justify-between hover:shadow transition-shadow {{ $isHistory ? 'opacity-75 hover:opacity-100 transition-opacity' : '' }}">
    
    {{-- Product Snapshot Image Layer --}}
    <div class="relative w-full h-36 bg-[#ECE7DA] overflow-hidden">
        <img src="{{ asset($order['menu_item']['thumbnail'] ?? 'img/public/how-it-works-corporates.jpg') }}" 
                 class="w-full h-full object-cover" 
                 alt="Meal Snapshot"
                 onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'%238A441B\' width=\'48\' height=\'48\'><path d=\'M11 9H9V2H7v7H5V2H3v7c0 2.12 1.66 3.84 3.75 3.97V22h2.5v-9.03C11.34 12.84 13 11.12 13 9V2h-2v7zm5-3v8h2.5v8h2.5V2c-2.76 0-5 2.24-5 4z\'/></svg>'; this.className='w-12 h-12 absolute inset-0 m-auto opacity-10';">
            
            {{-- Top Floating Quantity Bubble --}}
            <div class="absolute top-2.5 right-2.5 bg-middo-orange text-white text-[11px] font-black font-mono px-2 py-0.5 rounded-full shadow-sm">
                Qty: {{ $order['quantity'] ?? 1 }}
            </div>

            @if(!empty($order['has_complaint']))
                <div
                    class="absolute top-2.5 left-2.5 inline-flex items-center gap-1 bg-rose-600 text-white text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full shadow-sm"
                    title="A complaint or support request was raised on this order"
                >
                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                    <span>Complaint</span>
                </div>
            @endif
    </div>

    {{-- Metadata Ledger Content Box --}}
    <div class="p-4 flex-1 flex flex-col justify-between space-y-2 text-xs font-medium text-[#2B1A11]">
        <div class="space-y-2">
            {{-- Date Entry Row --}}
            <div class="grid grid-cols-12 gap-1 pb-1.5 border-b border-gray-100 items-center">
                <span class="col-span-4 text-gray-400 font-bold text-left">Date</span>
                <span class="col-span-8 font-black text-right text-middo-orange">
                    {{ \Carbon\Carbon::parse($order['delivery_date'])->format('M d, Y') }}
                </span>
            </div>

            {{-- Menu Selection Entry Row --}}
            <div class="grid grid-cols-12 gap-1 py-0.5 items-center">
                <span class="col-span-4 text-gray-400 font-bold text-left">Menu</span>
                <span class="col-span-8 font-extrabold tracking-tight truncate text-right">
                    {{ $order['menu_item']['name'] ?? 'Custom Selection' }}
                </span>
            </div>

            @if($showCustomer && !empty($order['user']))
                <div class="grid grid-cols-12 gap-1 py-0.5 items-center">
                    <span class="col-span-4 text-gray-400 font-bold text-left">Customer</span>
                    <span class="col-span-8 font-bold truncate text-right">
                        {{ trim(($order['user']['first_name'] ?? '') . ' ' . ($order['user']['last_name'] ?? '')) ?: 'N/A' }}
                    </span>
                </div>
                <div class="grid grid-cols-12 gap-1 py-0.5 items-center">
                    <span class="col-span-4 text-gray-400 font-bold text-left">Address</span>
                    <span class="col-span-8 font-medium text-right text-[#635347] line-clamp-2">
                        {{ $order['address'] ?? 'N/A' }}
                    </span>
                </div>
            @endif

            {{-- Time/Fulfillment Window Entry Row --}}
            <div class="grid grid-cols-12 gap-1 py-0.5 items-center">
                <span class="col-span-4 text-gray-400 font-bold text-left">Window</span>
                <span class="col-span-8 font-bold text-right {{ $isHistory ? 'text-[#635347]' : 'text-emerald-800' }}">
                    {{ $isHistory ? '⏰ Delivered — ' : '⏰ Timeline — ' }}{{ $order['delivery_time'] ?? '12:00 PM' }}
                </span>
            </div>

            {{-- Order Financial Totals Row --}}
            <div class="grid grid-cols-12 gap-1 pt-1.5 border-t border-dashed border-gray-200 text-sm items-center">
                <span class="col-span-5 text-[#635347] font-black text-left">Order Total</span>
                <span class="col-span-7 font-black text-right font-mono text-[#2B1A11]">
                    ৳{{ number_format($order['total_amount'], 0) }}
                </span>
            </div>
            
            {{-- Bottom Status Badge Row --}}
            <div class="pt-2 text-[10px] text-[#635347] font-bold flex items-center justify-between uppercase tracking-wider">
                <span class="text-left">Status:</span>
                <div class="flex items-center gap-1.5">
                    @if($showActions && ($order['order_status'] ?? '') === 'pending' && ! \App\Support\OrderCutoff::isPastForDeliveryDate($order['delivery_date'] ?? now()))
                        <button
                            type="button"
                            @click="$dispatch('open-delete-order-modal', { orderId: {{ $order['id'] }} })"
                            aria-label="Delete order"
                            class="p-1 rounded-md text-red-600 bg-red-50 hover:bg-red-100 border border-red-200/80 transition active:scale-95">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                        </button>
                    @endif
                    <span class="px-1.5 py-0.5 rounded border font-mono text-right {{ $isHistory ? 'bg-gray-50 text-gray-500 border-gray-200' : 'bg-amber-50 text-amber-900 border-amber-900/10' }}">
                        {{ $order['order_status'] ?? ($isHistory ? 'Archived' : 'Pending') }}
                    </span>
                </div>
            </div>

            {{-- Optional Contextual Payment Status (Omitted in history views for cleaner layout) --}}
            @if(!$isHistory)
                @php
                    $paymentMethodLabel = $order['payment_method_label']
                        ?? \App\Support\OrderPaymentMethod::label($order['payment_method'] ?? null);
                    if ($paymentMethodLabel === '—' && ($order['payment_status'] ?? 'pending') === 'pending') {
                        $paymentMethodLabel = 'Cash on Delivery';
                    }
                @endphp
                <div class="pt-1 text-[10px] text-[#635347] font-bold flex items-center justify-between uppercase tracking-wider gap-2">
                    <span class="text-left shrink-0">Payment:</span>
                    <div class="flex items-center gap-1.5 flex-wrap justify-end">
                        @if($paymentMethodLabel !== '—')
                            <span class="bg-sky-50 text-sky-800 px-1.5 py-0.5 rounded border border-sky-200/60 font-mono text-right normal-case tracking-normal">{{ $paymentMethodLabel }}</span>
                        @endif
                        @if(($order['payment_status'] ?? 'pending') === 'pending')
                            <span class="bg-red-50 text-red-700 px-1.5 py-0.5 rounded border border-red-200/60 font-mono text-right">Pending</span>
                        @else
                            <span class="bg-emerald-50 text-emerald-700 px-1.5 py-0.5 rounded border border-emerald-200/60 font-mono text-right">Paid</span>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        {{-- CONTEXTUAL WORKFLOW ACTION BUTTON FOOTERS --}}
        @if($showActions)
        <div class="pt-3.5 border-t border-dashed border-gray-100 mt-3 space-y-2">
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
                    class="w-full text-xs font-black text-white bg-middo-orange hover:bg-[#733614] py-2.5 px-1 rounded-xl transition flex items-center justify-center gap-1.5 shadow-sm active:scale-[0.98]">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                    </svg>
                    <span>{{ $payLabel }}</span>
                </a>
            @endif
            <div class="grid grid-cols-2 gap-2">
                @php
                    $repeatDishId = $order['menu_item_id'] ?? ($order['menu_item']['id'] ?? null);
                @endphp
                @if($repeatDishId)
                <button
                    type="button"
                    @click="$dispatch('openOrderModal', { dishId: {{ $repeatDishId }} })"
                    class="w-full text-xs font-bold text-[#635347] bg-[#F7F4EB] hover:bg-[#EFE9DC] py-2.5 px-1 rounded-xl transition flex items-center justify-center gap-1.5 border border-[#EBE3D3]/60 shadow-sm active:scale-[0.98]">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                    <span>Repeat Order</span>
                </button>
                @endif
                <button
                    type="button"
                    @click="$dispatch('open-track-order-modal', { orderId: {{ $order['id'] }} })"
                    class="w-full text-xs font-bold text-white bg-[#1E4630] hover:bg-[#143021] py-2.5 px-1 rounded-xl transition flex items-center justify-center gap-1.5 shadow-sm active:scale-[0.98] {{ $repeatDishId ? '' : 'col-span-2' }}">
                    <svg class="w-3.5 h-3.5 {{ ($order['order_status'] ?? '') === 'on_the_way_to_delivery' ? 'animate-pulse' : '' }}" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25s-7.5-4.108-7.5-11.25a7.5 7.5 0 1115 0z" />
                    </svg>
                    <span>Track Order</span>
                </button>
            </div>
            <button
                type="button"
                @click="$dispatch('open-complaint-support-modal', { orderId: {{ $order['id'] }} })"
                @class([
                    'w-full text-xs font-bold py-2.5 px-1 rounded-xl transition flex items-center justify-center gap-1.5 border shadow-sm active:scale-[0.98]',
                    'text-rose-800 bg-rose-50 hover:bg-rose-100 border-rose-200' => !empty($order['has_complaint']),
                    'text-[#8A441B] bg-amber-50 hover:bg-amber-100 border-amber-200/80' => empty($order['has_complaint']),
                ])>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 0 1-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8Z" />
                </svg>
                <span>{{ !empty($order['has_complaint']) ? 'Complaint raised — view' : 'Complaint / Support' }}</span>
            </button>
        </div>
        @endif
    </div>
</div>