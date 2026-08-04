<div class="max-w-7xl mx-auto py-10 px-6 space-y-6">
    <div class="space-y-1">
        <h1 class="text-3xl font-bold text-middo-dark">Dispatch SLA</h1>
        <p class="text-sm font-semibold text-gray-500">
            Unassigned Middo groups by accept-window state, and assigned orders past dispatch deadline.
        </p>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="rounded-2xl border border-rose-100 bg-rose-50 p-4">
            <p class="text-[11px] font-bold uppercase text-rose-700">Window closed</p>
            <p class="text-2xl font-black text-rose-900 mt-1">{{ $counts['unassigned_closed'] }}</p>
        </div>
        <div class="rounded-2xl border border-amber-100 bg-amber-50 p-4">
            <p class="text-[11px] font-bold uppercase text-amber-700">Window open</p>
            <p class="text-2xl font-black text-amber-900 mt-1">{{ $counts['unassigned_open'] }}</p>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase text-gray-400">Unassigned total</p>
            <p class="text-2xl font-black text-middo-dark mt-1">{{ $counts['unassigned_total'] }}</p>
        </div>
        <div class="rounded-2xl border border-orange-100 bg-orange-50 p-4">
            <p class="text-[11px] font-bold uppercase text-orange-700">Late to pack</p>
            <p class="text-2xl font-black text-orange-900 mt-1">{{ $counts['late_to_pack'] }}</p>
        </div>
    </div>

    <div class="flex flex-wrap gap-2">
        <button type="button" wire:click="$set('tab', 'unassigned')"
            @class([
                'px-3 py-1.5 rounded-xl text-xs font-bold border',
                'bg-middo-orange text-white border-middo-orange' => $tab === 'unassigned',
                'bg-white text-gray-600 border-gray-200' => $tab !== 'unassigned',
            ])>Unassigned groups</button>
        <button type="button" wire:click="$set('tab', 'late')"
            @class([
                'px-3 py-1.5 rounded-xl text-xs font-bold border',
                'bg-middo-orange text-white border-middo-orange' => $tab === 'late',
                'bg-white text-gray-600 border-gray-200' => $tab !== 'late',
            ])>Late to pack</button>
    </div>

    @if($tab === 'unassigned')
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left min-w-[800px] text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                        <tr>
                            <th class="p-4">Group</th>
                            <th class="p-4">Date</th>
                            <th class="p-4">Menu</th>
                            <th class="p-4">Orders</th>
                            <th class="p-4">Accept window</th>
                            <th class="p-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($unassigned as $row)
                            <tr @class(['bg-rose-50/40' => $row['accept_window']['state'] === 'closed'])>
                                <td class="p-4 font-bold text-middo-dark">{{ $row['name'] }}</td>
                                <td class="p-4 font-mono text-xs">{{ $row['delivery_date'] }}</td>
                                <td class="p-4">{{ $row['menu'] }}</td>
                                <td class="p-4">{{ $row['order_count'] }} <span class="text-gray-400">· {{ $row['qty'] }} meals</span></td>
                                <td class="p-4">
                                    <span @class([
                                        'inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold uppercase border',
                                        'bg-rose-50 text-rose-800 border-rose-200' => $row['accept_window']['state'] === 'closed',
                                        'bg-amber-50 text-amber-800 border-amber-200' => $row['accept_window']['state'] === 'open' && ($row['accept_window']['closing_soon'] ?? false),
                                        'bg-emerald-50 text-emerald-800 border-emerald-200' => $row['accept_window']['state'] === 'open' && !($row['accept_window']['closing_soon'] ?? false),
                                        'bg-gray-50 text-gray-600 border-gray-200' => $row['accept_window']['state'] === 'not_yet',
                                    ])>{{ $row['accept_window']['label'] }}</span>
                                </td>
                                <td class="p-4 text-right">
                                    <button
                                        type="button"
                                        onclick="Livewire.dispatch('open-assign-kitchen-modal', { orderGroupId: {{ $row['id'] }} })"
                                        class="inline-flex px-3 py-1.5 rounded-xl bg-middo-orange text-white text-xs font-bold">
                                        Assign kitchen
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-10 text-center text-gray-400 italic">No unassigned groups.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left min-w-[800px] text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                        <tr>
                            <th class="p-4">Order</th>
                            <th class="p-4">Kitchen</th>
                            <th class="p-4">When</th>
                            <th class="p-4">Status</th>
                            <th class="p-4">Deadline</th>
                            <th class="p-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($late as $row)
                            <tr class="bg-orange-50/30">
                                <td class="p-4">
                                    <a href="{{ \App\Support\StaffOrderRoutes::show($row['id'], 'kitchen') }}" class="font-mono font-bold text-middo-orange hover:underline">#{{ $row['id'] }}</a>
                                    <div class="text-xs text-gray-500">{{ $row['menu'] }} · qty {{ $row['qty'] }}</div>
                                </td>
                                <td class="p-4">
                                    <div class="font-semibold">{{ $row['kitchen'] }}</div>
                                    <div class="text-xs text-gray-400">{{ $row['group_name'] }}</div>
                                </td>
                                <td class="p-4 font-mono text-xs">{{ $row['delivery_date'] }} · {{ $row['delivery_time'] }}</td>
                                <td class="p-4 capitalize">{{ str_replace('_', ' ', $row['order_status']) }}</td>
                                <td class="p-4">
                                    <div class="font-semibold text-orange-800">{{ $row['deadline_label'] }}</div>
                                    <div class="text-[11px] text-orange-600">{{ $row['minutes_late'] }}m late</div>
                                </td>
                                <td class="p-4 text-right">
                                    @if($row['group_id'])
                                        <button
                                            type="button"
                                            onclick="Livewire.dispatch('open-assign-kitchen-modal', { orderGroupId: {{ $row['group_id'] }} })"
                                            class="inline-flex px-3 py-1.5 rounded-xl border border-gray-200 text-xs font-bold text-gray-700 hover:border-middo-orange">
                                            Group
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-10 text-center text-gray-400 italic">No orders past dispatch deadline.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
