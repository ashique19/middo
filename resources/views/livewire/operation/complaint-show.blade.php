<div class="max-w-3xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="space-y-2">
        <a href="{{ route($rolePrefix.'.complaints.index') }}" class="text-sm font-semibold text-middo-orange hover:underline">← Complaints</a>
        <h1 class="text-3xl font-bold text-middo-dark">Complaint #{{ $complaint->id }}</h1>
        <p class="text-sm font-semibold text-gray-500">
            Order
            <a href="{{ $orderShowUrl }}" class="text-middo-orange hover:underline font-mono">#{{ $order?->id }}</a>
            · {{ $order?->menuItem?->name ?? 'Menu' }} ·
            {{ \App\Support\KitchenComplaints::categoryLabel($complaint->category) }}
        </p>
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
            {{ $statusMessage }}
        </div>
    @endif
    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
            {{ $errorMessage }}
        </div>
    @endif

    <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm text-sm text-gray-600 space-y-1">
        <p><span class="font-bold text-middo-dark">Customer:</span> {{ $order?->user?->name ?? '—' }}</p>
        <p><span class="font-bold text-middo-dark">Kitchen:</span> {{ $order?->orderGroup?->kitchen?->name ?? '—' }}</p>
        <p><span class="font-bold text-middo-dark">Delivery:</span>
            {{ $order?->delivery_date?->format('M j, Y') }} · {{ $order?->delivery_time }}
        </p>
        <p><span class="font-bold text-middo-dark">Status:</span> {{ str_replace('_', ' ', $order?->order_status ?? '—') }}</p>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm divide-y divide-gray-100 overflow-hidden">
        @foreach($messages as $entry)
            <div class="px-4 py-4 space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-[11px] font-black uppercase tracking-wide {{ $entry->is_reply ? 'text-emerald-700' : 'text-middo-orange' }}">
                        {{ $entry->is_reply ? 'Middo support' : 'Customer' }}
                    </span>
                    <span class="text-xs text-gray-400">
                        {{ $entry->created_at?->timezone('Asia/Dhaka')->format('M j · g:i A') }}
                    </span>
                    @if($entry->createdBy)
                        <span class="text-xs text-gray-400">· {{ $entry->createdBy->name }}</span>
                    @endif
                </div>
                <p class="text-sm font-semibold text-gray-800 whitespace-pre-line">{{ $entry->message }}</p>
            </div>
        @endforeach
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-3">
        <h2 class="text-sm font-bold uppercase tracking-wider text-gray-400">Ops reply</h2>
        <textarea
            wire:model="replyMessage"
            rows="4"
            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-800 focus:ring-middo-orange focus:border-middo-orange"
            placeholder="Reply to the customer…"></textarea>
        @error('replyMessage')
            <p class="text-xs font-semibold text-red-600">{{ $message }}</p>
        @enderror
        <button
            type="button"
            wire:click="reply"
            class="inline-flex items-center px-4 py-2 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-sm font-bold transition">
            Post reply
        </button>
    </div>
</div>
