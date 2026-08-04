<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="space-y-2">
        <h1 class="text-3xl font-bold text-middo-dark">Complaints</h1>
        <p class="text-sm font-semibold text-gray-500">
            All customer support threads. Reply as Middo ops or open the order corporate lens.
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
        <div class="overflow-x-auto">
            <table class="w-full text-left min-w-[720px]">
                <thead>
                    <tr class="bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <th class="p-4">Order</th>
                        <th class="p-4">Corporate</th>
                        <th class="p-4">Kitchen</th>
                        <th class="p-4">Category</th>
                        <th class="p-4">Message</th>
                        <th class="p-4">When</th>
                        <th class="p-4 text-right">Open</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($complaints as $complaint)
                        <tr wire:key="ops-complaint-{{ $complaint->id }}">
                            <td class="p-4 font-semibold text-gray-800">
                                <a href="{{ \App\Support\StaffOrderRoutes::show((int) $complaint->order_id, 'corporate') }}"
                                   class="font-mono text-middo-orange hover:underline">#{{ $complaint->order_id }}</a>
                                <div class="text-xs font-medium text-gray-500">
                                    {{ $complaint->order?->menuItem?->name ?? 'Menu' }}
                                </div>
                            </td>
                            <td class="p-4">{{ $complaint->order?->user?->name ?? '—' }}</td>
                            <td class="p-4">{{ $complaint->order?->orderGroup?->kitchen?->name ?? '—' }}</td>
                            <td class="p-4">{{ \App\Support\KitchenComplaints::categoryLabel($complaint->category) }}</td>
                            <td class="p-4 text-gray-600 max-w-xs truncate">{{ $complaint->message }}</td>
                            <td class="p-4 text-xs text-gray-500 whitespace-nowrap">
                                {{ $complaint->created_at?->timezone('Asia/Dhaka')->format('M j, g:i A') }}
                            </td>
                            <td class="p-4 text-right">
                                <a href="{{ route($rolePrefix.'.complaints.show', $complaint) }}"
                                   class="text-xs font-bold text-middo-orange hover:underline">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-10 text-center text-sm text-gray-400 italic">
                                No open complaint threads.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>{{ $complaints->links() }}</div>
</div>
