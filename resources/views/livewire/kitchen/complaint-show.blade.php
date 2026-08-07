<div class="max-w-3xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="space-y-2">
        <a href="{{ route('kitchen.complaints') }}" class="text-sm font-semibold text-middo-orange hover:underline">← Complaints</a>
        <h1 class="text-2xl sm:text-3xl font-bold text-middo-dark">Complaint #{{ $complaint->id }}</h1>
        <p class="text-sm font-semibold text-gray-500">
            Order #{{ $order?->id }} · {{ $order?->menuItem?->name ?? 'Menu' }} ·
            {{ \App\Support\KitchenComplaints::categoryLabel($complaint->category) }}
        </p>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm text-sm text-gray-600 space-y-1">
        <p><span class="font-bold text-middo-dark">Area:</span> {{ $order?->area?->name ?? $order?->orderGroup?->area?->name ?? '—' }}</p>
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
                </div>
                @if(! $entry->is_reply && $entry->category)
                    <p class="text-xs font-semibold text-gray-500">
                        {{ \App\Support\KitchenComplaints::categoryLabel($entry->category) }}
                    </p>
                @endif
                <p class="text-sm font-semibold text-gray-800 whitespace-pre-line">{{ $entry->message }}</p>
                @if($entry->attachment)
                    <a href="{{ asset($entry->attachment) }}" target="_blank" rel="noopener noreferrer"
                       class="text-xs font-bold text-middo-orange hover:underline">Attachment</a>
                @endif
            </div>
        @endforeach
    </div>

    <p class="text-xs text-gray-400 italic">Read-only. Contact Middo ops if you need to respond.</p>
</div>
