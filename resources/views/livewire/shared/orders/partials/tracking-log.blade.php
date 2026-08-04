<div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100">
        <h2 class="text-lg font-bold text-middo-dark">Tracking log</h2>
        <p class="text-xs font-semibold text-gray-400 mt-0.5">Newest first</p>
    </div>
    <div class="divide-y divide-gray-100 max-h-[32rem] overflow-y-auto">
        @forelse($logs as $log)
            <div class="px-5 py-4 flex gap-3">
                <div class="mt-1.5 w-2.5 h-2.5 rounded-full bg-middo-orange shrink-0"></div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm font-bold text-middo-dark">{{ $log['title'] }}</p>
                        <p class="text-[11px] font-semibold text-gray-400 whitespace-nowrap">{{ $log['at_label'] }}</p>
                    </div>
                    <p class="text-sm text-gray-600 mt-0.5">{{ $log['description'] }}</p>
                    @if(!empty($log['performer_name']))
                        <p class="text-[11px] font-semibold text-gray-400 mt-1">by {{ $log['performer_name'] }}</p>
                    @endif
                </div>
            </div>
        @empty
            <div class="px-5 py-10 text-center text-sm font-semibold text-gray-400 italic">
                No tracking events yet.
            </div>
        @endforelse
    </div>
</div>
