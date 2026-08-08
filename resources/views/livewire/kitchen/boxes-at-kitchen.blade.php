<div class="max-w-7xl mx-auto py-6 sm:py-10 px-4 sm:px-6 space-y-5 sm:space-y-6">
    <div class="space-y-1">
        <a href="{{ route('kitchen.dashboard') }}" class="text-sm font-semibold text-middo-orange hover:underline">← Dashboard</a>
        <h1 class="text-2xl sm:text-3xl font-bold text-middo-dark">Boxes at kitchen</h1>
        <p class="text-sm font-semibold text-gray-500">
            Inventory, damaged returns, and recent box activity for your kitchen.
        </p>
    </div>

    <div class="flex flex-wrap gap-2 items-center">
        @foreach([
            'inventory' => 'With me',
            'sendable' => 'Sendable',
            'damaged' => 'Damaged',
            'history' => 'History',
        ] as $key => $label)
            <button type="button"
                    wire:click="$set('filter', '{{ $key }}')"
                    @class([
                        'px-3 py-1.5 rounded-xl text-xs font-bold border transition',
                        'bg-middo-orange text-white border-middo-orange' => $filter === $key,
                        'bg-white text-gray-700 border-gray-200 hover:border-middo-orange' => $filter !== $key,
                    ])>
                {{ $label }}
                <span class="opacity-80">({{ $counts[$key] ?? 0 }})</span>
            </button>
        @endforeach
        <a href="{{ route('kitchen.middo-boxes.incoming') }}"
           class="px-3 py-1.5 rounded-xl text-xs font-bold border border-sky-200 text-sky-800 bg-sky-50 hover:border-sky-400 transition">
            Incoming →
        </a>
        <button type="button"
                wire:click="openRequestModal"
                class="ml-auto px-3 py-1.5 rounded-xl text-xs font-bold border border-transparent bg-middo-orange text-white hover:bg-[#733614] transition">
            Request box
        </button>
    </div>

    @if($showRequestModal)
        <div class="fixed inset-0 bg-gray-900/50 flex items-center justify-center p-4 z-50 overflow-y-auto">
            <div class="bg-white rounded-2xl p-5 sm:p-6 w-full max-w-md shadow-2xl my-8 space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-bold text-middo-dark">Request Middo boxes</h2>
                        <p class="text-sm text-gray-500 mt-1">Ops will see this on the Middo Boxes page.</p>
                    </div>
                    <button type="button" wire:click="closeRequestModal" class="text-gray-400 hover:text-gray-600 text-xl leading-none">✕</button>
                </div>

                <div>
                    <label for="request-box-qty" class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1.5">Quantity</label>
                    <input id="request-box-qty" type="number" min="1" max="500" wire:model="requestQuantity"
                           class="block w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-middo-orange focus:ring-middo-orange">
                    @error('requestQuantity') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="request-box-note" class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1.5">Note (optional)</label>
                    <textarea id="request-box-note" rows="3" wire:model="requestNote"
                              class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-middo-orange focus:ring-middo-orange"
                              placeholder="e.g. Need stock before lunch accept window"></textarea>
                    @error('requestNote') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col-reverse sm:flex-row gap-2 pt-1">
                    <button type="button" wire:click="closeRequestModal"
                            class="inline-flex justify-center px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-bold text-gray-700 hover:bg-gray-50 w-full sm:w-auto">
                        Cancel
                    </button>
                    <button type="button" wire:click="submitBoxRequest"
                            class="inline-flex justify-center px-4 py-2.5 rounded-xl bg-middo-orange text-white text-sm font-bold hover:bg-[#733614] w-full sm:flex-1">
                        Submit request
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($pendingRequests->isNotEmpty())
        <div class="rounded-2xl border border-amber-200 bg-amber-50/70 px-4 py-3 space-y-2">
            <p class="text-xs font-bold uppercase tracking-wider text-amber-800">Pending box requests</p>
            @foreach($pendingRequests as $req)
                <div wire:key="kitchen-box-req-{{ $req->id }}" class="flex flex-wrap items-center justify-between gap-2 text-sm">
                    <p class="font-semibold text-amber-950">
                        {{ $req->quantity }} {{ str('box')->plural($req->quantity) }}
                        @if((int) $req->allocated_qty > 0)
                            <span class="font-medium text-amber-800/80">· {{ (int) $req->allocated_qty }} staged · {{ $req->remainingQuantity() }} remaining</span>
                        @endif
                        <span class="font-medium text-amber-800/80">· {{ $req->created_at?->timezone('Asia/Dhaka')->format('M j, g:i A') }}</span>
                        @if($req->note)
                            <span class="block text-xs font-medium text-amber-800/80 mt-0.5">{{ $req->note }}</span>
                        @endif
                    </p>
                    @if((int) $req->allocated_qty < 1)
                        <button type="button" wire:click="cancelBoxRequest({{ $req->id }})"
                                class="text-xs font-bold text-amber-900 hover:underline">
                            Cancel
                        </button>
                    @else
                        <span class="text-xs font-bold text-amber-800">In progress</span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

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

    @if($damageBoxId)
        <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4 sm:p-5 shadow-sm space-y-3">
            <h2 class="text-lg font-bold text-middo-dark">Mark box damaged</h2>
            <p class="text-sm text-gray-600">
                Damaged boxes cannot be used for dispatch and must be returned on the damaged path (not a normal empty return).
            </p>
            <textarea wire:model="damageNotes" rows="3"
                      class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-middo-orange focus:ring-middo-orange bg-white"
                      placeholder="Optional note (crack, missing latch, contamination…)"></textarea>
            @error('damageNotes') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
            <div class="flex flex-col-reverse sm:flex-row flex-wrap gap-2">
                <button type="button" wire:click="confirmDamage"
                        wire:confirm="Mark this Middo box as damaged?"
                        class="inline-flex justify-center px-4 py-2.5 sm:py-2 rounded-xl bg-amber-700 text-white text-sm font-bold hover:bg-amber-800 w-full sm:w-auto">
                    Confirm damaged
                </button>
                <button type="button" wire:click="cancelDamage"
                        class="inline-flex justify-center px-4 py-2.5 sm:py-2 rounded-xl border border-gray-200 text-sm font-bold text-gray-700 hover:bg-white w-full sm:w-auto">
                    Cancel
                </button>
            </div>
        </div>
    @endif

    @if($filter === 'history')
        {{-- Mobile cards --}}
        <div class="md:hidden space-y-3">
            @forelse($history as $log)
                <div wire:key="box-history-m-{{ $log->id }}"
                     class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm space-y-2">
                    <div class="flex items-start justify-between gap-3">
                        <p class="font-mono text-sm font-bold text-middo-dark break-all">
                            {{ $log->middoBox?->qr_code_id ?? '—' }}
                        </p>
                        <p class="shrink-0 text-xs font-semibold text-gray-500 whitespace-nowrap">
                            {{ $log->created_at?->timezone('Asia/Dhaka')->format('M d, H:i') }}
                        </p>
                    </div>
                    <span @class([
                        'inline-flex px-2 py-0.5 rounded-lg text-xs font-bold border',
                        'bg-rose-50 text-rose-800 border-rose-200' => str_contains($log->log_action, 'damaged'),
                        'bg-emerald-50 text-emerald-800 border-emerald-200' => $log->log_action === 'received_at_kitchen',
                        'bg-sky-50 text-sky-800 border-sky-200' => in_array($log->log_action, ['returned_to_warehouse', 'dispatched_to_warehouse', 'staged_for_warehouse_pickup'], true),
                        'bg-gray-50 text-gray-700 border-gray-200' => ! str_contains($log->log_action, 'damaged') && ! in_array($log->log_action, ['received_at_kitchen', 'returned_to_warehouse', 'dispatched_to_warehouse', 'staged_for_warehouse_pickup'], true),
                    ])>
                        {{ str($log->log_action)->replace('_', ' ')->headline() }}
                    </span>
                    @if($log->notes)
                        <p class="text-sm text-gray-600">{{ $log->notes }}</p>
                    @endif
                </div>
            @empty
                <div class="rounded-2xl border border-gray-100 bg-white p-10 text-center text-sm font-semibold text-gray-400 italic">
                    No recent box history for your kitchen yet.
                </div>
            @endforelse
        </div>

        {{-- Desktop table --}}
        <div class="hidden md:block bg-white shadow-md border border-gray-100 rounded-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[640px]">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <th class="p-4">When</th>
                            <th class="p-4">QR Code</th>
                            <th class="p-4">Action</th>
                            <th class="p-4">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        @forelse($history as $log)
                            <tr wire:key="box-history-{{ $log->id }}" class="hover:bg-gray-50/70">
                                <td class="p-4 text-gray-600 whitespace-nowrap">
                                    {{ $log->created_at?->timezone('Asia/Dhaka')->format('M d, Y H:i') }}
                                </td>
                                <td class="p-4 font-mono font-bold text-middo-dark">{{ $log->middoBox?->qr_code_id ?? '—' }}</td>
                                <td class="p-4">
                                    <span @class([
                                        'inline-flex px-2 py-0.5 rounded-lg text-xs font-bold border',
                                        'bg-rose-50 text-rose-800 border-rose-200' => str_contains($log->log_action, 'damaged'),
                                        'bg-emerald-50 text-emerald-800 border-emerald-200' => $log->log_action === 'received_at_kitchen',
                                        'bg-sky-50 text-sky-800 border-sky-200' => in_array($log->log_action, ['returned_to_warehouse', 'dispatched_to_warehouse', 'staged_for_warehouse_pickup'], true),
                                        'bg-gray-50 text-gray-700 border-gray-200' => ! str_contains($log->log_action, 'damaged') && ! in_array($log->log_action, ['received_at_kitchen', 'returned_to_warehouse', 'dispatched_to_warehouse', 'staged_for_warehouse_pickup'], true),
                                    ])>
                                        {{ str($log->log_action)->replace('_', ' ')->headline() }}
                                    </span>
                                </td>
                                <td class="p-4 text-gray-600">{{ $log->notes ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="p-12 text-center text-sm font-semibold text-gray-400 italic">
                                    No recent box history for your kitchen yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($history->hasPages())
            <div class="mt-4 px-1 overflow-x-auto">{{ $history->links() }}</div>
        @endif
    @else
        @php
            $emptyCopy = match ($filter) {
                'damaged' => 'No damaged boxes at your kitchen.',
                'sendable' => 'No sendable empty boxes right now.',
                default => 'No boxes currently at your kitchen.',
            };
        @endphp

        {{-- Mobile cards --}}
        <div class="md:hidden space-y-3">
            @forelse($boxes as $box)
                @php
                    $reserved = (int) ($box->order_middo_boxes_count ?? 0) > 0;
                    $damaged = $box->asset_status === 'damaged';
                    $handoff = $box->warehouseHandoff;
                    $runRequested = $handoff?->status === \App\Models\KitchenWarehouseHandoff::STATUS_RUN_REQUESTED;
                    $runClaimed = $handoff?->status === \App\Models\KitchenWarehouseHandoff::STATUS_RUN_CLAIMED;
                    $runDispatched = $handoff?->status === \App\Models\KitchenWarehouseHandoff::STATUS_DISPATCHED;
                    $onWarehouseRun = $runRequested || $runClaimed || $runDispatched;
                    $stagedRiderName = $handoff?->rider?->name;
                @endphp
                <div wire:key="kitchen-box-m-{{ $box->id }}"
                     class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 space-y-1">
                            <p class="font-mono text-base font-bold text-middo-dark break-all">{{ $box->qr_code_id }}</p>
                            <p class="text-sm text-gray-600">{{ str($box->box_model_type)->headline() }} · {{ $box->total_uses_count }} uses</p>
                        </div>
                        @if($damaged)
                            <span class="shrink-0 inline-flex px-2 py-0.5 rounded-lg text-xs font-bold bg-rose-50 text-rose-800 border border-rose-200">
                                Damaged
                            </span>
                        @elseif($runRequested)
                            <span class="shrink-0 inline-flex px-2 py-0.5 rounded-lg text-xs font-bold bg-sky-50 text-sky-800 border border-sky-200">
                                Ready to ship
                            </span>
                        @elseif($runClaimed)
                            <span class="shrink-0 inline-flex px-2 py-0.5 rounded-lg text-xs font-bold bg-amber-50 text-amber-900 border border-amber-200">
                                Rider claimed
                            </span>
                        @elseif($runDispatched)
                            <span class="shrink-0 inline-flex px-2 py-0.5 rounded-lg text-xs font-bold bg-violet-50 text-violet-800 border border-violet-200">
                                Dispatched
                            </span>
                        @elseif($reserved)
                            <span class="shrink-0 inline-flex px-2 py-0.5 rounded-lg text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                Reserved
                            </span>
                        @else
                            <span class="shrink-0 inline-flex px-2 py-0.5 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                At kitchen
                            </span>
                        @endif
                    </div>

                    @if($runRequested)
                        <p class="text-sm font-semibold text-sky-800">
                            Waiting for an area rider to claim this warehouse run
                        </p>
                    @elseif($runClaimed)
                        <p class="text-sm font-semibold text-amber-900">
                            {{ $stagedRiderName ?? 'Rider' }} claimed — dispatch when ready
                        </p>
                    @elseif($runDispatched)
                        <p class="text-sm font-semibold text-violet-800">
                            Waiting for {{ $stagedRiderName ?? 'rider' }} to accept the box
                        </p>
                    @endif

                    @if($damaged || (! $reserved && ! $onWarehouseRun) || $runClaimed)
                        <div class="flex flex-col gap-2 pt-1 border-t border-gray-100">
                            @if($damaged)
                                <button
                                    type="button"
                                    wire:click="sendDamagedToWarehouse({{ $box->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="sendDamagedToWarehouse({{ $box->id }})"
                                    wire:confirm="Send this DAMAGED box to Middo? It will not restock as normal inventory."
                                    class="w-full inline-flex justify-center items-center px-3 py-2.5 rounded-xl border border-rose-300 bg-rose-50 text-xs font-bold text-rose-800 hover:bg-rose-100 transition disabled:opacity-60">
                                    <span wire:loading.remove wire:target="sendDamagedToWarehouse({{ $box->id }})">Send damaged to Middo</span>
                                    <span wire:loading wire:target="sendDamagedToWarehouse({{ $box->id }})">Sending...</span>
                                </button>
                            @elseif($runClaimed)
                                <button
                                    type="button"
                                    wire:click="dispatchWarehouseRun({{ $box->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="dispatchWarehouseRun({{ $box->id }})"
                                    wire:confirm="Dispatch this box to {{ $stagedRiderName ?? 'the claiming rider' }}?"
                                    class="w-full inline-flex justify-center items-center px-3 py-2.5 rounded-xl bg-middo-orange text-white text-xs font-bold hover:bg-[#733614] transition disabled:opacity-60">
                                    <span wire:loading.remove wire:target="dispatchWarehouseRun({{ $box->id }})">Dispatch to {{ $stagedRiderName ?? 'rider' }}</span>
                                    <span wire:loading wire:target="dispatchWarehouseRun({{ $box->id }})">Dispatching...</span>
                                </button>
                            @else
                                <button
                                    type="button"
                                    wire:click="sendToWarehouse({{ $box->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="sendToWarehouse({{ $box->id }})"
                                    wire:confirm="{{ $viaRiderEnabled ? 'Mark this empty box ready to ship to Middo warehouse? Area riders will be notified.' : 'Send this empty box back to Middo warehouse?' }}"
                                    class="w-full inline-flex justify-center items-center px-3 py-2.5 rounded-xl border border-gray-300 bg-white text-xs font-bold text-middo-dark hover:border-middo-orange hover:text-middo-orange transition disabled:opacity-60">
                                    <span wire:loading.remove wire:target="sendToWarehouse({{ $box->id }})">Send to Middo warehouse</span>
                                    <span wire:loading wire:target="sendToWarehouse({{ $box->id }})">Sending...</span>
                                </button>
                                <button
                                    type="button"
                                    wire:click="openDamage({{ $box->id }})"
                                    class="w-full inline-flex justify-center items-center px-3 py-2.5 rounded-xl border border-amber-300 bg-white text-xs font-bold text-amber-800 hover:bg-amber-50 transition">
                                    Mark damaged
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-2xl border border-gray-100 bg-white p-10 text-center text-sm font-semibold text-gray-400 italic">
                    {{ $emptyCopy }}
                </div>
            @endforelse
        </div>

        {{-- Desktop table --}}
        <div class="hidden md:block bg-white shadow-md border border-gray-100 rounded-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[640px]">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <th class="p-4">QR Code</th>
                            <th class="p-4">Model</th>
                            <th class="p-4">Status</th>
                            <th class="p-4 text-center">Uses</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        @forelse($boxes as $box)
                            @php
                                $reserved = (int) ($box->order_middo_boxes_count ?? 0) > 0;
                                $damaged = $box->asset_status === 'damaged';
                                $handoff = $box->warehouseHandoff;
                                $runRequested = $handoff?->status === \App\Models\KitchenWarehouseHandoff::STATUS_RUN_REQUESTED;
                                $runClaimed = $handoff?->status === \App\Models\KitchenWarehouseHandoff::STATUS_RUN_CLAIMED;
                                $runDispatched = $handoff?->status === \App\Models\KitchenWarehouseHandoff::STATUS_DISPATCHED;
                                $stagedRiderName = $handoff?->rider?->name;
                            @endphp
                            <tr wire:key="kitchen-box-{{ $box->id }}" class="hover:bg-gray-50/70 transition">
                                <td class="p-4 font-mono font-bold text-middo-dark">{{ $box->qr_code_id }}</td>
                                <td class="p-4 text-gray-700">{{ str($box->box_model_type)->headline() }}</td>
                                <td class="p-4">
                                    @if($damaged)
                                        <span class="inline-flex px-2 py-0.5 rounded-lg text-xs font-bold bg-rose-50 text-rose-800 border border-rose-200">
                                            Damaged
                                        </span>
                                    @elseif($runRequested)
                                        <div class="space-y-1">
                                            <span class="inline-flex px-2 py-0.5 rounded-lg text-xs font-bold bg-sky-50 text-sky-800 border border-sky-200">
                                                Ready to ship
                                            </span>
                                            <p class="text-xs font-semibold text-sky-800">Awaiting rider claim</p>
                                        </div>
                                    @elseif($runClaimed)
                                        <div class="space-y-1">
                                            <span class="inline-flex px-2 py-0.5 rounded-lg text-xs font-bold bg-amber-50 text-amber-900 border border-amber-200">
                                                Rider claimed
                                            </span>
                                            <p class="text-xs font-semibold text-amber-900">{{ $stagedRiderName ?? 'Rider' }} — dispatch when ready</p>
                                        </div>
                                    @elseif($runDispatched)
                                        <div class="space-y-1">
                                            <span class="inline-flex px-2 py-0.5 rounded-lg text-xs font-bold bg-violet-50 text-violet-800 border border-violet-200">
                                                Dispatched
                                            </span>
                                            <p class="text-xs font-semibold text-violet-800">Waiting for {{ $stagedRiderName ?? 'rider' }} accept</p>
                                        </div>
                                    @elseif($reserved)
                                        <span class="inline-flex px-2 py-0.5 rounded-lg text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                            Reserved for order
                                        </span>
                                    @else
                                        <span class="inline-flex px-2 py-0.5 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                            At kitchen
                                        </span>
                                    @endif
                                </td>
                                <td class="p-4 text-center text-gray-600">{{ $box->total_uses_count }}</td>
                                <td class="p-4 text-right">
                                    <div class="inline-flex flex-wrap justify-end gap-2">
                                        @if($damaged)
                                            <button
                                                type="button"
                                                wire:click="sendDamagedToWarehouse({{ $box->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="sendDamagedToWarehouse({{ $box->id }})"
                                                wire:confirm="Send this DAMAGED box to Middo? It will not restock as normal inventory."
                                                class="inline-flex items-center px-3 py-1.5 rounded-xl border border-rose-300 bg-rose-50 text-xs font-bold text-rose-800 hover:bg-rose-100 transition disabled:opacity-60">
                                                <span wire:loading.remove wire:target="sendDamagedToWarehouse({{ $box->id }})">Send damaged to Middo</span>
                                                <span wire:loading wire:target="sendDamagedToWarehouse({{ $box->id }})">Sending...</span>
                                            </button>
                                        @elseif($runClaimed)
                                            <button
                                                type="button"
                                                wire:click="dispatchWarehouseRun({{ $box->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="dispatchWarehouseRun({{ $box->id }})"
                                                wire:confirm="Dispatch this box to {{ $stagedRiderName ?? 'the claiming rider' }}?"
                                                class="inline-flex items-center px-3 py-1.5 rounded-xl bg-middo-orange text-white text-xs font-bold hover:bg-[#733614] transition disabled:opacity-60">
                                                <span wire:loading.remove wire:target="dispatchWarehouseRun({{ $box->id }})">Dispatch to {{ $stagedRiderName ?? 'rider' }}</span>
                                                <span wire:loading wire:target="dispatchWarehouseRun({{ $box->id }})">Dispatching...</span>
                                            </button>
                                        @elseif($runRequested || $runDispatched)
                                            <span class="inline-flex items-center px-3 py-1.5 rounded-xl border border-sky-200 bg-sky-50 text-xs font-bold text-sky-800">
                                                {{ $runRequested ? 'Awaiting rider claim' : 'Awaiting rider accept' }}
                                            </span>
                                        @elseif(! $reserved)
                                            <button
                                                type="button"
                                                wire:click="openDamage({{ $box->id }})"
                                                class="inline-flex items-center px-3 py-1.5 rounded-xl border border-amber-300 bg-white text-xs font-bold text-amber-800 hover:bg-amber-50 transition">
                                                Mark damaged
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="sendToWarehouse({{ $box->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="sendToWarehouse({{ $box->id }})"
                                                wire:confirm="{{ $viaRiderEnabled ? 'Mark this empty box ready to ship to Middo warehouse? Area riders will be notified.' : 'Send this empty box back to Middo warehouse?' }}"
                                                class="inline-flex items-center px-3 py-1.5 rounded-xl border border-gray-300 bg-white text-xs font-bold text-middo-dark hover:border-middo-orange hover:text-middo-orange transition disabled:opacity-60">
                                                <span wire:loading.remove wire:target="sendToWarehouse({{ $box->id }})">Send to Middo warehouse</span>
                                                <span wire:loading wire:target="sendToWarehouse({{ $box->id }})">Sending...</span>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-12 text-center text-sm font-semibold text-gray-400 italic">
                                    {{ $emptyCopy }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($boxes->hasPages())
            <div class="mt-4 px-1 overflow-x-auto">
                {{ $boxes->links() }}
            </div>
        @endif
    @endif
</div>
