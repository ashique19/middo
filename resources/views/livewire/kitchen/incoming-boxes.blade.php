<div class="max-w-7xl mx-auto py-6 sm:py-10 px-4 sm:px-6 space-y-5 sm:space-y-6">
    <div class="space-y-1">
        <a href="{{ route('kitchen.dashboard') }}" class="text-sm font-semibold text-middo-orange hover:underline">← Dashboard</a>
        <h1 class="text-2xl sm:text-3xl font-bold text-middo-dark">Incoming</h1>
        <p class="text-sm font-semibold text-gray-500">
            Boxes on the way to your kitchen. Showing {{ $boxes->count() }} of {{ $boxes->total() }}.
            Confirm receive after the rider hands them over.
        </p>
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

    {{-- Mobile cards --}}
    <div class="md:hidden space-y-4">
        @forelse($runGroups as $group)
            <div wire:key="incoming-run-m-{{ $group['key'] }}" class="rounded-2xl border border-gray-100 bg-white shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 bg-gray-50/80 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="text-sm font-bold text-middo-dark">{{ $group['title'] }}</p>
                        <p class="text-xs font-semibold text-gray-500">
                            {{ $group['box_count'] }} {{ str('box')->plural($group['box_count']) }}
                            · {{ $group['held_by'] }}
                            @if(! empty($group['held_by_mobile']))
                                <span class="font-mono">{{ $group['held_by_mobile'] }}</span>
                            @endif
                        </p>
                    </div>
                    @if(count($group['receive_all_ids']) >= 1)
                        <button
                            type="button"
                            wire:click="receiveAllBoxes({{ json_encode($group['receive_all_ids']) }})"
                            wire:confirm="{{ e($group['receive_confirm_label']) }}"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center px-3 py-1.5 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-xs font-bold transition disabled:opacity-60">
                            Confirm receive ({{ count($group['receive_all_ids']) }})
                        </button>
                    @endif
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach($group['nodes'] as $box)
                        <div wire:key="incoming-box-m-{{ $box['id'] }}" class="p-4 space-y-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 space-y-1">
                                    <p class="font-mono text-base font-bold text-middo-dark break-all">{{ $box['qr_code_id'] }}</p>
                                    <p class="text-sm text-gray-600">
                                        {{ $box['model'] }} · {{ $box['source_label'] }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        Held by {{ $box['held_by'] }}
                                        @if(! empty($box['held_by_mobile']))
                                            <span class="font-mono">{{ $box['held_by_mobile'] }}</span>
                                        @endif
                                    </p>
                                </div>
                                <span @class([
                                    'shrink-0 inline-flex px-2 py-0.5 rounded-lg text-xs font-bold border',
                                    'bg-amber-50 text-amber-800 border-amber-200' => ! $box['can_receive'],
                                    'bg-sky-50 text-sky-800 border-sky-200' => $box['can_receive'],
                                ])>
                                    {{ $box['status_label'] }}
                                </span>
                            </div>
                            @if($box['can_receive'])
                                <button
                                    type="button"
                                    wire:click="receiveBox({{ $box['id'] }})"
                                    wire:loading.attr="disabled"
                                    wire:target="receiveBox({{ $box['id'] }})"
                                    @if(! empty($box['receive_confirm_label'])) wire:confirm="{{ e($box['receive_confirm_label']) }}" @endif
                                    class="w-full inline-flex justify-center items-center px-3 py-2.5 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-xs font-bold transition disabled:opacity-60">
                                    <span wire:loading.remove wire:target="receiveBox({{ $box['id'] }})">Confirm receive</span>
                                    <span wire:loading wire:target="receiveBox({{ $box['id'] }})">Receiving...</span>
                                </button>
                            @else
                                <p class="text-xs font-semibold text-amber-800 bg-amber-50 border border-amber-100 rounded-xl px-3 py-2">
                                    Rider is bringing this box. Confirm receive after they hand it over.
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-gray-100 bg-white p-10 text-center text-sm font-semibold text-gray-400 italic">
                No boxes currently on the way to your kitchen.
            </div>
        @endforelse
    </div>

    {{-- Desktop grouped tables --}}
    <div class="hidden md:block space-y-4">
        @forelse($runGroups as $group)
            <div wire:key="incoming-run-d-{{ $group['key'] }}" class="bg-white shadow-md border border-gray-100 rounded-2xl overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 bg-gray-50 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="text-sm font-bold text-middo-dark">{{ $group['title'] }}</p>
                        <p class="text-xs font-semibold text-gray-500">
                            {{ $group['box_count'] }} {{ str('box')->plural($group['box_count']) }}
                            · {{ $group['held_by'] }}
                            @if(! empty($group['held_by_mobile']))
                                <span class="font-mono">{{ $group['held_by_mobile'] }}</span>
                            @endif
                        </p>
                    </div>
                    @if(count($group['receive_all_ids']) >= 1)
                        <button
                            type="button"
                            wire:click="receiveAllBoxes({{ json_encode($group['receive_all_ids']) }})"
                            wire:confirm="{{ e($group['receive_confirm_label']) }}"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center px-3 py-1.5 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-xs font-bold transition disabled:opacity-60">
                            Confirm receive ({{ count($group['receive_all_ids']) }})
                        </button>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[640px]">
                        <thead>
                            <tr class="bg-white border-b border-gray-100 text-xs font-semibold uppercase tracking-wider text-gray-500">
                                <th class="p-4">QR Code</th>
                                <th class="p-4">Model</th>
                                <th class="p-4">Source</th>
                                <th class="p-4">Status</th>
                                <th class="p-4">Held by</th>
                                <th class="p-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            @foreach($group['nodes'] as $box)
                                <tr wire:key="incoming-box-{{ $box['id'] }}" class="hover:bg-gray-50/70 transition">
                                    <td class="p-4 font-mono font-bold text-middo-dark">{{ $box['qr_code_id'] }}</td>
                                    <td class="p-4 text-gray-700">{{ $box['model'] }}</td>
                                    <td class="p-4 text-gray-700">{{ $box['source_label'] }}</td>
                                    <td class="p-4">
                                        <span @class([
                                            'inline-flex px-2 py-0.5 rounded-lg text-xs font-bold border',
                                            'bg-amber-50 text-amber-800 border-amber-200' => ! $box['can_receive'],
                                            'bg-sky-50 text-sky-800 border-sky-200' => $box['can_receive'],
                                        ])>
                                            {{ $box['status_label'] }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-gray-600">
                                        <div>{{ $box['held_by'] }}</div>
                                        @if(! empty($box['held_by_mobile']))
                                            <div class="text-[11px] font-semibold text-gray-500 font-mono">{{ $box['held_by_mobile'] }}</div>
                                        @endif
                                    </td>
                                    <td class="p-4 text-right">
                                        @if($box['can_receive'])
                                            <button
                                                type="button"
                                                wire:click="receiveBox({{ $box['id'] }})"
                                                wire:loading.attr="disabled"
                                                wire:target="receiveBox({{ $box['id'] }})"
                                                @if(! empty($box['receive_confirm_label'])) wire:confirm="{{ e($box['receive_confirm_label']) }}" @endif
                                                class="inline-flex items-center px-3 py-1.5 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-xs font-bold transition disabled:opacity-60">
                                                <span wire:loading.remove wire:target="receiveBox({{ $box['id'] }})">Confirm receive</span>
                                                <span wire:loading wire:target="receiveBox({{ $box['id'] }})">Receiving...</span>
                                            </button>
                                        @else
                                            <span class="text-xs font-semibold text-amber-700">Waiting for rider handoff</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="bg-white shadow-md border border-gray-100 rounded-2xl p-12 text-center text-sm font-semibold text-gray-400 italic">
                No boxes currently on the way to your kitchen.
            </div>
        @endforelse
    </div>

    @if($boxes->hasPages())
        <div class="mt-4 px-1 overflow-x-auto">
            {{ $boxes->links() }}
        </div>
    @endif
</div>
