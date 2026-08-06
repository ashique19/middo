<div class="block w-full max-w-6xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Package Subscriptions</h1>
            <p class="text-sm text-gray-500 mt-1">
                Manage corporate meal-package purchases, skips, refunds, and delivery details.
            </p>
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

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-4 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <input
                wire:model.live.debounce.300ms="search"
                type="search"
                placeholder="Search corporate, package, mobile..."
                class="md:col-span-2 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-middo-orange focus:border-middo-orange"
            />
            <select wire:model.live="statusFilter" class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold">
                <option value="all">All statuses</option>
                <option value="active">Active</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>

        @if($canManage)
            <div class="flex flex-wrap items-end gap-3 border-t border-gray-100 pt-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Holiday bulk skip</label>
                    <input type="date" wire:model="bulkSkipDate" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
                </div>
                <button
                    type="button"
                    wire:click="bulkSkipHoliday"
                    wire:confirm="Skip all pending package orders on this date and refund wallets?"
                    class="inline-flex items-center px-4 py-2 rounded-xl bg-sky-700 hover:bg-sky-800 text-white text-sm font-bold transition">
                    Skip all package days
                </button>
            </div>
        @endif
    </div>

    <div class="bg-white shadow-md border border-gray-100 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[980px]">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <th class="p-4">#</th>
                        <th class="p-4">Corporate</th>
                        <th class="p-4">Package</th>
                        <th class="p-4">Window</th>
                        <th class="p-4 text-center">Days</th>
                        <th class="p-4 text-right">Paid</th>
                        <th class="p-4 text-center">Status</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($subscriptions as $subscription)
                        <tr wire:key="subscription-row-{{ $subscription->id }}" class="hover:bg-gray-50/70 transition">
                            <td class="p-4 font-mono font-semibold text-gray-800">#{{ $subscription->id }}</td>
                            <td class="p-4">
                                <div class="font-semibold text-gray-800">
                                    {{ $subscription->user?->company_name ?: trim(($subscription->user?->first_name.' '.$subscription->user?->last_name)) }}
                                </div>
                                <div class="text-xs text-gray-400">{{ $subscription->user?->mobile }}</div>
                            </td>
                            <td class="p-4">
                                <div class="font-semibold text-gray-800">{{ $subscription->package?->name ?? '—' }}</div>
                                <div class="text-xs text-gray-400">Qty {{ $subscription->quantity }} · {{ $subscription->delivery_time }}</div>
                            </td>
                            <td class="p-4 text-xs text-gray-700">
                                {{ $subscription->start_date?->format('M d') }} – {{ $subscription->end_date?->format('M d, Y') }}
                            </td>
                            <td class="p-4 text-center">
                                <span class="font-bold text-middo-orange">{{ $subscription->pending_orders_count }}</span>
                                <span class="text-xs text-gray-400">/ {{ $subscription->orders_count }}</span>
                            </td>
                            <td class="p-4 text-right font-semibold">৳{{ number_format($subscription->amount_paid) }}</td>
                            <td class="p-4 text-center">
                                <span @class([
                                    'px-2.5 py-1 rounded-full text-[11px] font-bold uppercase',
                                    'bg-emerald-100 text-emerald-800 border border-emerald-200' => $subscription->status === 'active',
                                    'bg-gray-100 text-gray-600 border border-gray-200' => $subscription->status === 'completed',
                                    'bg-red-50 text-red-700 border border-red-200' => $subscription->status === 'cancelled',
                                ])>
                                    {{ $subscription->status }}
                                </span>
                            </td>
                            <td class="p-4 text-right">
                                <a href="{{ $this->showRoute($subscription->id) }}"
                                   class="inline-flex px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-bold text-middo-orange hover:border-middo-orange transition">
                                    Open
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-10 text-center text-sm text-gray-400 italic">No subscriptions found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $subscriptions->links() }}
        </div>
    </div>
</div>
