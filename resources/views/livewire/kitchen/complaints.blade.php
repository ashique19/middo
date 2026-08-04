<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="space-y-2">
        <a href="{{ route('kitchen.dashboard') }}" class="text-sm font-semibold text-middo-orange hover:underline">← Dashboard</a>
        <h1 class="text-3xl font-bold text-middo-dark">Complaints</h1>
        <p class="text-sm font-semibold text-gray-500">
            Read-only complaints for orders currently assigned to your kitchen.
        </p>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
        <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Category</label>
        <select wire:model.live="category" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
            <option value="">All</option>
            <option value="food_quality">Food quality</option>
            <option value="delivery">Delivery</option>
            <option value="payment">Payment</option>
            <option value="other">Other</option>
        </select>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <table class="w-full text-left min-w-[640px]">
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

    <div>{{ $complaints->links() }}</div>
</div>
