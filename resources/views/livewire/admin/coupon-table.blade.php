<div class="block w-full max-w-6xl mx-auto py-8 px-4 sm:px-6">
    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Coupons</h1>
            <p class="text-sm text-gray-500 mt-1">Create discount codes for corporate menu and package checkout. Every redemption is logged.</p>
        </div>
        <div class="flex items-center gap-3 self-end md:self-auto">
            <div class="relative w-56">
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search code or name..."
                       class="w-full pl-3 pr-3 py-2 text-sm border border-gray-200 rounded-xl shadow-sm focus:ring-middo-orange focus:border-middo-orange">
            </div>
            <button type="button" wire:click="openCreate"
                    class="inline-flex items-center gap-2 bg-middo-orange hover:bg-[#733614] text-white font-bold text-sm px-4 py-2.5 rounded-xl shadow-sm transition">
                + New coupon
            </button>
        </div>
    </div>

    @if($statusMessage)
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-900 text-sm font-semibold px-4 py-3">{{ $statusMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 text-red-800 text-sm font-semibold px-4 py-3">{{ $errorMessage }}</div>
    @endif

    @if($showForm)
        <div class="mb-6 bg-white border border-gray-100 rounded-2xl shadow-sm p-5 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-black text-gray-800">{{ $editingId ? 'Edit coupon' : 'Create coupon' }}</h2>
                <button type="button" wire:click="closeForm" class="text-sm font-bold text-gray-400 hover:text-gray-700">Close</button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[11px] font-bold uppercase text-gray-500 mb-1">Code</label>
                    <input wire:model="code" type="text" class="w-full border-gray-200 rounded-xl text-sm uppercase tracking-wider font-black" placeholder="LUNCH100">
                    @error('code') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase text-gray-500 mb-1">Name</label>
                    <input wire:model="name" type="text" class="w-full border-gray-200 rounded-xl text-sm" placeholder="July lunch promo">
                    @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2">
                    <label class="block text-[11px] font-bold uppercase text-gray-500 mb-1">Description</label>
                    <input wire:model="description" type="text" class="w-full border-gray-200 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase text-gray-500 mb-1">Type</label>
                    <select wire:model="type" class="w-full border-gray-200 rounded-xl text-sm">
                        <option value="fixed">Fixed ৳ off</option>
                        <option value="percent">Percent % off</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase text-gray-500 mb-1">Value</label>
                    <input wire:model="value" type="number" min="1" class="w-full border-gray-200 rounded-xl text-sm">
                    @error('value') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase text-gray-500 mb-1">Min subtotal (৳)</label>
                    <input wire:model="min_subtotal" type="number" min="0" class="w-full border-gray-200 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase text-gray-500 mb-1">Max discount (৳, optional)</label>
                    <input wire:model="max_discount" type="number" min="1" class="w-full border-gray-200 rounded-xl text-sm" placeholder="Cap for % coupons">
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase text-gray-500 mb-1">Global usage limit</label>
                    <input wire:model="usage_limit" type="number" min="1" class="w-full border-gray-200 rounded-xl text-sm" placeholder="Unlimited">
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase text-gray-500 mb-1">Per-user limit</label>
                    <input wire:model="per_user_limit" type="number" min="1" class="w-full border-gray-200 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase text-gray-500 mb-1">Applies to</label>
                    <select wire:model="applies_to" class="w-full border-gray-200 rounded-xl text-sm">
                        <option value="both">Menu orders + packages</option>
                        <option value="orders">Menu orders only</option>
                        <option value="packages">Packages only</option>
                    </select>
                </div>
                <div class="flex items-center gap-2 pt-6">
                    <input id="coupon-active" type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-middo-orange focus:ring-middo-orange">
                    <label for="coupon-active" class="text-sm font-semibold text-gray-700">Active</label>
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase text-gray-500 mb-1">Starts at</label>
                    <input wire:model="starts_at" type="datetime-local" class="w-full border-gray-200 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase text-gray-500 mb-1">Ends at</label>
                    <input wire:model="ends_at" type="datetime-local" class="w-full border-gray-200 rounded-xl text-sm">
                    @error('ends_at') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" wire:click="closeForm" class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-bold text-gray-600">Cancel</button>
                <button type="button" wire:click="save" class="px-4 py-2 rounded-xl bg-middo-orange text-white text-sm font-bold">Save coupon</button>
            </div>
        </div>
    @endif

    <div class="bg-white shadow-md border border-gray-100 rounded-2xl overflow-hidden mb-8">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[900px]">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <th class="p-4">Code</th>
                        <th class="p-4">Discount</th>
                        <th class="p-4">Applies</th>
                        <th class="p-4 text-center">Used</th>
                        <th class="p-4 text-center">Status</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($coupons as $coupon)
                        <tr wire:key="coupon-{{ $coupon->id }}" class="hover:bg-gray-50/70">
                            <td class="p-4">
                                <div class="font-black tracking-wider text-gray-800">{{ $coupon->code }}</div>
                                <div class="text-xs text-gray-500">{{ $coupon->name }}</div>
                            </td>
                            <td class="p-4 font-semibold">
                                @if($coupon->type === 'percent')
                                    {{ $coupon->value }}%
                                    @if($coupon->max_discount) <span class="text-xs text-gray-400">max ৳{{ number_format($coupon->max_discount) }}</span> @endif
                                @else
                                    ৳{{ number_format($coupon->value) }}
                                @endif
                                @if($coupon->min_subtotal > 0)
                                    <div class="text-[11px] text-gray-400">min ৳{{ number_format($coupon->min_subtotal) }}</div>
                                @endif
                            </td>
                            <td class="p-4 capitalize text-gray-700">{{ $coupon->applies_to }}</td>
                            <td class="p-4 text-center font-bold text-middo-orange">
                                {{ $coupon->redemptions_count }}{{ $coupon->usage_limit ? ' / '.$coupon->usage_limit : '' }}
                            </td>
                            <td class="p-4 text-center">
                                <span @class([
                                    'px-2.5 py-1 rounded-full text-[11px] font-bold uppercase',
                                    'bg-emerald-100 text-emerald-800' => $coupon->is_active,
                                    'bg-gray-100 text-gray-500' => ! $coupon->is_active,
                                ])>{{ $coupon->is_active ? 'Active' : 'Off' }}</span>
                            </td>
                            <td class="p-4 text-right space-x-2">
                                <button type="button" wire:click="openEdit({{ $coupon->id }})" class="text-xs font-bold text-middo-orange hover:underline">Edit</button>
                                <button type="button" wire:click="toggleActive({{ $coupon->id }})" class="text-xs font-bold text-gray-500 hover:underline">
                                    {{ $coupon->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-gray-400 italic">No coupons yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-100">{{ $coupons->links() }}</div>
    </div>

    <div class="bg-white shadow-md border border-gray-100 rounded-2xl overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100">
            <h2 class="text-sm font-black uppercase tracking-wider text-gray-600">Recent redemptions</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm min-w-[700px]">
                <thead>
                    <tr class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <th class="p-3">When</th>
                        <th class="p-3">Code</th>
                        <th class="p-3">Corporate</th>
                        <th class="p-3">Context</th>
                        <th class="p-3 text-right">Discount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($recentRedemptions as $row)
                        <tr>
                            <td class="p-3 text-xs text-gray-500">{{ $row->created_at?->timezone('Asia/Dhaka')->format('M d, Y H:i') }}</td>
                            <td class="p-3 font-black tracking-wider">{{ $row->code }}</td>
                            <td class="p-3">
                                {{ $row->user?->company_name ?: trim(($row->user?->first_name.' '.$row->user?->last_name)) }}
                                <div class="text-[11px] text-gray-400">{{ $row->user?->mobile }}</div>
                            </td>
                            <td class="p-3 capitalize">
                                {{ $row->context }}
                                @if($row->order_id) · order #{{ $row->order_id }} @endif
                                @if($row->package_subscription_id) · pkg #{{ $row->package_subscription_id }} @endif
                            </td>
                            <td class="p-3 text-right font-bold text-emerald-700">
                                −৳{{ number_format($row->discount_amount) }}
                                <div class="text-[11px] text-gray-400 font-medium">
                                    ৳{{ number_format($row->original_amount) }} → ৳{{ number_format($row->final_amount) }}
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-6 text-center text-gray-400 italic">No redemptions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
