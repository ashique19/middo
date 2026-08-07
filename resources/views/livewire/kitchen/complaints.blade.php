<div class="max-w-5xl mx-auto py-6 sm:py-8 px-4 sm:px-6 space-y-5 sm:space-y-6">
    <div class="space-y-2">
        <a href="{{ route('kitchen.dashboard') }}" class="text-sm font-semibold text-middo-orange hover:underline">← Dashboard</a>
        <h1 class="text-2xl sm:text-3xl font-bold text-middo-dark">Complaints</h1>
        <p class="text-sm font-semibold text-gray-500">
            Read-only complaints for orders currently assigned to your kitchen.
        </p>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
        <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Category</label>
        <select wire:model.live="category" class="w-full sm:w-auto rounded-xl border border-gray-200 px-3 py-2.5 sm:py-2 text-sm">
            <option value="">All</option>
            <option value="food_quality">Food quality</option>
            <option value="delivery">Delivery</option>
            <option value="payment">Payment</option>
            <option value="other">Other</option>
        </select>
    </div>

    <div class="md:hidden space-y-3">
        @forelse($complaints as $complaint)
            <a href="{{ route('kitchen.complaints.show', $complaint) }}"
               wire:key="complaint-m-{{ $complaint->id }}"
               class="block rounded-2xl border border-gray-100 bg-white p-4 shadow-sm space-y-2 hover:border-middo-orange transition">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-800">#{{ $complaint->order_id }}</p>
                        <p class="text-xs font-medium text-gray-500 truncate">
                            {{ $complaint->order?->menuItem?->name ?? 'Menu' }}
                        </p>
                    </div>
                    <span class="shrink-0 text-[11px] font-bold uppercase tracking-wide text-middo-orange">
                        {{ \App\Support\KitchenComplaints::categoryLabel($complaint->category) }}
                    </span>
                </div>
                <p class="text-sm text-gray-600 line-clamp-3">{{ $complaint->message }}</p>
                <p class="text-xs text-gray-400">
                    {{ $complaint->created_at?->timezone('Asia/Dhaka')->format('M j, g:i A') }}
                </p>
            </a>
        @empty
            <div class="rounded-2xl border border-gray-100 bg-white p-10 text-center text-sm text-gray-400 italic">
                No complaints on your assigned orders.
            </div>
        @endforelse
    </div>

    <div class="hidden md:block bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left min-w-[560px]">
                <thead>
                    <tr class="bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <th class="p-4">Order</th>
                        <th class="p-4">Category</th>
                        <th class="p-4">Message</th>
                        <th class="p-4">When</th>
                        <th class="p-4 text-right">Open</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($complaints as $complaint)
                        <tr wire:key="complaint-{{ $complaint->id }}">
                            <td class="p-4 font-semibold text-gray-800">
                                #{{ $complaint->order_id }}
                                <div class="text-xs font-medium text-gray-500">
                                    {{ $complaint->order?->menuItem?->name ?? 'Menu' }}
                                </div>
                            </td>
                            <td class="p-4">{{ \App\Support\KitchenComplaints::categoryLabel($complaint->category) }}</td>
                            <td class="p-4 text-gray-600 max-w-xs truncate">{{ $complaint->message }}</td>
                            <td class="p-4 text-xs text-gray-500">
                                {{ $complaint->created_at?->timezone('Asia/Dhaka')->format('M j, g:i A') }}
                            </td>
                            <td class="p-4 text-right">
                                <a href="{{ route('kitchen.complaints.show', $complaint) }}"
                                   class="text-xs font-bold text-middo-orange hover:underline">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-10 text-center text-sm text-gray-400 italic">
                                No complaints on your assigned orders.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="overflow-x-auto">{{ $complaints->links() }}</div>
</div>
