<div class="max-w-7xl mx-auto py-10 px-6 space-y-6">
    <div class="space-y-1">
        <h1 class="text-3xl font-bold text-middo-dark">Rider ops</h1>
        <p class="text-sm font-semibold text-gray-500">
            Roster, packed-awaiting, in-transit lunch, box custody, and custom runs — who holds what.
        </p>
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ $statusMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errorMessage }}</div>
    @endif

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase text-gray-400">Riders</p>
            <p class="text-2xl font-black text-middo-dark mt-1">{{ $counts['riders'] }}</p>
        </div>
        <div class="rounded-2xl border border-amber-100 bg-amber-50 p-4">
            <p class="text-[11px] font-bold uppercase text-amber-700">Awaiting accept</p>
            <p class="text-2xl font-black text-amber-900 mt-1">{{ $counts['awaiting'] }}</p>
        </div>
        <div class="rounded-2xl border {{ ($counts['awaiting_aging'] ?? 0) > 0 ? 'border-rose-200 bg-rose-50' : 'border-gray-100 bg-white' }} p-4">
            <p class="text-[11px] font-bold uppercase {{ ($counts['awaiting_aging'] ?? 0) > 0 ? 'text-rose-700' : 'text-gray-400' }}">Aging / overdue</p>
            <p class="text-2xl font-black {{ ($counts['awaiting_aging'] ?? 0) > 0 ? 'text-rose-900' : 'text-middo-dark' }} mt-1">{{ $counts['awaiting_aging'] ?? 0 }}</p>
        </div>
        <div class="rounded-2xl border border-sky-100 bg-sky-50 p-4">
            <p class="text-[11px] font-bold uppercase text-sky-700">On the way</p>
            <p class="text-2xl font-black text-sky-900 mt-1">{{ $counts['on_the_way'] }}</p>
        </div>
        <div class="rounded-2xl border border-violet-100 bg-violet-50 p-4">
            <p class="text-[11px] font-bold uppercase text-violet-700">Box custody</p>
            <p class="text-2xl font-black text-violet-900 mt-1">{{ $counts['box_custody'] }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4">
            <p class="text-[11px] font-bold uppercase text-emerald-700">Custom started</p>
            <p class="text-2xl font-black text-emerald-900 mt-1">{{ $counts['custom_started'] }}</p>
        </div>
    </div>

    <div class="flex flex-wrap gap-2">
        @foreach([
            'riders' => 'Riders',
            'awaiting' => 'Awaiting accept',
            'on_the_way' => 'On the way',
            'boxes' => 'Box custody',
            'custom' => 'Custom runs',
        ] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')"
                @class([
                    'px-3 py-1.5 rounded-xl text-xs font-bold border',
                    'bg-middo-orange text-white border-middo-orange' => $tab === $key,
                    'bg-white text-gray-600 border-gray-200' => $tab !== $key,
                ])>{{ $label }}</button>
        @endforeach
    </div>

    @if($tab === 'riders')
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[900px]">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                        <tr>
                            <th class="p-3 text-left">Rider</th>
                            <th class="p-3 text-left">Shift</th>
                            <th class="p-3 text-left">Areas</th>
                            <th class="p-3 text-right">Due float</th>
                            <th class="p-3 text-right">Wallet</th>
                            <th class="p-3 text-right">On way</th>
                            <th class="p-3 text-right">Boxes</th>
                            <th class="p-3 text-right">Custom</th>
                            <th class="p-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($riders as $row)
                            <tr @class(['bg-sky-50/40' => $row['active_total'] > 0])>
                                <td class="p-3">
                                    <div class="font-semibold">{{ $row['name'] }}</div>
                                    <div class="text-xs text-gray-500">{{ $row['mobile'] }}</div>
                                </td>
                                <td class="p-3">
                                    <span @class([
                                        'text-xs font-bold',
                                        'text-emerald-700' => $row['shift'] === 'on',
                                        'text-gray-500' => $row['shift'] === 'off',
                                        'text-amber-700' => $row['shift'] === 'unable',
                                    ])>{{ $row['shift_label'] }}</span>
                                </td>
                                <td class="p-3 text-xs text-gray-600">{{ $row['areas'] ? implode(', ', $row['areas']) : '—' }}</td>
                                <td class="p-3 text-right font-mono {{ $row['due_float'] > 0 ? 'text-amber-800 font-bold' : '' }}">৳{{ number_format($row['due_float']) }}</td>
                                <td class="p-3 text-right font-mono">৳{{ number_format($row['wallet']) }}</td>
                                <td class="p-3 text-right font-mono">{{ $row['on_the_way'] }}</td>
                                <td class="p-3 text-right font-mono">{{ $row['boxes'] }}</td>
                                <td class="p-3 text-right font-mono">{{ $row['custom_started'] }}</td>
                                <td class="p-3 text-right">
                                    <a href="{{ route($rolePrefix.'.deliveries.show', $row['id']) }}" class="text-xs font-bold text-middo-orange hover:underline">Profile →</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="p-10 text-center text-gray-400 italic">No active riders.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif($tab === 'awaiting')
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-50 flex flex-wrap justify-between gap-2 items-center">
                <p class="text-xs text-gray-500 font-semibold">Ready or packed lunch with no rider. Ops assigns — riders do not claim.</p>
                <a href="{{ route($rolePrefix.'.coverage.index') }}" class="text-xs font-bold text-middo-orange hover:underline">Coverage board →</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[960px]">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                        <tr>
                            <th class="p-3 text-left">Order</th>
                            <th class="p-3 text-left">Kitchen</th>
                            <th class="p-3 text-left">When</th>
                            <th class="p-3 text-left">Area</th>
                            <th class="p-3 text-left">Corporate</th>
                            <th class="p-3 text-left">Age / SLA</th>
                            <th class="p-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($awaiting as $row)
                            <tr @class([
                                'bg-rose-50/40' => ($row['sla']['state'] ?? '') === 'overdue',
                                'bg-amber-50/40' => in_array($row['sla']['state'] ?? '', ['closing_soon', 'aging'], true),
                            ])>
                                <td class="p-3">
                                    <a href="{{ \App\Support\StaffOrderRoutes::show($row['id'], 'rider') }}" class="font-mono font-bold text-middo-orange hover:underline">#{{ $row['id'] }}</a>
                                    <div class="text-xs text-gray-500">{{ $row['menu'] }} · qty {{ $row['qty'] }}</div>
                                </td>
                                <td class="p-3">
                                    <div class="font-semibold">{{ $row['kitchen'] }}</div>
                                    <div class="text-xs text-gray-400">{{ $row['group_name'] }}</div>
                                </td>
                                <td class="p-3 font-mono text-xs">{{ $row['delivery_date'] }} · {{ $row['delivery_time'] }}</td>
                                <td class="p-3">{{ $row['area'] }}</td>
                                <td class="p-3">{{ $row['corporate'] }}</td>
                                <td class="p-3">
                                    <span @class([
                                        'inline-flex px-2 py-0.5 rounded-lg text-[11px] font-bold border',
                                        'bg-rose-50 text-rose-800 border-rose-200' => ($row['sla']['state'] ?? '') === 'overdue',
                                        'bg-amber-50 text-amber-800 border-amber-200' => in_array($row['sla']['state'] ?? '', ['closing_soon', 'aging'], true),
                                        'bg-emerald-50 text-emerald-800 border-emerald-200' => ($row['sla']['state'] ?? '') === 'ok',
                                    ])>{{ $row['sla']['label'] ?? '—' }}</span>
                                    <div class="text-[10px] text-gray-400 mt-1 font-mono">{{ $row['sla']['minutes_waiting'] ?? 0 }}m waiting</div>
                                </td>
                                <td class="p-3 text-right">
                                    <button type="button" wire:click="openLunchAssign({{ $row['id'] }})"
                                            class="px-3 py-1.5 rounded-xl bg-middo-orange text-white text-xs font-bold">
                                        Assign rider
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="p-10 text-center text-gray-400 italic">No lunch runs waiting for a rider assignment.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($assignLunchOrderId)
            <div class="fixed inset-0 bg-gray-900/50 flex items-center justify-center p-4 z-50">
                <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-2xl space-y-4">
                    <h3 class="text-lg font-bold text-middo-dark">Assign lunch rider for #{{ $assignLunchOrderId }}</h3>
                    <select wire:model="assignLunchRiderId" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                        <option value="">Select rider</option>
                        @foreach($riderOptions as $rider)
                            <option value="{{ $rider->id }}">{{ $rider->name }} · {{ $rider->mobile }}</option>
                        @endforeach
                    </select>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="cancelLunchAssign" class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-bold">Cancel</button>
                        <button type="button" wire:click="confirmLunchAssign" class="px-4 py-2 rounded-xl bg-middo-orange text-white text-sm font-bold">Assign</button>
                    </div>
                </div>
            </div>
        @endif
    @elseif($tab === 'on_the_way')
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[900px]">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                        <tr>
                            <th class="p-3 text-left">Order</th>
                            <th class="p-3 text-left">Rider</th>
                            <th class="p-3 text-left">Kitchen</th>
                            <th class="p-3 text-left">When</th>
                            <th class="p-3 text-left">Corporate</th>
                            <th class="p-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($onTheWay as $row)
                            <tr>
                                <td class="p-3">
                                    <a href="{{ \App\Support\StaffOrderRoutes::show($row['id'], 'rider') }}" class="font-mono font-bold text-middo-orange hover:underline">#{{ $row['id'] }}</a>
                                    <div class="text-xs text-gray-500">{{ $row['menu'] }} · qty {{ $row['qty'] }}</div>
                                </td>
                                <td class="p-3">
                                    @if($row['rider_id'])
                                        <a href="{{ route($rolePrefix.'.deliveries.show', $row['rider_id']) }}" class="font-semibold text-middo-orange hover:underline">{{ $row['rider'] }}</a>
                                    @else
                                        {{ $row['rider'] }}
                                    @endif
                                    @if($row['original_rider_id'] && $row['original_rider_id'] !== $row['rider_id'])
                                        <div class="text-[10px] text-gray-400">Starter #{{ $row['original_rider_id'] }}</div>
                                    @endif
                                </td>
                                <td class="p-3">{{ $row['kitchen'] }}</td>
                                <td class="p-3 font-mono text-xs">{{ $row['delivery_date'] }} · {{ $row['delivery_time'] }}</td>
                                <td class="p-3">{{ $row['corporate'] }}</td>
                                <td class="p-3 text-right">
                                    @if($row['cash_collected'] < 1)
                                        <button type="button" wire:click="openOrderReassign({{ $row['id'] }})"
                                            class="px-3 py-1.5 rounded-xl border border-gray-200 text-xs font-bold text-gray-700">Reassign</button>
                                    @else
                                        <span class="text-[10px] text-amber-700 font-semibold">Cash held</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-10 text-center text-gray-400 italic">No lunch runs in transit.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($reassignOrderId)
            <div class="fixed inset-0 bg-gray-900/50 flex items-center justify-center p-4 z-50">
                <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-2xl space-y-4">
                    <h3 class="text-lg font-bold text-middo-dark">Mid-run reassign order #{{ $reassignOrderId }}</h3>
                    <p class="text-xs text-gray-500">Moves boxes to the rescue rider. Starter keeps lunch commission. Due/cash does not peer-transfer.</p>
                    <select wire:model="reassignOrderRiderId" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                        <option value="">Select rescue rider</option>
                        @foreach($riderOptions as $rider)
                            <option value="{{ $rider->id }}">{{ $rider->name }} · {{ $rider->mobile }} · {{ \App\Support\RiderShift::label($rider->rider_shift_status ?? null) }}</option>
                        @endforeach
                    </select>
                    <input type="text" wire:model="reassignOrderReason" placeholder="Reason (optional)"
                           class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="cancelOrderReassign" class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-bold">Cancel</button>
                        <button type="button" wire:click="confirmOrderReassign" class="px-4 py-2 rounded-xl bg-middo-orange text-white text-sm font-bold">Reassign</button>
                    </div>
                </div>
            </div>
        @endif
    @elseif($tab === 'boxes')
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[700px]">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                        <tr>
                            <th class="p-3 text-left">Box</th>
                            <th class="p-3 text-left">Rider</th>
                            <th class="p-3 text-left">Location</th>
                            <th class="p-3 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($boxes as $row)
                            <tr>
                                <td class="p-3 font-mono font-bold">{{ $row['code'] }}</td>
                                <td class="p-3">
                                    @if($row['rider_id'])
                                        <a href="{{ route($rolePrefix.'.deliveries.show', $row['rider_id']) }}" class="font-semibold text-middo-orange hover:underline">{{ $row['rider'] }}</a>
                                    @else
                                        {{ $row['rider'] }}
                                    @endif
                                </td>
                                <td class="p-3">{{ $row['location'] }}</td>
                                <td class="p-3 capitalize">{{ str_replace('_', ' ', $row['asset_status']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-10 text-center text-gray-400 italic">No boxes in rider custody.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-50 flex justify-between items-center">
                <h2 class="text-sm font-bold text-middo-dark">Active custom runs</h2>
                <a href="{{ route($rolePrefix.'.custom-runs.index') }}" class="text-xs font-bold text-middo-orange hover:underline">Create / manage →</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[900px]">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                        <tr>
                            <th class="p-3 text-left">ID</th>
                            <th class="p-3 text-left">Route</th>
                            <th class="p-3 text-left">Area</th>
                            <th class="p-3 text-left">Rider</th>
                            <th class="p-3 text-right">Commission</th>
                            <th class="p-3 text-left">Status</th>
                            <th class="p-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($customRuns as $row)
                            <tr @class(['bg-emerald-50/40' => $row['is_started']])>
                                <td class="p-3 font-mono">#{{ $row['id'] }}</td>
                                <td class="p-3 font-semibold">{{ $row['label'] }}</td>
                                <td class="p-3">{{ $row['area'] }}</td>
                                <td class="p-3">{{ $row['rider'] }}</td>
                                <td class="p-3 text-right font-bold">৳{{ number_format($row['commission']) }}</td>
                                <td class="p-3 capitalize">{{ $row['status'] }}</td>
                                <td class="p-3 text-right whitespace-nowrap space-x-2">
                                    @if($row['is_pending'])
                                        <button type="button" wire:click="openReassign({{ $row['id'] }})"
                                            class="px-3 py-1.5 rounded-xl border border-gray-200 text-xs font-bold text-gray-700">Reassign</button>
                                    @endif
                                    @if($row['is_pending'] || $row['is_started'])
                                        <button type="button" wire:click="cancelCustomRun({{ $row['id'] }})"
                                            wire:confirm="Cancel custom run #{{ $row['id'] }}{{ $row['is_started'] ? ' and void rider commission?' : '?' }}"
                                            class="px-3 py-1.5 rounded-xl border border-red-200 text-red-600 text-xs font-bold">Cancel</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="p-10 text-center text-gray-400 italic">No pending or started custom runs.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($reassignRunId)
            <div class="fixed inset-0 bg-gray-900/50 flex items-center justify-center p-4 z-50">
                <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-2xl space-y-4">
                    <h3 class="text-lg font-bold text-middo-dark">Reassign custom run #{{ $reassignRunId }}</h3>
                    <select wire:model="reassignRiderId" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                        <option value="">Select rider</option>
                        @foreach($riderOptions as $rider)
                            <option value="{{ $rider->id }}">{{ $rider->name }} · {{ $rider->mobile }}</option>
                        @endforeach
                    </select>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="cancelReassign" class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-bold">Cancel</button>
                        <button type="button" wire:click="confirmReassign" class="px-4 py-2 rounded-xl bg-middo-orange text-white text-sm font-bold">Save</button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
