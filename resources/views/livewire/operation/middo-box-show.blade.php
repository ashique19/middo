<div class="max-w-5xl mx-auto py-10 px-6 space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
            <a href="{{ route('operation.middo-boxes.index') }}" class="text-xs font-bold text-middo-orange hover:underline">← Middo boxes</a>
            <h1 class="text-3xl font-bold text-middo-dark font-mono">{{ $box->qr_code_id }}</h1>
            <p class="text-sm text-gray-500">
                #{{ $box->id }} · {{ str($box->box_model_type)->headline() }} · {{ $metrics['location'] }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('operation.middo-boxes.print', $box) }}" target="_blank" rel="noopener noreferrer"
               class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-bold text-gray-700 hover:border-middo-orange">Print label</a>
        </div>
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ $statusMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errorMessage }}</div>
    @endif

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase text-gray-400">Created</p>
            <p class="text-sm font-bold text-middo-dark mt-1">{{ $metrics['created_at']?->format('M d, Y g:i A') ?? '—' }}</p>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase text-gray-400">Damaged reported</p>
            <p class="text-sm font-bold {{ $metrics['damaged_at'] ? 'text-orange-800' : 'text-middo-dark' }} mt-1">
                {{ $metrics['damaged_at']?->format('M d, Y g:i A') ?? '—' }}
            </p>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase text-gray-400">Retired</p>
            <p class="text-sm font-bold text-middo-dark mt-1">{{ $metrics['retired_at']?->format('M d, Y g:i A') ?? '—' }}</p>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase text-gray-400">Status</p>
            <p class="text-sm font-bold text-middo-dark mt-1">{{ str($metrics['status'])->headline() }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Held by {{ $box->heldByUser?->name ?? '—' }}</p>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="rounded-2xl border border-violet-100 bg-violet-50 p-4">
            <p class="text-[11px] font-bold uppercase text-violet-700">Runs</p>
            <p class="text-2xl font-black text-violet-900 mt-1">{{ $metrics['run_count'] }}</p>
            <p class="text-[10px] text-violet-700/80">uses counter {{ $metrics['uses_recorded'] }}</p>
        </div>
        <div class="rounded-2xl border border-sky-100 bg-sky-50 p-4">
            <p class="text-[11px] font-bold uppercase text-sky-700">Days in service</p>
            <p class="text-2xl font-black text-sky-900 mt-1">{{ $metrics['days_in_service'] }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4">
            <p class="text-[11px] font-bold uppercase text-emerald-700">Runs / day</p>
            <p class="text-2xl font-black text-emerald-900 mt-1">{{ $metrics['runs_per_day'] ?? '—' }}</p>
        </div>
        <div class="rounded-2xl border border-amber-100 bg-amber-50 p-4">
            <p class="text-[11px] font-bold uppercase text-amber-700">Cost / run</p>
            <p class="text-2xl font-black text-amber-900 mt-1">
                @if($metrics['cost_per_run'] !== null)
                    ৳{{ number_format($metrics['cost_per_run'], 2) }}
                @else
                    —
                @endif
            </p>
            <p class="text-[10px] text-amber-800/80">
                @if($metrics['unit_cost'] !== null)
                    unit ৳{{ number_format($metrics['unit_cost']) }}
                @else
                    set unit cost below
                @endif
            </p>
        </div>
    </div>

    <form wire:submit="saveUnitCost" class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm flex flex-wrap items-end gap-4">
        <div class="flex-1 min-w-[180px]">
            <label class="block text-[11px] font-bold uppercase text-gray-400 mb-1">Unit cost (৳)</label>
            <input type="number" min="0" wire:model="unitCostBdt"
                   class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm font-mono"
                   placeholder="Optional acquisition cost">
            @error('unitCostBdt') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="px-4 py-2 rounded-xl bg-middo-orange text-white text-sm font-bold">Save cost</button>
        <p class="w-full text-xs text-gray-500">Used for cost-per-run efficiency. Leave blank if unknown.</p>
    </form>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-50">
            <h2 class="text-sm font-bold text-middo-dark">Tracking tree</h2>
            <p class="text-xs text-gray-500 mt-0.5">Custody events, latest first.</p>
        </div>
        <div class="relative">
            @forelse($tree as $i => $row)
                <div class="px-5 py-4 flex gap-4 border-b border-gray-50 last:border-0" wire:key="log-{{ $row['id'] }}">
                    <div class="flex flex-col items-center pt-1">
                        <span class="w-2.5 h-2.5 rounded-full bg-middo-orange shrink-0"></span>
                        @if(!$loop->last)
                            <span class="w-px flex-1 bg-gray-200 mt-1"></span>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0 pb-1">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <p class="text-sm font-bold text-middo-dark">{{ $row['action_label'] }}</p>
                            <p class="text-xs font-mono text-gray-400">{{ $row['at_label'] }}</p>
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Custody: {{ str($row['custody'])->headline() }}
                            @if($row['actor']) · {{ $row['actor'] }} @endif
                        </p>
                        @if($row['order_id'])
                            <p class="text-xs mt-1">
                                Order
                                <a href="{{ \App\Support\StaffOrderRoutes::show($row['order_id'], 'rider') }}"
                                   class="font-mono font-bold text-middo-orange hover:underline">#{{ $row['order_id'] }}</a>
                                @if($row['order_menu']) · {{ $row['order_menu'] }} @endif
                            </p>
                        @endif
                        @if($row['notes'])
                            <p class="text-xs text-gray-600 mt-1 italic">{{ $row['notes'] }}</p>
                        @endif
                    </div>
                </div>
            @empty
                <p class="p-10 text-center text-sm text-gray-400 italic">No custody events yet.</p>
            @endforelse
        </div>
    </div>
</div>
