<div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div>
        <h1 class="text-3xl font-bold text-middo-dark">Custom runs</h1>
        <p class="text-sm text-gray-500 mt-1">Create ad-hoc point → point jobs for riders. Commission credits when the rider starts the run.</p>
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ $statusMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errorMessage }}</div>
    @endif

    <form wire:submit="createRun" class="bg-white border border-gray-100 rounded-2xl shadow-sm p-5 space-y-4">
        <h2 class="text-lg font-bold text-middo-dark">New custom run</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold uppercase text-gray-400 mb-1">From</label>
                <input type="text" wire:model="fromLabel" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" placeholder="Warehouse / Kitchen / Address">
                @error('fromLabel') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-bold uppercase text-gray-400 mb-1">To</label>
                <input type="text" wire:model="toLabel" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" placeholder="Destination">
                @error('toLabel') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-bold uppercase text-gray-400 mb-1">Area (optional)</label>
                <select wire:model="areaId" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    <option value="">Open / any area</option>
                    @foreach($areas as $area)
                        <option value="{{ $area->id }}">{{ $area->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase text-gray-400 mb-1">Rider (optional)</label>
                <select wire:model.live="riderUserId" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    <option value="">Open pool</option>
                    @foreach($riders as $rider)
                        <option value="{{ $rider->id }}">{{ $rider->name }} · {{ $rider->mobile }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase text-gray-400 mb-1">Commission (৳)</label>
                <input type="number" min="0" wire:model="commissionAmount" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                @error('commissionAmount') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-bold uppercase text-gray-400 mb-1">Notes</label>
                <input type="text" wire:model="notes" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" placeholder="Optional">
            </div>
        </div>
        <button type="submit" class="px-4 py-2 rounded-xl bg-middo-orange text-white text-sm font-bold">Create run</button>
    </form>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-lg font-bold text-middo-dark">Recent runs</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[800px]">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                    <tr>
                        <th class="p-3 text-left">ID</th>
                        <th class="p-3 text-left">Route</th>
                        <th class="p-3 text-left">Area</th>
                        <th class="p-3 text-left">Rider</th>
                        <th class="p-3 text-right">Commission</th>
                        <th class="p-3 text-left">Status</th>
                        <th class="p-3 text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($runs as $run)
                        <tr>
                            <td class="p-3 font-mono">#{{ $run->id }}</td>
                            <td class="p-3 font-semibold">{{ $run->label() }}</td>
                            <td class="p-3 text-gray-600">{{ $run->area?->name ?? '—' }}</td>
                            <td class="p-3 text-gray-600">{{ $run->rider?->name ?? 'Open pool' }}</td>
                            <td class="p-3 text-right font-bold">৳{{ number_format($run->commission_amount) }}</td>
                            <td class="p-3 capitalize">{{ $run->status }}</td>
                            <td class="p-3 text-right space-x-2 whitespace-nowrap">
                                @if($run->isPending())
                                    <button type="button" wire:click="cancelRun({{ $run->id }})"
                                            wire:confirm="Cancel custom run #{{ $run->id }}?"
                                            class="px-3 py-1.5 rounded-xl border border-red-200 text-red-600 text-xs font-bold">
                                        Cancel
                                    </button>
                                @elseif($run->isStarted())
                                    <button type="button" wire:click="cancelRun({{ $run->id }})"
                                            wire:confirm="Cancel started run #{{ $run->id }} and void rider commission?"
                                            class="px-3 py-1.5 rounded-xl border border-red-200 text-red-600 text-xs font-bold">
                                        Force cancel
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-10 text-center text-gray-400 italic">No custom runs yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($runs->hasPages()) <div class="p-3">{{ $runs->links() }}</div> @endif
    </div>
</div>
