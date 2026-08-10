<div class="block w-full max-w-6xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-4">
        <div class="space-y-1">
            <h1 class="text-3xl font-bold text-gray-800">Coupons</h1>
            <p class="text-sm text-gray-500">Discount codes and charge waivers for corporate menu and package checkout. Every redemption is logged.</p>
        </div>
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 self-stretch md:self-auto">
            <div class="relative w-full sm:w-56">
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search code or name..."
                       class="w-full pl-3 pr-3 py-2.5 text-sm border border-gray-200 rounded-xl shadow-sm focus:ring-middo-orange focus:border-middo-orange">
            </div>
            <button type="button" wire:click="openCreate"
                    class="inline-flex items-center justify-center gap-2 bg-middo-orange hover:bg-[#733614] text-white font-bold text-sm px-4 py-2.5 rounded-xl shadow-sm transition whitespace-nowrap">
                + New coupon
            </button>
        </div>
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-900 text-sm font-semibold px-4 py-3">{{ $statusMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 text-red-800 text-sm font-semibold px-4 py-3">{{ $errorMessage }}</div>
    @endif

    @if($showForm)
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-5 sm:p-6 space-y-5">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-black text-gray-800">{{ $editingId ? 'Edit coupon' : 'Create coupon' }}</h2>
                <button type="button" wire:click="closeForm" class="text-sm font-bold text-gray-400 hover:text-gray-700">Close</button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-4">
                <div class="space-y-1.5">
                    <label class="block text-[11px] font-bold uppercase text-gray-500">Code</label>
                    <input wire:model="code" type="text" class="w-full border-gray-200 rounded-xl text-sm uppercase tracking-wider font-black" placeholder="LUNCH100">
                    @error('code') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="space-y-1.5">
                    <label class="block text-[11px] font-bold uppercase text-gray-500">Name</label>
                    <input wire:model="name" type="text" class="w-full border-gray-200 rounded-xl text-sm" placeholder="July lunch promo">
                    @error('name') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2 space-y-1.5">
                    <label class="block text-[11px] font-bold uppercase text-gray-500">Description</label>
                    <input wire:model="description" type="text" class="w-full border-gray-200 rounded-xl text-sm">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-[11px] font-bold uppercase text-gray-500">Effect</label>
                    <select wire:model.live="type" class="w-full border-gray-200 rounded-xl text-sm">
                        <option value="fixed">Fixed ৳ off</option>
                        <option value="percent">Percent % off</option>
                        <option value="waive_charge">Waive charges</option>
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label class="block text-[11px] font-bold uppercase text-gray-500">Applies to</label>
                    <select wire:model="applies_to" class="w-full border-gray-200 rounded-xl text-sm">
                        <option value="both">Menu orders + packages</option>
                        <option value="orders">Menu orders only</option>
                        <option value="packages">Packages only</option>
                    </select>
                </div>

                @if($type !== 'waive_charge')
                    <div class="space-y-1.5">
                        <label class="block text-[11px] font-bold uppercase text-gray-500">Value</label>
                        <input wire:model="value" type="number" min="1" class="w-full border-gray-200 rounded-xl text-sm">
                        @error('value') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-[11px] font-bold uppercase text-gray-500">Min subtotal (৳)</label>
                        <input wire:model="min_subtotal" type="number" min="0" class="w-full border-gray-200 rounded-xl text-sm">
                    </div>
                @else
                    <div class="space-y-1.5">
                        <label class="block text-[11px] font-bold uppercase text-gray-500">Charge category</label>
                        <select wire:model="waive_charge_category" class="w-full border-gray-200 rounded-xl text-sm">
                            <option value="">Any category</option>
                            <option value="delivery">Delivery</option>
                            <option value="handling">Handling</option>
                            <option value="packaging">Packaging</option>
                            <option value="other">Other</option>
                        </select>
                        @error('waive_charge_category') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-[11px] font-bold uppercase text-gray-500">Specific charge (optional)</label>
                        <select wire:model="waive_charge_id" class="w-full border-gray-200 rounded-xl text-sm">
                            <option value="">All matching category</option>
                            @foreach($charges as $charge)
                                <option value="{{ $charge->id }}">{{ $charge->name }} · {{ $charge->category }} · ৳{{ number_format($charge->amount) }}</option>
                            @endforeach
                        </select>
                        @error('waive_charge_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div class="space-y-1.5">
                    <label class="block text-[11px] font-bold uppercase text-gray-500">
                        {{ $type === 'waive_charge' ? 'Max waive (৳, optional)' : 'Max discount (৳, optional)' }}
                    </label>
                    <input wire:model="max_discount" type="number" min="1" class="w-full border-gray-200 rounded-xl text-sm" placeholder="{{ $type === 'waive_charge' ? 'Cap waived amount' : 'Cap for % coupons' }}">
                </div>
                <div class="space-y-1.5">
                    <label class="block text-[11px] font-bold uppercase text-gray-500">Global usage limit</label>
                    <input wire:model="usage_limit" type="number" min="1" class="w-full border-gray-200 rounded-xl text-sm" placeholder="Unlimited">
                </div>
                <div class="space-y-1.5">
                    <label class="block text-[11px] font-bold uppercase text-gray-500">Per-user limit</label>
                    <input wire:model="per_user_limit" type="number" min="1" class="w-full border-gray-200 rounded-xl text-sm">
                </div>
                <div class="flex items-center gap-2 pt-6">
                    <input id="coupon-active" type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-middo-orange focus:ring-middo-orange">
                    <label for="coupon-active" class="text-sm font-semibold text-gray-700">Active</label>
                </div>
            </div>

            <div class="border-t border-gray-100 pt-5 space-y-4">
                <div>
                    <h3 class="text-sm font-black uppercase tracking-wider text-gray-600">Eligibility</h3>
                    <p class="text-xs text-gray-400 mt-1">Leave lists empty to allow all. Selections restrict who can use the code.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-4">
                    <div class="space-y-1.5">
                        <label class="block text-[11px] font-bold uppercase text-gray-500">Menus (optional)</label>
                        <select wire:model="eligible_menu_item_ids" multiple class="w-full border-gray-200 rounded-xl text-sm min-h-[6.5rem]">
                            @foreach($menus as $menu)
                                <option value="{{ $menu->id }}">{{ $menu->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-[11px] font-bold uppercase text-gray-500">Areas (optional)</label>
                        <select wire:model="eligible_area_ids" multiple class="w-full border-gray-200 rounded-xl text-sm min-h-[6.5rem]">
                            @foreach($areas as $area)
                                <option value="{{ $area->id }}">{{ $area->name }}@if($area->city) — {{ $area->city->name }}@endif</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-1.5 md:col-span-2">
                        <label class="block text-[11px] font-bold uppercase text-gray-500">Companies (optional whitelist)</label>
                        <select wire:model="eligible_company_ids" multiple class="w-full border-gray-200 rounded-xl text-sm min-h-[6.5rem]">
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-[11px] font-bold uppercase text-gray-500">Min seats / items</label>
                        <input wire:model="min_quantity" type="number" min="1" class="w-full border-gray-200 rounded-xl text-sm" placeholder="No minimum">
                    </div>
                    <div class="flex items-center gap-2 pt-6">
                        <input id="coupon-first-order" type="checkbox" wire:model="first_order_only" class="rounded border-gray-300 text-middo-orange focus:ring-middo-orange">
                        <label for="coupon-first-order" class="text-sm font-semibold text-gray-700">First-order only</label>
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-[11px] font-bold uppercase text-gray-500">Starts at</label>
                        <input wire:model="starts_at" type="datetime-local" class="w-full border-gray-200 rounded-xl text-sm">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-[11px] font-bold uppercase text-gray-500">Ends at</label>
                        <input wire:model="ends_at" type="datetime-local" class="w-full border-gray-200 rounded-xl text-sm">
                        @error('ends_at') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-1">
                <button type="button" wire:click="closeForm" class="px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-bold text-gray-600">Cancel</button>
                <button type="button" wire:click="save" class="px-4 py-2.5 rounded-xl bg-middo-orange text-white text-sm font-bold">Save coupon</button>
            </div>
        </div>
    @endif

    <div class="bg-white shadow-md border border-gray-100 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[960px]">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <th class="px-4 py-3.5">Code</th>
                        <th class="px-4 py-3.5">Effect</th>
                        <th class="px-4 py-3.5">Applies</th>
                        <th class="px-4 py-3.5 text-center">Used</th>
                        <th class="px-4 py-3.5 text-center">Status</th>
                        <th class="px-4 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($coupons as $coupon)
                        <tr wire:key="coupon-{{ $coupon->id }}" class="hover:bg-gray-50/70">
                            <td class="px-4 py-3.5">
                                <div class="font-black tracking-wider text-gray-800">{{ $coupon->code }}</div>
                                <div class="text-xs text-gray-500 mt-0.5">{{ $coupon->name }}</div>
                            </td>
                            <td class="px-4 py-3.5 font-semibold">
                                {{ $coupon->effectLabel() }}
                                @if($coupon->min_subtotal > 0)
                                    <div class="text-[11px] text-gray-400 font-medium mt-0.5">min ৳{{ number_format($coupon->min_subtotal) }}</div>
                                @endif
                                @if($coupon->firstOrderOnly() || $coupon->minQuantity() || $coupon->eligibleMenuItemIds() || $coupon->eligibleAreaIds() || $coupon->eligibleCompanyIds())
                                    <div class="text-[11px] text-gray-400 font-medium mt-0.5">Scoped</div>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 capitalize text-gray-700">{{ $coupon->applies_to }}</td>
                            <td class="px-4 py-3.5 text-center font-bold text-middo-orange">
                                {{ $coupon->redemptions_count }}{{ $coupon->usage_limit ? ' / '.$coupon->usage_limit : '' }}
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <span @class([
                                    'px-2.5 py-1 rounded-full text-[11px] font-bold uppercase',
                                    'bg-emerald-100 text-emerald-800' => $coupon->is_active,
                                    'bg-gray-100 text-gray-500' => ! $coupon->is_active,
                                ])>{{ $coupon->is_active ? 'Active' : 'Off' }}</span>
                            </td>
                            <td class="px-4 py-3.5 text-right space-x-3">
                                <button type="button" wire:click="openEdit({{ $coupon->id }})" class="text-xs font-bold text-middo-orange hover:underline">Edit</button>
                                <button type="button" wire:click="toggleActive({{ $coupon->id }})" class="text-xs font-bold text-gray-500 hover:underline">
                                    {{ $coupon->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-gray-400 italic">No coupons yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-4 border-t border-gray-100">{{ $coupons->links() }}</div>
    </div>

    <div class="bg-white shadow-md border border-gray-100 rounded-2xl overflow-hidden">
        <div class="px-4 py-3.5 border-b border-gray-100">
            <h2 class="text-sm font-black uppercase tracking-wider text-gray-600">Recent redemptions</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm min-w-[700px]">
                <thead>
                    <tr class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <th class="px-4 py-3">When</th>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">Corporate</th>
                        <th class="px-4 py-3">Context</th>
                        <th class="px-4 py-3 text-right">Discount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($recentRedemptions as $row)
                        <tr>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ $row->created_at?->timezone('Asia/Dhaka')->format('M d, Y H:i') }}</td>
                            <td class="px-4 py-3 font-black tracking-wider">{{ $row->code }}</td>
                            <td class="px-4 py-3">
                                {{ $row->user?->company_name ?: trim(($row->user?->first_name.' '.$row->user?->last_name)) }}
                                <div class="text-[11px] text-gray-400 mt-0.5">{{ $row->user?->mobile }}</div>
                            </td>
                            <td class="px-4 py-3 capitalize">
                                {{ $row->context }}
                                @if($row->order_id) · order #{{ $row->order_id }} @endif
                                @if($row->package_subscription_id) · pkg #{{ $row->package_subscription_id }} @endif
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-emerald-700">
                                −৳{{ number_format($row->discount_amount) }}
                                <div class="text-[11px] text-gray-400 font-medium mt-0.5">
                                    ৳{{ number_format($row->original_amount) }} → ৳{{ number_format($row->final_amount) }}
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400 italic">No redemptions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
