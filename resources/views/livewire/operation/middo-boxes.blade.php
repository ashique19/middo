<div class="max-w-7xl mx-auto py-10 px-6 space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="space-y-1">
            <h1 class="text-3xl font-bold text-middo-dark">Middo Boxes</h1>
            <p class="text-sm font-semibold text-gray-500">
                All registered Middo box assets. Showing {{ $boxes->count() }} of {{ $boxes->total() }} boxes.
            </p>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full md:w-auto">
            <div class="relative w-full sm:w-72">
                <svg
                    class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="2"
                    stroke="currentColor"
                    aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.603 10.601z" />
                </svg>
                <input
                    wire:model.live.debounce.300ms="search"
                    type="text"
                    placeholder="Search QR code, model, status..."
                    class="w-full rounded-xl border border-gray-200 py-2 pl-9 pr-4 text-sm shadow-sm transition focus:border-middo-orange focus:ring-middo-orange"
                >
            </div>

            <select wire:model.live="statusFilter"
                    class="rounded-xl border border-gray-200 py-2 px-3 text-sm shadow-sm focus:border-middo-orange focus:ring-middo-orange">
                <option value="">All statuses</option>
                <option value="damaged">Damaged{{ $damagedCount ? " ({$damagedCount})" : '' }}</option>
                <option value="at_middo_warehouse">Warehouse</option>
                <option value="active">Active</option>
                <option value="maintenance">Maintenance</option>
                <option value="retired">Retired</option>
                <option value="lost">Lost</option>
            </select>

            <livewire:operation.generate-middo-boxes-modal />
        </div>
    </div>

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

    @if($warningMessage)
        <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-950">
            {{ $warningMessage }}
        </div>
    @endif

    @if($pendingBoxRequests->isNotEmpty())
        <div class="rounded-2xl border border-amber-200 bg-amber-50/80 p-4 sm:p-5 space-y-3 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-lg font-bold text-amber-950">Kitchen box requests</h2>
                    <p class="text-sm font-semibold text-amber-800/80">
                        {{ $pendingBoxRequests->count() }} open — check a request to select warehouse boxes for its remaining qty, then Ready for pickup.
                    </p>
                </div>
            </div>
            <div class="bg-white/80 border border-amber-100 rounded-xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm min-w-[720px]">
                        <thead>
                            <tr class="bg-amber-50/80 text-xs font-semibold uppercase tracking-wider text-amber-800">
                                <th class="p-3 w-12"></th>
                                <th class="p-3">Kitchen</th>
                                <th class="p-3 text-center">Requested</th>
                                <th class="p-3 text-center">Staged</th>
                                <th class="p-3 text-center">Remaining</th>
                                <th class="p-3">Note</th>
                                <th class="p-3">Requested at</th>
                                <th class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-amber-100">
                            @foreach($pendingBoxRequests as $req)
                                @php
                                    $remaining = $req->remainingQuantity();
                                    $receivedCount = $req->requestBoxes->where('status', 'received')->count();
                                    $inTransitCount = $req->requestBoxes->where('status', '!=', 'received')->count();
                                    $requestSelected = $selectedRequestId === $req->id;
                                @endphp
                                <tr wire:key="ops-box-req-{{ $req->id }}" @class(['bg-amber-50/60' => $requestSelected])>
                                    <td class="p-3">
                                        <input
                                            type="checkbox"
                                            wire:click="toggleRequestBoxSelection({{ $req->id }})"
                                            @checked($requestSelected)
                                            @disabled($remaining < 1)
                                            title="{{ $remaining < 1 ? 'No remaining boxes to stage' : 'Select '.$remaining.' warehouse '.str('box')->plural($remaining).' for this request' }}"
                                            aria-label="Select warehouse boxes for {{ $req->kitchen?->name ?? 'kitchen' }} request"
                                            class="h-4 w-4 rounded border-gray-300 text-middo-orange focus:ring-middo-orange cursor-pointer disabled:cursor-not-allowed disabled:opacity-40"
                                        >
                                    </td>
                                    <td class="p-3 font-semibold text-middo-dark">
                                        {{ $req->kitchen?->name ?? 'Kitchen #'.$req->kitchen_id }}
                                        @if($inTransitCount > 0)
                                            <div class="text-[11px] font-semibold text-amber-700 mt-0.5">{{ $inTransitCount }} in transit</div>
                                        @elseif($receivedCount > 0 && $remaining === 0)
                                            <div class="text-[11px] font-semibold text-emerald-700 mt-0.5">All staged boxes received</div>
                                        @endif
                                    </td>
                                    <td class="p-3 text-center font-black text-middo-orange">{{ $req->quantity }}</td>
                                    <td class="p-3 text-center font-semibold text-gray-800">{{ (int) $req->allocated_qty }}</td>
                                    <td class="p-3 text-center font-semibold text-gray-800">{{ $remaining }}</td>
                                    <td class="p-3 text-gray-600 max-w-xs">{{ $req->note ?: '—' }}</td>
                                    <td class="p-3 text-xs font-semibold text-gray-500 whitespace-nowrap">
                                        {{ $req->created_at?->timezone('Asia/Dhaka')->format('M j, g:i A') }}
                                    </td>
                                    <td class="p-3 text-right space-x-2 whitespace-nowrap">
                                        @if($req->canClose())
                                            <button type="button"
                                                    wire:click="openCloseRequest({{ $req->id }})"
                                                    class="text-xs font-bold text-emerald-700 hover:underline">
                                                Close with note
                                            </button>
                                        @elseif((int) $req->allocated_qty < 1)
                                            <button type="button"
                                                    wire:click="cancelBoxRequest({{ $req->id }})"
                                                    class="text-xs font-bold text-gray-500 hover:underline">
                                                Cancel
                                            </button>
                                        @else
                                            <span class="text-xs text-amber-700 font-semibold">In progress</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if($closingRequestId)
        <div class="fixed inset-0 bg-gray-900/50 flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-2xl space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-middo-dark">Close box request #{{ $closingRequestId }}</h2>
                        <p class="text-sm text-gray-500 mt-1">Add a short note for the audit trail, then close.</p>
                    </div>
                    <button type="button" wire:click="cancelCloseRequest" class="text-gray-400 hover:text-gray-600 text-xl leading-none">✕</button>
                </div>
                <div>
                    <label for="ops-close-box-req-note" class="block text-sm font-semibold text-gray-700 mb-1.5">Close note</label>
                    <textarea id="ops-close-box-req-note" rows="3" wire:model="closeNote"
                              class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-middo-orange focus:ring-middo-orange"
                              placeholder="e.g. Kitchen confirmed 4 boxes received"></textarea>
                    @error('closeNote') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" wire:click="cancelCloseRequest"
                            class="px-4 py-2.5 rounded-xl border border-gray-300 text-sm font-bold text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="button" wire:click="closeBoxRequest" wire:loading.attr="disabled"
                            class="px-4 py-2.5 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-sm font-bold disabled:opacity-60">
                        Close request
                    </button>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        @php
            $tiles = [
                ['key' => 'warehouse', 'label' => 'Warehouse', 'classes' => 'border-sky-100 bg-sky-50', 'labelClass' => 'text-sky-700', 'valueClass' => 'text-sky-900'],
                ['key' => 'at_kitchen', 'label' => 'At kitchen', 'classes' => 'border-emerald-100 bg-emerald-50', 'labelClass' => 'text-emerald-700', 'valueClass' => 'text-emerald-900'],
                ['key' => 'to_kitchen', 'label' => 'To kitchen', 'classes' => 'border-amber-100 bg-amber-50', 'labelClass' => 'text-amber-700', 'valueClass' => 'text-amber-900'], // includes staged pickup
                ['key' => 'with_rider', 'label' => 'With rider', 'classes' => 'border-violet-100 bg-violet-50', 'labelClass' => 'text-violet-700', 'valueClass' => 'text-violet-900'],
                ['key' => 'damaged', 'label' => 'Damaged', 'classes' => 'border-orange-100 bg-orange-50', 'labelClass' => 'text-orange-700', 'valueClass' => 'text-orange-900'],
                ['key' => 'returns', 'label' => 'Inbound returns', 'classes' => 'border-rose-100 bg-rose-50', 'labelClass' => 'text-rose-700', 'valueClass' => 'text-rose-900'],
            ];
        @endphp
        @foreach($tiles as $tile)
            @php
                $tileActive = match ($tile['key']) {
                    'warehouse' => $custodyFilter === 'warehouse' || ($custodyFilter === 'all' && $statusFilter === 'at_middo_warehouse'),
                    'to_kitchen' => $custodyFilter === 'to_kitchen',
                    'returns' => $custodyFilter === 'returns',
                    'damaged' => $statusFilter === 'damaged',
                    default => false,
                };
                $tileClickable = in_array($tile['key'], ['warehouse', 'to_kitchen', 'returns', 'damaged'], true);
            @endphp
            <button
                type="button"
                @if($tile['key'] === 'returns' || $tile['key'] === 'warehouse' || $tile['key'] === 'to_kitchen')
                    wire:click="toggleCustodyFilter('{{ $tile['key'] }}')"
                @elseif($tile['key'] === 'damaged')
                    wire:click="$set('statusFilter', '{{ $statusFilter === 'damaged' ? '' : 'damaged' }}')"
                @endif
                class="rounded-2xl border p-4 text-left {{ $tile['classes'] }} {{ $tileClickable ? 'hover:opacity-90 transition' : 'cursor-default' }} {{ $tileActive ? 'ring-2 ring-middo-orange/40' : '' }}">
                <p class="text-[11px] font-bold uppercase {{ $tile['labelClass'] }}">{{ $tile['label'] }}</p>
                <p class="text-2xl font-black mt-1 {{ $tile['valueClass'] }}">{{ $custody[$tile['key']] ?? 0 }}</p>
            </button>
        @endforeach
    </div>

    @if($custodyFilter === 'returns')
        <div class="rounded-xl border border-rose-200 bg-rose-50/70 px-4 py-3 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm font-semibold text-rose-900">
                Showing inbound kitchen returns awaiting ops ack ({{ $custody['returns'] ?? 0 }}).
            </p>
            <button type="button" wire:click="toggleCustodyFilter('returns')" class="text-xs font-bold text-rose-800 hover:underline">
                Clear returns filter
            </button>
        </div>
    @endif

    @if($custodyFilter === 'warehouse')
        <div class="rounded-xl border border-sky-200 bg-sky-50/70 px-4 py-3 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm font-semibold text-sky-900">
                Showing free warehouse stock ready to stage ({{ $custody['warehouse'] ?? 0 }}). Staged pickup boxes are under To kitchen.
            </p>
            <button type="button" wire:click="toggleCustodyFilter('warehouse')" class="text-xs font-bold text-sky-800 hover:underline">
                Clear warehouse filter
            </button>
        </div>
    @endif

    @if($custodyFilter === 'to_kitchen')
        <div class="rounded-xl border border-amber-200 bg-amber-50/70 px-4 py-3 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm font-semibold text-amber-900">
                Showing staged pickup + boxes en route to kitchens ({{ $custody['to_kitchen'] ?? 0 }}).
            </p>
            <button type="button" wire:click="toggleCustodyFilter('to_kitchen')" class="text-xs font-bold text-amber-800 hover:underline">
                Clear to-kitchen filter
            </button>
        </div>
    @endif

    <livewire:operation.assign-middo-boxes-modal />
    <livewire:operation.middo-box-logs-modal />

    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white px-4 py-3 shadow-sm">
        <p class="text-sm font-semibold text-gray-600">
            @if(count($selectedBoxIds) > 0)
                <span class="text-middo-orange">{{ count($selectedBoxIds) }}</span> warehouse {{ str('box')->plural(count($selectedBoxIds)) }} selected
                <span class="text-gray-400 font-medium">· only against a kitchen’s pending request</span>
            @else
                Select warehouse boxes to send against a kitchen box request
            @endif
        </p>

        <button
            type="button"
            wire:click="openAssignModal"
            @disabled(count($selectedBoxIds) === 0)
            class="inline-flex items-center justify-center gap-2 rounded-xl border border-transparent bg-middo-orange px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#733614] whitespace-nowrap disabled:cursor-not-allowed disabled:border-gray-200 disabled:bg-gray-100 disabled:text-gray-400 disabled:shadow-none disabled:hover:bg-gray-100">
            Ready for pickup
            @if(count($selectedBoxIds) > 0)
                ({{ count($selectedBoxIds) }})
            @endif
        </button>
    </div>

    <div class="bg-white shadow-md border border-gray-100 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[960px]">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <th class="p-4 w-12"></th>
                        <th class="p-4">ID</th>
                        <th class="p-4">QR Code</th>
                        <th class="p-4">Model</th>
                        <th class="p-4">Status</th>
                        <th class="p-4">Held By</th>
                        <th class="p-4 text-center">Uses</th>
                        <th class="p-4">Last Scanned</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($boxes as $box)
                        <tr wire:key="middo-box-row-{{ $box->id }}" class="hover:bg-gray-50/70 transition">
                            <td class="p-4">
                                @if($box->isAvailableForKitchenStaging())
                                    <input
                                        type="checkbox"
                                        wire:click="toggleBoxSelection({{ $box->id }})"
                                        @checked(in_array($box->id, $selectedBoxIds, true))
                                        class="h-4 w-4 rounded border-gray-300 text-middo-orange focus:ring-middo-orange cursor-pointer"
                                    >
                                @elseif($box->isStagedForKitchenPickup())
                                    <span class="inline-flex h-4 w-4 items-center justify-center rounded border border-amber-300 bg-amber-50 text-[9px] font-black text-amber-800" title="Staged for rider pickup">S</span>
                                @endif
                            </td>
                            <td class="p-4 font-mono font-semibold text-gray-800">
                                <a href="{{ route('operation.middo-boxes.show', $box) }}" class="text-middo-orange hover:underline">#{{ $box->id }}</a>
                            </td>
                            <td class="p-4 font-mono font-medium text-middo-dark">
                                <a href="{{ route('operation.middo-boxes.show', $box) }}" class="hover:text-middo-orange hover:underline">{{ $box->qr_code_id }}</a>
                            </td>
                            <td class="p-4 text-gray-700">{{ str($box->box_model_type)->headline() }}</td>
                            <td class="p-4">
                                @php
                                    $stagedPickup = $box->isStagedForKitchenPickup();
                                    $statusClasses = $stagedPickup
                                        ? 'bg-amber-50 text-amber-900 border-amber-200/70'
                                        : match ($box->asset_status) {
                                            'active' => 'bg-emerald-50 text-emerald-700 border-emerald-200/70',
                                            'at_middo_warehouse' => 'bg-sky-50 text-sky-700 border-sky-200/70',
                                            'maintenance' => 'bg-amber-50 text-amber-900 border-amber-200/70',
                                            'damaged' => 'bg-orange-50 text-orange-800 border-orange-200/70',
                                            'lost' => 'bg-red-50 text-red-700 border-red-200/70',
                                            'retired' => 'bg-gray-100 text-gray-600 border-gray-200',
                                            default => 'bg-gray-50 text-gray-700 border-gray-200',
                                        };
                                    $statusLabel = $stagedPickup
                                        ? 'Staged for pickup'
                                        : str($box->asset_status)->headline()->toString();
                                @endphp
                                <span class="inline-flex px-2 py-1 rounded-md text-[11px] font-bold uppercase tracking-wide border {{ $statusClasses }}">
                                    {{ $statusLabel }}
                                </span>
                                @if($stagedPickup && $box->requestBox?->request?->kitchen)
                                    <div class="text-[11px] font-semibold text-amber-800 mt-1">
                                        → {{ $box->requestBox->request->kitchen->name }}
                                    </div>
                                @endif
                            </td>
                            <td class="p-4 font-medium text-gray-800">
                                @if($stagedPickup && $box->requestBox?->rider)
                                    {{ $box->requestBox->rider->name }}
                                    <div class="text-[11px] font-semibold text-gray-500">Awaiting accept</div>
                                @else
                                    {{ $box->heldByUser?->name ?? '—' }}
                                @endif
                            </td>
                            <td class="p-4 text-center font-mono font-bold text-middo-orange">
                                {{ $box->total_uses_count }}
                            </td>
                            <td class="p-4 text-gray-600">
                                {{ $box->last_scanned_at?->timezone('Asia/Dhaka')->format('M d, Y H:i') ?? '—' }}
                            </td>
                            <td class="p-4 text-right">
                                <div class="inline-flex items-center justify-end gap-2">
                                    <a
                                        href="{{ route('operation.middo-boxes.show', $box) }}"
                                        class="inline-flex items-center px-3 py-1.5 rounded-lg border border-gray-300 bg-white text-xs font-bold text-gray-700 hover:border-middo-orange hover:bg-orange-50 hover:text-middo-orange transition">
                                        Details
                                    </a>

                                    <a
                                        href="{{ route('operation.middo-boxes.print', $box) }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center px-3 py-1.5 rounded-lg border border-gray-300 bg-white text-xs font-bold text-gray-700 hover:border-middo-orange hover:bg-orange-50 hover:text-middo-orange transition">
                                        Print
                                    </a>

                                    <button
                                        type="button"
                                        wire:click="$dispatch('open-middo-box-logs-modal', { boxId: {{ $box->id }} })"
                                        class="inline-flex items-center px-3 py-1.5 rounded-lg border border-gray-300 bg-white text-xs font-bold text-gray-700 hover:border-middo-orange hover:bg-orange-50 hover:text-middo-orange transition">
                                        Log
                                    </button>

                                    @if($custodyFilter === 'returns')
                                        <button
                                            type="button"
                                            wire:click="ackReturn({{ $box->id }})"
                                            wire:confirm="Acknowledge inbound return for {{ $box->qr_code_id }} into warehouse?"
                                            class="inline-flex items-center px-3 py-1.5 rounded-lg border border-rose-300 bg-rose-50 text-xs font-bold text-rose-800 hover:bg-rose-100 transition">
                                            Ack return
                                        </button>
                                    @endif

                                    @if(in_array($box->asset_status, ['retired', 'damaged', 'maintenance', 'lost'], true))
                                        <button
                                            type="button"
                                            wire:click="reactivate({{ $box->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="reactivate({{ $box->id }})"
                                            wire:confirm="Return {{ $box->qr_code_id }} to warehouse inventory?"
                                            class="inline-flex items-center px-3 py-1.5 rounded-lg border border-emerald-300 bg-emerald-50 text-xs font-bold text-emerald-700 hover:bg-emerald-100 transition disabled:opacity-50">
                                            {{ $box->asset_status === 'damaged' ? 'Clear damage' : 'Re-Activate' }}
                                        </button>
                                    @else
                                        <button
                                            type="button"
                                            wire:click="retire({{ $box->id }})"
                                            wire:confirm="Are you sure you want to retire box {{ $box->qr_code_id }}?"
                                            wire:loading.attr="disabled"
                                            wire:target="retire({{ $box->id }})"
                                            class="inline-flex items-center px-3 py-1.5 rounded-lg border border-gray-300 bg-white text-xs font-bold text-gray-700 hover:border-red-300 hover:bg-red-50 hover:text-red-700 transition disabled:opacity-50">
                                            Retire
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="p-12 text-center text-sm font-semibold text-gray-400 italic">
                                No Middo boxes found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($boxes->hasPages())
        <div class="mt-4 px-1">
            {{ $boxes->links() }}
        </div>
    @endif
</div>
