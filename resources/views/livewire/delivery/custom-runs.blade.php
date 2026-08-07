<div class="max-w-7xl mx-auto py-6 sm:py-10 px-4 sm:px-6 space-y-5 sm:space-y-6">
    <div class="space-y-1">
        <a href="{{ route('delivery.dashboard') }}" class="text-sm font-semibold text-middo-orange hover:underline">← Dashboard</a>
        <h1 class="text-2xl sm:text-3xl font-bold text-middo-dark">Custom runs</h1>
        <p class="text-sm font-semibold text-gray-500">
            Ad-hoc point → point jobs. Commission credits when you start.
        </p>
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ $statusMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errorMessage }}</div>
    @endif

    @forelse($runs as $run)
        <div wire:key="custom-run-{{ $run->id }}" class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="flex flex-col gap-4 px-4 sm:px-5 py-4 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between">
                <div class="space-y-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-mono font-black text-middo-dark">#{{ $run->id }}</span>
                        <span class="text-sm font-bold text-gray-800 break-words">{{ $run->label() }}</span>
                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-bold bg-sky-50 text-sky-800 border border-sky-200 capitalize">
                            {{ $run->status }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-600">
                        @if($run->area)
                            {{ $run->area->name }} ·
                        @endif
                        @if($run->commission_amount > 0)
                            <span class="font-semibold text-emerald-700">Commission ৳{{ number_format($run->commission_amount) }}</span>
                        @else
                            No commission
                        @endif
                    </p>
                    @if($run->notes)
                        <p class="text-xs text-gray-500 break-words">{{ $run->notes }}</p>
                    @endif
                </div>
                <div class="w-full sm:w-auto sm:shrink-0">
                    @if($run->isPending())
                        <button type="button"
                                wire:click="startRun({{ $run->id }})"
                                wire:loading.attr="disabled"
                                wire:confirm="Start this custom run? Commission will credit your wallet."
                                class="w-full sm:w-auto inline-flex justify-center items-center px-4 py-2.5 sm:py-2 rounded-xl bg-middo-orange text-white text-sm font-bold disabled:opacity-60">
                            Start run
                        </button>
                    @elseif($run->isStarted())
                        <button type="button"
                                wire:click="completeRun({{ $run->id }})"
                                wire:loading.attr="disabled"
                                wire:confirm="Mark custom run #{{ $run->id }} complete?"
                                class="w-full sm:w-auto inline-flex justify-center items-center px-4 py-2.5 sm:py-2 rounded-xl bg-emerald-700 text-white text-sm font-bold disabled:opacity-60">
                            Complete
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="rounded-2xl border border-dashed border-gray-200 px-4 sm:px-6 py-12 sm:py-16 text-center text-gray-400 italic">
            No open custom runs in your areas.
        </div>
    @endforelse

    @if($runs->hasPages())
        <div class="overflow-x-auto">{{ $runs->links() }}</div>
    @endif
</div>
