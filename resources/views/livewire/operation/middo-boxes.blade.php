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

            <livewire:operation.generate-middo-boxes-modal />
        </div>
    </div>

    <livewire:operation.assign-middo-boxes-modal />
    <livewire:operation.middo-box-logs-modal />

    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white px-4 py-3 shadow-sm">
        <p class="text-sm font-semibold text-gray-600">
            @if(count($selectedBoxIds) > 0)
                <span class="text-middo-orange">{{ count($selectedBoxIds) }}</span> warehouse {{ str('box')->plural(count($selectedBoxIds)) }} selected
            @else
                Select warehouse boxes to assign to a rider
            @endif
        </p>

        <button
            type="button"
            wire:click="openAssignModal"
            @disabled(count($selectedBoxIds) === 0)
            class="inline-flex items-center justify-center gap-2 rounded-xl border border-transparent bg-middo-orange px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#733614] whitespace-nowrap disabled:cursor-not-allowed disabled:border-gray-200 disabled:bg-gray-100 disabled:text-gray-400 disabled:shadow-none disabled:hover:bg-gray-100">
            Assign to
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
                                @if($box->asset_status === 'at_middo_warehouse')
                                    <input
                                        type="checkbox"
                                        wire:click="toggleBoxSelection({{ $box->id }})"
                                        @checked(in_array($box->id, $selectedBoxIds, true))
                                        class="h-4 w-4 rounded border-gray-300 text-middo-orange focus:ring-middo-orange cursor-pointer"
                                    >
                                @endif
                            </td>
                            <td class="p-4 font-mono font-semibold text-gray-800">#{{ $box->id }}</td>
                            <td class="p-4 font-mono font-medium text-middo-dark">{{ $box->qr_code_id }}</td>
                            <td class="p-4 text-gray-700">{{ str($box->box_model_type)->headline() }}</td>
                            <td class="p-4">
                                @php
                                    $statusClasses = match ($box->asset_status) {
                                        'active' => 'bg-emerald-50 text-emerald-700 border-emerald-200/70',
                                        'at_middo_warehouse' => 'bg-sky-50 text-sky-700 border-sky-200/70',
                                        'maintenance' => 'bg-amber-50 text-amber-900 border-amber-200/70',
                                        'damaged' => 'bg-orange-50 text-orange-800 border-orange-200/70',
                                        'lost' => 'bg-red-50 text-red-700 border-red-200/70',
                                        'retired' => 'bg-gray-100 text-gray-600 border-gray-200',
                                        default => 'bg-gray-50 text-gray-700 border-gray-200',
                                    };
                                @endphp
                                <span class="inline-flex px-2 py-1 rounded-md text-[11px] font-bold uppercase tracking-wide border {{ $statusClasses }}">
                                    {{ str($box->asset_status)->headline() }}
                                </span>
                            </td>
                            <td class="p-4 font-medium text-gray-800">
                                {{ $box->heldByUser?->name ?? '—' }}
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

                                    @if($box->asset_status === 'retired')
                                        <button
                                            type="button"
                                            wire:click="reactivate({{ $box->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="reactivate({{ $box->id }})"
                                            class="inline-flex items-center px-3 py-1.5 rounded-lg border border-emerald-300 bg-emerald-50 text-xs font-bold text-emerald-700 hover:bg-emerald-100 transition disabled:opacity-50">
                                            Re-Activate
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
