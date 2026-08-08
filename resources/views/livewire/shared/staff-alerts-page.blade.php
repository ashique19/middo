<div class="max-w-3xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="space-y-1">
            <h1 class="text-3xl font-bold text-middo-dark">Alerts</h1>
            <p class="text-sm font-semibold text-gray-500">
                {{ $unreadCount }} unread · lunch, boxes, custom runs, assignments, and reassignment needs.
            </p>
        </div>
        <button type="button" wire:click="markAllRead"
                class="inline-flex px-4 py-2 rounded-xl border border-gray-200 text-sm font-bold text-gray-700 hover:bg-gray-50">
            Mark all read
        </button>
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
            {{ $statusMessage }}
        </div>
    @endif

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm divide-y divide-gray-100 overflow-hidden">
        @forelse($alerts as $alert)
            <div wire:key="alert-{{ $alert->id }}"
                 @class([
                     'px-4 py-4 flex flex-wrap gap-3 items-start',
                     'bg-amber-50/40' => $alert->isUnread(),
                 ])>
                <div class="flex-1 min-w-0 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="font-bold text-middo-dark">{{ $alert->title }}</p>
                        @if($alert->isUnread())
                            <span class="text-[10px] font-black uppercase tracking-wide px-1.5 py-0.5 rounded bg-middo-orange text-white">New</span>
                        @endif
                        <span class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">{{ str_replace('_', ' ', $alert->type) }}</span>
                    </div>
                    @if($alert->body)
                        <p class="text-sm text-gray-600">{{ $alert->body }}</p>
                    @endif
                    <p class="text-xs text-gray-400">{{ $alert->created_at?->timezone('Asia/Dhaka')->format('M j, g:i A') }}</p>
                    @if(in_array($alert->type, [
                        \App\Models\StaffAlert::TYPE_OPS_TO_KITCHEN_BOX,
                        \App\Models\StaffAlert::TYPE_KITCHEN_TO_OPS_BOX,
                    ], true) && auth()->user()?->role?->name === 'delivery')
                        <a href="{{ route('delivery.middo-boxes.pending-run') }}"
                           class="inline-flex mt-1 text-xs font-bold text-middo-orange hover:underline">
                            Open pending box runs →
                        </a>
                    @elseif($alert->type === \App\Models\StaffAlert::TYPE_OPS_TO_KITCHEN_BOX && auth()->user()?->role?->name === 'kitchen' && ($alert->meta['phase'] ?? null) === 'handed')
                        <a href="{{ route('kitchen.middo-boxes.incoming') }}"
                           class="inline-flex mt-1 text-xs font-bold text-middo-orange hover:underline">
                            Open incoming boxes →
                        </a>
                    @endif
                </div>
                @if($alert->isUnread())
                    <button type="button" wire:click="markRead({{ $alert->id }})"
                            class="text-xs font-bold text-middo-orange hover:underline">
                        Mark read
                    </button>
                @endif
            </div>
        @empty
            <div class="p-10 text-center text-sm text-gray-400 italic">No alerts yet.</div>
        @endforelse
    </div>

    <div>
        {{ $alerts->links() }}
    </div>
</div>
