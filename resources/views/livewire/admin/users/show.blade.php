<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="space-y-2">
        <a href="{{ $this->listRoute() }}" class="text-sm font-semibold text-middo-orange hover:underline">
            ← {{ $this->listLabel() }}
        </a>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">
                    {{ $user->role->name ?? 'user' }} account
                </p>
                <h1 class="text-3xl font-bold text-middo-dark">{{ $displayName }}</h1>
                <p class="text-sm text-gray-500 mt-1">
                    {{ $user->first_name }} {{ $user->last_name }}
                    · {{ $user->mobile }}
                    @if($user->email)
                        · {{ $user->email }}
                    @endif
                </p>
            </div>
            <span @class([
                'px-2.5 py-1 rounded-full text-[11px] font-bold uppercase border',
                'bg-emerald-100 text-emerald-800 border-emerald-200' => $user->status === 'active',
                'bg-yellow-100 text-yellow-800 border-yellow-200' => $user->status === 'pending',
                'bg-gray-100 text-gray-600 border-gray-200' => ! in_array($user->status, ['active', 'pending'], true),
            ])>
                {{ $user->status }}
            </span>
        </div>
    </div>

    @if(count($relatedLinks))
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach($relatedLinks as $link)
                <a href="{{ $link['href'] }}"
                   class="block bg-white border border-gray-100 rounded-2xl p-4 shadow-sm hover:border-middo-orange transition group">
                    <p class="text-sm font-bold text-middo-dark group-hover:text-middo-orange transition">
                        {{ $link['label'] }} →
                    </p>
                    <p class="text-xs text-gray-500 mt-1">{{ $link['description'] }}</p>
                </a>
            @endforeach
        </div>
    @endif

    <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
        <h2 class="text-lg font-bold text-middo-dark mb-4">Profile details</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
            @if($user->company_name)
                <div>
                    <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Company</dt>
                    <dd class="font-semibold text-gray-800 mt-0.5">{{ $user->company_name }}</dd>
                </div>
            @endif
            <div>
                <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Name</dt>
                <dd class="font-semibold text-gray-800 mt-0.5">{{ $user->first_name }} {{ $user->last_name }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Mobile</dt>
                <dd class="font-mono font-semibold text-gray-800 mt-0.5">{{ $user->mobile ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Email</dt>
                <dd class="font-semibold text-gray-800 mt-0.5">{{ $user->email ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Role</dt>
                <dd class="font-semibold text-gray-800 mt-0.5 uppercase">{{ $user->role->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Address</dt>
                <dd class="font-semibold text-gray-800 mt-0.5">{{ $user->address ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Location</dt>
                <dd class="font-semibold text-gray-800 mt-0.5">
                    {{ $user->area_name ?: '—' }}@if($user->city_name), {{ $user->city_name }}@endif
                </dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Joined</dt>
                <dd class="font-semibold text-gray-800 mt-0.5">
                    {{ $user->created_at?->timezone('Asia/Dhaka')->format('M d, Y') ?: '—' }}
                </dd>
            </div>
        </dl>
    </div>

    @if($showOrders)
        <div class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-bold text-middo-dark">Related orders</h2>
                <p class="text-xs font-semibold text-gray-400">Newest first</p>
            </div>

            <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[720px]">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr class="text-xs font-semibold uppercase tracking-wider text-gray-500">
                                <th class="p-4">Order</th>
                                <th class="p-4">Menu</th>
                                <th class="p-4">Date</th>
                                <th class="p-4">Status</th>
                                <th class="p-4 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            @forelse($orderRows as $order)
                                <tr class="hover:bg-gray-50/80">
                                    <td class="p-4">
                                        <a href="{{ $order['show_url'] }}" class="font-bold text-middo-orange hover:underline">
                                            #{{ $order['id'] }}
                                        </a>
                                    </td>
                                    <td class="p-4">
                                        @if($order['menu_url'])
                                            <a href="{{ $order['menu_url'] }}" class="font-medium text-gray-800 hover:text-middo-orange transition">
                                                {{ $order['menu_name'] }}
                                            </a>
                                        @else
                                            <span class="font-medium text-gray-800">{{ $order['menu_name'] }}</span>
                                        @endif
                                    </td>
                                    <td class="p-4 whitespace-nowrap text-gray-600">
                                        {{ $order['delivery_date'] }}
                                        @if($order['delivery_time'])
                                            · {{ $order['delivery_time'] }}
                                        @endif
                                    </td>
                                    <td class="p-4">
                                        <span class="text-xs font-bold uppercase text-gray-700">{{ $order['order_status'] }}</span>
                                    </td>
                                    <td class="p-4 text-right font-mono font-semibold text-middo-dark whitespace-nowrap">
                                        ৳{{ number_format((int) $order['total_amount']) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="p-8 text-center text-sm font-semibold text-gray-400 italic">
                                        No related orders yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($orders && $orders->hasPages())
                    <div class="p-4 border-t border-gray-100">
                        {{ $orders->links() }}
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-lg font-bold text-middo-dark">Audit log</h2>
            <p class="text-xs font-semibold text-gray-400 mt-0.5">Login, profile, and account events · newest first</p>
        </div>
        <div class="divide-y divide-gray-100">
            @forelse($logs as $log)
                <div class="px-5 py-4 flex gap-3">
                    <div class="mt-1.5 w-2.5 h-2.5 rounded-full bg-middo-orange shrink-0"></div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-bold text-middo-dark">{{ $this->eventLabel($log->event) }}</p>
                            <p class="text-[11px] font-semibold text-gray-400 whitespace-nowrap">
                                {{ $log->created_at?->timezone('Asia/Dhaka')->format('M d, Y · g:i A') }}
                            </p>
                        </div>
                        <p class="text-sm text-gray-600 mt-0.5">
                            Source: {{ str_replace('_', ' ', $log->source ?? 'system') }}
                            @if($log->ip_address)
                                · IP {{ $log->ip_address }}
                            @endif
                        </p>
                        @if($log->performedBy)
                            <p class="text-[11px] font-semibold text-gray-400 mt-1">
                                by {{ $log->performedBy->name ?: trim($log->performedBy->first_name.' '.$log->performedBy->last_name) }}
                            </p>
                        @endif
                        @if(!empty($log->metadata))
                            <pre class="mt-2 text-[11px] font-mono text-gray-500 bg-gray-50 rounded-lg p-2 overflow-x-auto max-w-full">{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-5 py-10 text-center text-sm font-semibold text-gray-400 italic">
                    No audit events recorded yet.
                </div>
            @endforelse
        </div>
        @if($logs->hasPages())
            <div class="p-4 border-t border-gray-100">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
