@php
    $toneClasses = [
        'amber' => 'border-amber-200 bg-amber-50 text-amber-950',
        'sky' => 'border-sky-200 bg-sky-50 text-sky-950',
        'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-950',
        'rose' => 'border-rose-200 bg-rose-50 text-rose-950',
    ];
    $statusOrder = ['pending', 'processing', 'ready', 'packed', 'on_the_way_to_delivery', 'delivered', 'delivered_and_paid'];
@endphp

<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-middo-dark">
                {{ $data['role'] === 'admin' ? 'Admin' : 'Operations' }} dashboard
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                {{ $data['today_label'] }} · live ops snapshot for Middo
            </p>
        </div>
        <a href="{{ route($data['role'].'.orders.active') }}"
           class="inline-flex items-center justify-center rounded-xl bg-middo-orange px-4 py-2.5 text-sm font-bold text-white hover:bg-[#733614] transition">
            Open active orders
        </a>
    </div>

    {{-- KPI strip --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Today · {{ $data['today_label'] }}</p>
            <p class="mt-1 text-3xl font-black text-middo-dark">{{ number_format($data['today']['qty']) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ number_format($data['today']['orders']) }} orders · ৳{{ number_format($data['today']['revenue']) }}</p>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Tomorrow · {{ $data['tomorrow_label'] }}</p>
            <p class="mt-1 text-3xl font-black text-middo-dark">{{ number_format($data['tomorrow']['qty']) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ number_format($data['tomorrow']['orders']) }} orders · {{ number_format($data['tomorrow']['ungrouped']) }} ungrouped</p>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Active pipeline</p>
            <p class="mt-1 text-3xl font-black text-middo-dark">{{ number_format($data['pipeline']['orders']) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ number_format($data['pipeline']['qty']) }} meals · {{ number_format($data['pipeline']['ungrouped']) }} need grouping</p>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Packages</p>
            <p class="mt-1 text-3xl font-black text-middo-dark">{{ number_format($data['packages']['active']) }}</p>
            <p class="text-xs text-gray-500 mt-1">
                {{ number_format($data['packages']['awaiting_schedule']) }} awaiting schedule
                · ৳{{ number_format($data['packages']['prepaid_revenue']) }} prepaid
            </p>
        </div>
        @php
            $boxReqCount = (int) ($data['box_requests']['open'] ?? 0);
            $boxReqRoute = $data['box_requests']['route'] ?? null;
            $boxReqClasses = $boxReqCount > 0
                ? 'border-amber-200 bg-amber-50 hover:border-amber-300'
                : 'border-gray-100 bg-white hover:border-middo-orange';
        @endphp
        @if($boxReqRoute && \Illuminate\Support\Facades\Route::has($boxReqRoute))
            <a href="{{ route($boxReqRoute) }}"
               class="rounded-2xl border p-4 shadow-sm transition {{ $boxReqClasses }}">
                <p class="text-[11px] font-bold uppercase tracking-wider {{ $boxReqCount > 0 ? 'text-amber-700' : 'text-gray-400' }}">Kitchen boxes</p>
                <p class="mt-1 text-2xl sm:text-3xl font-black {{ $boxReqCount > 0 ? 'text-amber-950' : 'text-middo-dark' }}">
                    Box Req ({{ number_format($boxReqCount) }})
                </p>
                <p class="text-xs {{ $boxReqCount > 0 ? 'text-amber-800/80' : 'text-gray-500' }} mt-1">
                    @if($boxReqCount > 0)
                        {{ number_format($data['box_requests']['remaining_qty'] ?? 0) }} boxes still needed · Middo boxes →
                    @else
                        No open kitchen box requests
                    @endif
                </p>
            </a>
        @else
            <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Kitchen boxes</p>
                <p class="mt-1 text-2xl sm:text-3xl font-black text-middo-dark">Box Req ({{ number_format($boxReqCount) }})</p>
                <p class="text-xs text-gray-500 mt-1">Open kitchen box requests</p>
            </div>
        @endif
    </div>

    @if(!empty($data['attention']))
        <div class="space-y-2">
            <h2 class="text-sm font-black uppercase tracking-wider text-gray-400">Needs attention</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($data['attention'] as $item)
                    @php($classes = $toneClasses[$item['tone']] ?? 'border-gray-200 bg-white text-gray-900')
                    <div @class(['rounded-2xl border p-4 shadow-sm', $classes])>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-bold">{{ $item['label'] }}</p>
                                <p class="text-xs opacity-80 mt-0.5">{{ $item['hint'] }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-2xl font-black">{{ number_format($item['value']) }}</p>
                                @if(!empty($item['amount']))
                                    <p class="text-xs font-semibold">৳{{ number_format($item['amount']) }}</p>
                                @endif
                            </div>
                        </div>
                        @if(!empty($item['route']) && \Illuminate\Support\Facades\Route::has($item['route']))
                            <a href="{{ route($item['route']) }}" class="inline-block mt-3 text-xs font-black uppercase tracking-wide underline underline-offset-2">Open →</a>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Today status --}}
        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm space-y-3 lg:col-span-1">
            <h2 class="text-lg font-bold text-middo-dark">Today by status</h2>
            @php($hasStatus = false)
            <div class="space-y-2">
                @foreach($statusOrder as $status)
                    @if(!empty($data['status_breakdown'][$status]))
                        @php($hasStatus = true)
                        @php($row = $data['status_breakdown'][$status])
                        <div class="flex items-center justify-between gap-2 text-sm">
                            <span class="font-semibold text-gray-700 capitalize">{{ str_replace('_', ' ', $status) }}</span>
                            <span class="font-mono font-bold text-gray-900">{{ $row['orders'] }} <span class="text-gray-400 font-sans text-xs">· {{ $row['qty'] }} meals</span></span>
                        </div>
                    @endif
                @endforeach
                @foreach($data['status_breakdown'] as $status => $row)
                    @if(!in_array($status, $statusOrder, true))
                        @php($hasStatus = true)
                        <div class="flex items-center justify-between gap-2 text-sm">
                            <span class="font-semibold text-gray-700 capitalize">{{ str_replace('_', ' ', $status) }}</span>
                            <span class="font-mono font-bold text-gray-900">{{ $row['orders'] }} <span class="text-gray-400 font-sans text-xs">· {{ $row['qty'] }} meals</span></span>
                        </div>
                    @endif
                @endforeach
                @unless($hasStatus)
                    <p class="text-sm text-gray-400 italic">No deliveries scheduled for today.</p>
                @endunless
            </div>
        </div>

        {{-- Money --}}
        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm space-y-3 lg:col-span-1">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-lg font-bold text-middo-dark">Money snapshot</h2>
                @if(\Illuminate\Support\Facades\Route::has($data['role'].'.accounts.index'))
                    <a href="{{ route($data['role'].'.accounts.index') }}" class="text-xs font-bold text-middo-orange hover:underline">Accounts →</a>
                @elseif(\Illuminate\Support\Facades\Route::has($data['role'].'.middo-cash'))
                    <a href="{{ route($data['role'].'.middo-cash') }}" class="text-xs font-bold text-middo-orange hover:underline">Cash ledger →</a>
                @endif
            </div>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">Middo cash on hand</dt>
                    <dd class="font-mono font-bold text-gray-900">
                        @if($data['money']['middo_cash'] === null)
                            —
                        @else
                            ৳{{ number_format($data['money']['middo_cash']) }}
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">Rider cash float</dt>
                    <dd class="font-mono font-bold text-gray-900">৳{{ number_format($data['money']['rider_cash_float']) }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">Pending Middo Due</dt>
                    <dd class="font-mono font-bold text-gray-900">
                        {{ number_format($data['money']['pending_middo_handovers'] ?? 0) }}
                        <span class="text-gray-400 text-xs">· ৳{{ number_format($data['money']['pending_middo_handover_amount'] ?? 0) }}</span>
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">Pending kitchen handovers</dt>
                    <dd class="font-mono font-bold text-gray-900">
                        {{ number_format($data['money']['pending_kitchen_handovers'] ?? 0) }}
                        <span class="text-gray-400 text-xs">· ৳{{ number_format($data['money']['pending_kitchen_handover_amount'] ?? 0) }}</span>
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">Operating costs (all-time)</dt>
                    <dd class="font-mono font-bold text-gray-900">৳{{ number_format($data['money']['operating_costs_total'] ?? 0) }}</dd>
                </div>
                @if($data['money']['has_accounts'])
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">Open kitchen payables</dt>
                        <dd class="font-mono font-bold text-amber-800">৳{{ number_format($data['money']['open_kitchen_payables']) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">Open delivery payables</dt>
                        <dd class="font-mono font-bold text-sky-800">৳{{ number_format($data['money']['open_delivery_payables']) }}</dd>
                    </div>
                @endif
                <div class="flex justify-between gap-3 border-t border-gray-50 pt-3">
                    <dt class="text-gray-500">Package prepaid (all-time)</dt>
                    <dd class="font-mono font-bold text-gray-900">৳{{ number_format($data['packages']['prepaid_revenue']) }}</dd>
                </div>
            </dl>
        </div>

        {{-- Catalog / network --}}
        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm space-y-3 lg:col-span-1">
            <h2 class="text-lg font-bold text-middo-dark">Network</h2>
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div>
                    <dt class="text-[11px] font-bold uppercase text-gray-400">Corporates</dt>
                    <dd class="text-xl font-black text-middo-dark">{{ number_format($data['catalog']['corporates']) }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-bold uppercase text-gray-400">Kitchens</dt>
                    <dd class="text-xl font-black text-middo-dark">{{ number_format($data['catalog']['kitchens']) }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-bold uppercase text-gray-400">Riders</dt>
                    <dd class="text-xl font-black text-middo-dark">{{ number_format($data['catalog']['riders']) }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-bold uppercase text-gray-400">Menus</dt>
                    <dd class="text-xl font-black text-middo-dark">{{ number_format($data['catalog']['menus']) }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-bold uppercase text-gray-400">Published packages</dt>
                    <dd class="text-xl font-black text-middo-dark">{{ number_format($data['catalog']['packages_published']) }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-bold uppercase text-gray-400">Middo boxes</dt>
                    <dd class="text-xl font-black text-middo-dark">{{ number_format($data['catalog']['middo_boxes']) }}</dd>
                </div>
            </dl>
            @if($data['role'] === 'admin' && !empty($data['users_by_role']))
                <div class="border-t border-gray-50 pt-3 space-y-1">
                    <p class="text-[11px] font-bold uppercase text-gray-400">Active users by role</p>
                    @foreach($data['users_by_role'] as $row)
                        <div class="flex justify-between text-xs">
                            <span class="capitalize text-gray-600">{{ $row['name'] }}</span>
                            <span class="font-mono font-bold">{{ $row['count'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Upcoming days --}}
    <div class="rounded-2xl border border-gray-100 bg-white shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-50 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold text-middo-dark">Upcoming delivery days</h2>
                <p class="text-xs text-gray-500">Next week · orders, meals, groups, ungrouped</p>
            </div>
            <a href="{{ route($data['role'].'.packages.demand') }}" class="text-xs font-bold text-middo-orange hover:underline">Demand →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[640px]">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                    <tr>
                        <th class="p-3 text-left">Date</th>
                        <th class="p-3 text-right">Orders</th>
                        <th class="p-3 text-right">Meals</th>
                        <th class="p-3 text-right">Revenue</th>
                        <th class="p-3 text-right">Groups</th>
                        <th class="p-3 text-right">Ungrouped</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($data['upcoming'] as $day)
                        <tr @class(['bg-amber-50/40' => $day['is_today'], 'hover:bg-gray-50/70'])>
                            <td class="p-3 font-semibold text-gray-800">
                                {{ $day['label'] }}
                                @if($day['is_today'])
                                    <span class="ml-1 text-[10px] font-black uppercase text-middo-orange">Today</span>
                                @elseif($day['is_tomorrow'])
                                    <span class="ml-1 text-[10px] font-black uppercase text-sky-600">Tomorrow</span>
                                @endif
                            </td>
                            <td class="p-3 text-right font-mono">{{ number_format($day['orders']) }}</td>
                            <td class="p-3 text-right font-mono font-bold">{{ number_format($day['qty']) }}</td>
                            <td class="p-3 text-right font-mono">৳{{ number_format($day['revenue']) }}</td>
                            <td class="p-3 text-right font-mono">{{ number_format($day['groups']) }}</td>
                            <td class="p-3 text-right font-mono {{ $day['ungrouped'] > 0 ? 'text-amber-700 font-bold' : 'text-gray-500' }}">
                                {{ number_format($day['ungrouped']) }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-10 text-center text-gray-400">No upcoming orders.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Quick links --}}
    <div class="space-y-3">
        <h2 class="text-sm font-black uppercase tracking-wider text-gray-400">Quick links</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            @foreach($data['quick_links'] as $link)
                @if(\Illuminate\Support\Facades\Route::has($link['route']))
                    <a href="{{ route($link['route']) }}"
                       class="block rounded-2xl border border-gray-200 bg-white px-4 py-4 shadow-sm hover:border-middo-orange hover:shadow-md transition">
                        <p class="text-sm font-bold text-middo-dark">{{ $link['label'] }}</p>
                        <p class="text-xs text-gray-500 mt-1">{{ $link['hint'] }}</p>
                    </a>
                @endif
            @endforeach
        </div>
    </div>
</div>
