<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="space-y-2">
        <a href="{{ $this->indexRoute() }}" class="text-sm font-semibold text-middo-orange hover:underline">← Corporates</a>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-3xl font-bold text-middo-dark">
                    {{ $corporate->company_name ?: trim($corporate->first_name.' '.$corporate->last_name) }}
                </h1>
                <p class="text-sm text-gray-500 mt-1">
                    {{ $corporate->first_name }} {{ $corporate->last_name }}
                    · {{ $corporate->mobile }}
                    @if($corporate->email)
                        · {{ $corporate->email }}
                    @endif
                </p>
            </div>
            <span @class([
                'px-2.5 py-1 rounded-full text-[11px] font-bold uppercase',
                'bg-emerald-100 text-emerald-800 border border-emerald-200' => $corporate->status === 'active',
                'bg-yellow-100 text-yellow-800 border border-yellow-200' => $corporate->status === 'pending',
                'bg-gray-100 text-gray-600 border border-gray-200' => ! in_array($corporate->status, ['active', 'pending'], true),
            ])>
                {{ $corporate->status }}
            </span>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Balance</p>
            <p class="text-2xl font-black text-middo-dark font-mono">৳{{ number_format($stats['balance']) }}</p>
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Total orders</p>
            <p class="text-2xl font-black text-middo-dark">{{ number_format($stats['total_orders']) }}</p>
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Upcoming</p>
            <p class="text-2xl font-black text-middo-dark">{{ number_format($stats['upcoming_orders']) }}</p>
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Active packages</p>
            <p class="text-2xl font-black text-middo-dark">{{ number_format($stats['active_subscriptions']) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1 bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-4">
            <h2 class="text-lg font-bold text-middo-dark">Profile details</h2>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Company</dt>
                    <dd class="font-semibold text-gray-800 mt-0.5">{{ $corporate->company_name ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Contact name</dt>
                    <dd class="font-semibold text-gray-800 mt-0.5">{{ $corporate->first_name }} {{ $corporate->last_name }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Mobile</dt>
                    <dd class="font-mono font-semibold text-gray-800 mt-0.5">{{ $corporate->mobile }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Email</dt>
                    <dd class="font-semibold text-gray-800 mt-0.5">{{ $corporate->email ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Address</dt>
                    <dd class="font-semibold text-gray-800 mt-0.5">{{ $corporate->address ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Location</dt>
                    <dd class="font-semibold text-gray-800 mt-0.5">
                        {{ $corporate->area_name ?: '—' }}@if($corporate->city_name), {{ $corporate->city_name }}@endif
                    </dd>
                </div>
                <div>
                    <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Joined</dt>
                    <dd class="font-semibold text-gray-800 mt-0.5">
                        {{ $corporate->created_at?->timezone('Asia/Dhaka')->format('M d, Y') ?: '—' }}
                    </dd>
                </div>
            </dl>
        </div>

        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                    <h2 class="text-lg font-bold text-middo-dark">Wallet activity</h2>
                    <span class="text-sm font-mono font-bold text-middo-dark">৳{{ number_format($stats['balance']) }}</span>
                </div>
                <div class="divide-y divide-gray-100 max-h-80 overflow-y-auto">
                    @forelse($transactions as $tx)
                        <div class="px-5 py-3.5 flex items-center justify-between gap-4">
                            <div class="min-w-0">
                                <div class="text-sm font-bold text-gray-800 truncate">{{ $tx->description ?: ucfirst($tx->type) }}</div>
                                <div class="text-[11px] font-semibold text-gray-400 mt-0.5">
                                    {{ $tx->created_at?->timezone('Asia/Dhaka')->format('M d, Y g:i A') }} · {{ $tx->type }}
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <div class="text-sm font-black font-mono {{ in_array($tx->type, ['topup', 'refund'], true) ? 'text-emerald-700' : 'text-middo-orange' }}">
                                    {{ in_array($tx->type, ['topup', 'refund'], true) ? '+' : '−' }}৳{{ number_format($tx->amount) }}
                                </div>
                                <div class="text-[10px] font-bold text-gray-400 font-mono">Bal ৳{{ number_format($tx->balance_after) }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-10 text-center text-sm font-semibold text-gray-400 italic">
                            No wallet activity yet.
                        </div>
                    @endforelse
                </div>
            </div>

            @if($subscriptions->isNotEmpty())
                <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100">
                        <h2 class="text-lg font-bold text-middo-dark">Subscriptions</h2>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        @foreach($subscriptions as $subscription)
                            <li class="px-5 py-3.5 flex flex-wrap items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <a href="{{ $this->subscriptionShowRoute($subscription->id) }}" class="text-sm font-bold text-middo-dark hover:text-middo-orange transition">
                                        #{{ $subscription->id }} · {{ $subscription->package?->name ?? 'Package' }}
                                    </a>
                                    <div class="text-[11px] font-semibold text-gray-400 mt-0.5">
                                        {{ $subscription->start_date?->format('M d, Y') }} – {{ $subscription->end_date?->format('M d, Y') }}
                                        · qty {{ $subscription->quantity }}
                                    </div>
                                </div>
                                <span @class([
                                    'px-2 py-1 rounded-md text-[11px] font-bold uppercase',
                                    'bg-emerald-50 text-emerald-800 border border-emerald-200' => $subscription->status === 'active',
                                    'bg-gray-100 text-gray-600 border border-gray-200' => $subscription->status !== 'active',
                                ])>
                                    {{ $subscription->status }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>

    <div class="space-y-3">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-lg font-bold text-middo-dark">Order history</h2>
            <p class="text-xs font-semibold text-gray-400">Newest first</p>
        </div>
        <x-operation.orders.table :orders="$orderRows" empty-message="No orders for this corporate yet." />
        @if($orders->hasPages())
            <div class="px-1">
                {{ $orders->links() }}
            </div>
        @endif
    </div>
</div>
