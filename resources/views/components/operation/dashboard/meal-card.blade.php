@props([
    'order',
    'isHistory' => false
])

<div class="bg-[#FDFBF7] border border-[#EBE3D3] rounded-2xl overflow-hidden shadow-sm flex flex-col justify-between hover:shadow transition-shadow {{ $isHistory ? 'opacity-75 hover:opacity-100 transition-opacity' : '' }}">
    
    {{-- Product Snapshot Image Layer --}}
    <div class="relative w-full h-36 bg-[#ECE7DA] overflow-hidden">
        <img src="{{ asset($order['menu_item']['thumbnail']) }}" 
                 class="w-full h-full object-cover" 
                 alt="Meal Snapshot"
                 onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'%238A441B\' width=\'48\' height=\'48\'><path d=\'M11 9H9V2H7v7H5V2H3v7c0 2.12 1.66 3.84 3.75 3.97V22h2.5v-9.03C11.34 12.84 13 11.12 13 9V2h-2v7zm5-3v8h2.5v8h2.5V2c-2.76 0-5 2.24-5 4z\'/></svg>'; this.className='w-12 h-12 absolute inset-0 m-auto opacity-10';">
            
            {{-- Top Floating Quantity Bubble --}}
            <div class="absolute top-2.5 right-2.5 bg-middo-orange text-white text-[11px] font-black font-mono px-2 py-0.5 rounded-full shadow-sm">
                Qty: {{ $order['quantity'] ?? 1 }}
            </div>
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
                <span class="px-1.5 py-0.5 rounded border font-mono text-right {{ $isHistory ? 'bg-gray-50 text-gray-500 border-gray-200' : 'bg-amber-50 text-amber-900 border-amber-900/10' }}">
                    {{ $order['order_status'] ?? ($isHistory ? 'Archived' : 'Pending') }}
                </span>
            </div>

            {{-- Optional Contextual Payment Status (Omitted in history views for cleaner layout) --}}
            @if(!$isHistory)
                <div class="pt-1 text-[10px] text-[#635347] font-bold flex items-center justify-between uppercase tracking-wider">
                    <span class="text-left">Payment:</span>
                    @if(($order['payment_status'] ?? 'pending') === 'pending')
                        <span class="bg-red-50 text-red-700 px-1.5 py-0.5 rounded border border-red-200/60 font-mono text-right">Pending</span>
                    @else
                        <span class="bg-emerald-50 text-emerald-700 px-1.5 py-0.5 rounded border border-emerald-200/60 font-mono text-right">Paid</span>
                    @endif
                </div>
            @endif
        </div>

        {{-- CONTEXTUAL WORKFLOW ACTION BUTTON FOOTERS --}}
        <div class="pt-3.5 border-t border-dashed border-gray-100 mt-3 space-y-2">
           

            <div class="grid grid-cols-2 gap-2">
                <button class="w-full text-xs font-bold text-[#635347] bg-[#F7F4EB] hover:bg-[#EFE9DC] py-2.5 px-1 rounded-xl transition flex items-center justify-center gap-1.5 border border-[#EBE3D3]/60 shadow-sm active:scale-[0.98]">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                    <span>Repeat Order</span>
                </button>
                <button class="w-full text-xs font-bold text-white bg-[#1E4630] hover:bg-[#143021] py-2.5 px-1 rounded-xl transition flex items-center justify-center gap-1.5 shadow-sm active:scale-[0.98]">
                    <svg class="w-3.5 h-3.5 {{ ($order['order_status'] ?? '') === 'In Transit' ? 'animate-pulse' : '' }}" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25s-7.5-4.108-7.5-11.25a7.5 7.5 0 1115 0z" />
                    </svg>
                    <span>Track Order</span>
                </button>
            </div>
        </div>
    </div>
</div>