<div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <a href="{{ route('admin.settings.index') }}" class="text-sm font-semibold text-middo-orange hover:underline">← Settings</a>
            <h1 class="text-3xl font-bold text-middo-dark mt-1">Settings audit log</h1>
            <p class="text-sm text-gray-500 mt-1">
                Who changed admin settings, when, and old → new values.
            </p>
        </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm divide-y divide-gray-100 overflow-hidden">
        @forelse($logs as $log)
            <div wire:key="setting-audit-{{ $log->id }}" class="px-4 py-4 sm:px-5 space-y-2">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-bold text-middo-dark">{{ $log->summary }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ $log->actor?->name ?? 'System' }}
                            · {{ $log->created_at?->timezone('Asia/Dhaka')->format('M j, Y g:i A') }}
                            · {{ str_replace(['_', '.'], ' ', $log->source) }}
                        </p>
                    </div>
                    <span class="shrink-0 text-[10px] font-black uppercase tracking-wide px-2 py-0.5 rounded-lg bg-gray-50 text-gray-600 border border-gray-200">
                        {{ count($log->changes ?? []) }} {{ str('change')->plural(count($log->changes ?? [])) }}
                    </span>
                </div>

                <ul class="space-y-1.5 pt-1">
                    @foreach(($log->changes ?? []) as $change)
                        <li class="rounded-xl border border-gray-100 bg-gray-50/70 px-3 py-2 text-sm">
                            <p class="font-semibold text-middo-dark">{{ $change['label'] ?? $change['key'] }}</p>
                            <p class="text-xs text-gray-600 mt-0.5 break-all">
                                <span class="text-rose-700">{{ $change['old'] === null || $change['old'] === '' ? '—' : $change['old'] }}</span>
                                <span class="text-gray-400 mx-1">→</span>
                                <span class="text-emerald-800">{{ $change['new'] === null || $change['new'] === '' ? '—' : $change['new'] }}</span>
                            </p>
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <div class="p-10 text-center text-sm text-gray-400 italic">
                No settings changes recorded yet.
            </div>
        @endforelse
    </div>

    @if(method_exists($logs, 'hasPages') && $logs->hasPages())
        <div class="overflow-x-auto">{{ $logs->links() }}</div>
    @endif
</div>
