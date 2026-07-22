<div class="space-y-6">
    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-900">
            {{ $statusMessage }}
        </div>
    @endif
    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-900">
            {{ $errorMessage }}
        </div>
    @endif

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-black text-gray-900 tracking-tight">Charges</h1>
            <p class="text-sm text-gray-500 mt-1">Global and scoped fees applied at corporate menu or package checkout.</p>
        </div>
        <button type="button" wire:click="openCreate"
                class="inline-flex items-center justify-center rounded-xl bg-middo-orange px-4 py-2.5 text-sm font-bold text-white hover:bg-[#733614] transition">
            + Add charge
        </button>
    </div>

    <div class="flex items-center gap-3">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name or category…"
               class="w-full max-w-md rounded-xl border-gray-200 text-sm shadow-sm focus:border-middo-orange focus:ring-middo-orange">
    </div>

    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs font-bold uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Amount</th>
                    <th class="px-4 py-3">Calc</th>
                    <th class="px-4 py-3">Scope</th>
                    <th class="px-4 py-3">Applies</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($charges as $charge)
                    <tr wire:key="charge-{{ $charge->id }}" class="hover:bg-gray-50/80">
                        <td class="px-4 py-3">
                            <div class="font-bold text-gray-900">{{ $charge->name }}</div>
                            <div class="text-xs text-gray-500 capitalize">{{ $charge->category }}</div>
                        </td>
                        <td class="px-4 py-3 font-semibold text-gray-900">৳{{ number_format($charge->amount) }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ str_replace('_', ' ', $charge->calculation) }}</td>
                        <td class="px-4 py-3 text-gray-600 text-xs">
                            <div>{{ $charge->area?->name ?? 'All areas' }}</div>
                            <div>{{ $charge->menuItem?->name ?? 'All menus' }}</div>
                        </td>
                        <td class="px-4 py-3 capitalize text-gray-600">{{ $charge->applies_to }}</td>
                        <td class="px-4 py-3">
                            @if($charge->is_active)
                                <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-bold text-emerald-700">Active</span>
                            @else
                                <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-bold text-gray-500">Off</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                            <button type="button" wire:click="openEdit({{ $charge->id }})" class="text-xs font-bold text-blue-600 hover:underline">Edit</button>
                            <button type="button" wire:click="toggleActive({{ $charge->id }})" class="text-xs font-bold text-gray-600 hover:underline">
                                {{ $charge->is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-gray-500">No charges yet. Add delivery, handling, or packaging fees.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $charges->links() }}</div>

    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm p-4 space-y-3">
        <h2 class="text-sm font-black uppercase tracking-wider text-gray-400">Recent charge applications</h2>
        @forelse($recentUsages as $usage)
            <div class="flex items-start justify-between gap-3 text-sm border-b border-gray-50 pb-2 last:border-0">
                <div>
                    <div class="font-semibold text-gray-900">{{ $usage['name'] }} · ৳{{ number_format($usage['amount']) }}</div>
                    <div class="text-xs text-gray-500">
                        {{ $usage['label'] }}
                        @if($usage['user'])
                            — {{ trim(($usage['user']->first_name ?? '').' '.($usage['user']->last_name ?? '')) }}
                            @if($usage['user']->company_name) ({{ $usage['user']->company_name }})@endif
                        @endif
                    </div>
                </div>
                <div class="text-xs text-gray-400 whitespace-nowrap">{{ $usage['created_at']?->timezone('Asia/Dhaka')->format('M j, H:i') }}</div>
            </div>
        @empty
            <p class="text-sm text-gray-500">No applications yet.</p>
        @endforelse
    </div>

    @if($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:click.self="closeForm">
            <div class="w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-xl p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-black text-gray-900">{{ $editingId ? 'Edit charge' : 'New charge' }}</h2>
                    <button type="button" wire:click="closeForm" class="text-gray-400 hover:text-gray-700 text-xl leading-none">&times;</button>
                </div>

                <div class="grid grid-cols-1 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Name</label>
                        <input type="text" wire:model="name" class="w-full rounded-xl border-gray-200 text-sm" placeholder="Delivery charge">
                        @error('name') <span class="text-xs text-red-600 font-semibold">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Category</label>
                            <select wire:model="category" class="w-full rounded-xl border-gray-200 text-sm">
                                <option value="delivery">Delivery</option>
                                <option value="handling">Handling</option>
                                <option value="packaging">Packaging</option>
                                <option value="other">Other</option>
                            </select>
                            @error('category') <span class="text-xs text-red-600 font-semibold">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Amount (৳)</label>
                            <input type="number" min="1" wire:model="amount" class="w-full rounded-xl border-gray-200 text-sm">
                            @error('amount') <span class="text-xs text-red-600 font-semibold">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Calculation</label>
                        <select wire:model="calculation" class="w-full rounded-xl border-gray-200 text-sm">
                            <option value="per_delivery">Per delivery date</option>
                            <option value="per_item">Per item (quantity)</option>
                            <option value="per_checkout">Once per checkout</option>
                        </select>
                        @error('calculation') <span class="text-xs text-red-600 font-semibold">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Area (optional)</label>
                            <select wire:model="area_id" class="w-full rounded-xl border-gray-200 text-sm">
                                <option value="">All areas</option>
                                @foreach($areas as $area)
                                    <option value="{{ $area->id }}">{{ $area->name }}@if($area->city) — {{ $area->city->name }}@endif</option>
                                @endforeach
                            </select>
                            @error('area_id') <span class="text-xs text-red-600 font-semibold">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Menu (optional)</label>
                            <select wire:model="menu_item_id" class="w-full rounded-xl border-gray-200 text-sm">
                                <option value="">All menus</option>
                                @foreach($menus as $menu)
                                    <option value="{{ $menu->id }}">{{ $menu->name }}</option>
                                @endforeach
                            </select>
                            @error('menu_item_id') <span class="text-xs text-red-600 font-semibold">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Applies to</label>
                        <select wire:model="applies_to" class="w-full rounded-xl border-gray-200 text-sm">
                            <option value="both">Menu orders &amp; packages</option>
                            <option value="orders">Menu orders only</option>
                            <option value="packages">Packages only</option>
                        </select>
                        @error('applies_to') <span class="text-xs text-red-600 font-semibold">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Description</label>
                        <input type="text" wire:model="description" class="w-full rounded-xl border-gray-200 text-sm" placeholder="Optional note">
                        @error('description') <span class="text-xs text-red-600 font-semibold">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Starts</label>
                            <input type="datetime-local" wire:model="starts_at" class="w-full rounded-xl border-gray-200 text-sm">
                            @error('starts_at') <span class="text-xs text-red-600 font-semibold">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Ends</label>
                            <input type="datetime-local" wire:model="ends_at" class="w-full rounded-xl border-gray-200 text-sm">
                            @error('ends_at') <span class="text-xs text-red-600 font-semibold">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700">
                        <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-middo-orange focus:ring-middo-orange">
                        Active
                    </label>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" wire:click="closeForm" class="rounded-xl px-4 py-2 text-sm font-bold text-gray-600 hover:bg-gray-100">Cancel</button>
                    <button type="button" wire:click="save" class="rounded-xl bg-middo-orange px-4 py-2 text-sm font-bold text-white hover:bg-[#733614]">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
