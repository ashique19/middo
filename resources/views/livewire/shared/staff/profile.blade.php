<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="space-y-2">
        <a href="{{ $this->backRoute() }}" class="text-sm font-semibold text-middo-orange hover:underline">
            ← Back
        </a>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">{{ $staffRole }} profile</p>
                <h1 class="text-3xl font-bold text-middo-dark">
                    {{ $staff->name ?: trim($staff->first_name.' '.$staff->last_name) }}
                </h1>
                <p class="text-sm text-gray-500 mt-1">
                    {{ $staff->mobile }}
                    @if($staff->email)
                        · {{ $staff->email }}
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span @class([
                    'px-2.5 py-1 rounded-full text-[11px] font-bold uppercase border',
                    'bg-emerald-100 text-emerald-800 border-emerald-200' => $staff->status === 'active',
                    'bg-yellow-100 text-yellow-800 border-yellow-200' => $staff->status === 'pending',
                    'bg-gray-100 text-gray-600 border-gray-200' => ! in_array($staff->status, ['active', 'pending'], true),
                ])>
                    {{ $staff->status === 'inactive' ? 'suspended' : $staff->status }}
                </span>
                @if($this->canManageKitchenStatus())
                    @if($staff->status !== 'active')
                        <button type="button"
                                wire:click="activate"
                                wire:confirm="Activate {{ $staff->name }}? They will be able to log in."
                                class="inline-flex px-3 py-1.5 rounded-xl text-xs font-bold bg-emerald-600 text-white hover:bg-emerald-700 transition">
                            Activate
                        </button>
                    @endif
                    @if($staff->status !== 'inactive')
                        <button type="button"
                                wire:click="suspend"
                                wire:confirm="Suspend {{ $staff->name }}? They will not be able to log in."
                                class="inline-flex px-3 py-1.5 rounded-xl text-xs font-bold border border-red-200 text-red-600 hover:bg-red-50 transition">
                            Suspend
                        </button>
                    @endif
                @endif
                @if($this->kitchenOrdersRoute())
                    <a href="{{ $this->kitchenOrdersRoute() }}"
                       class="inline-flex px-3 py-1.5 rounded-xl border border-gray-200 text-xs font-bold text-middo-dark hover:border-middo-orange hover:text-middo-orange transition">
                        All kitchen orders →
                    </a>
                @endif
            </div>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="bg-emerald-50 text-emerald-800 border border-emerald-200 px-4 py-3 rounded-xl text-sm font-semibold">
            {{ session('message') }}
        </div>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Total orders</p>
            <p class="text-2xl font-black text-middo-dark">{{ number_format($stats['total_orders']) }}</p>
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Active</p>
            <p class="text-2xl font-black text-middo-dark">{{ number_format($stats['active_orders']) }}</p>
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Delivered</p>
            <p class="text-2xl font-black text-middo-dark">{{ number_format($stats['delivered_orders']) }}</p>
        </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
        <h2 class="text-lg font-bold text-middo-dark mb-4">Profile details</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
            <div>
                <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Name</dt>
                <dd class="font-semibold text-gray-800 mt-0.5">{{ $staff->first_name }} {{ $staff->last_name }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Mobile</dt>
                <dd class="font-mono font-semibold text-gray-800 mt-0.5">{{ $staff->mobile ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Email</dt>
                <dd class="font-semibold text-gray-800 mt-0.5">{{ $staff->email ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Address</dt>
                <dd class="font-semibold text-gray-800 mt-0.5">{{ $staff->address ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Location</dt>
                <dd class="font-semibold text-gray-800 mt-0.5">
                    {{ $staff->area_name ?: '—' }}@if($staff->city_name), {{ $staff->city_name }}@endif
                </dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Joined</dt>
                <dd class="font-semibold text-gray-800 mt-0.5">
                    {{ $staff->created_at?->timezone('Asia/Dhaka')->format('M d, Y') ?: '—' }}
                </dd>
            </div>
            @if($staffRole === 'kitchen')
                <div>
                    <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Tier</dt>
                    <dd class="font-semibold text-gray-800 mt-0.5 capitalize">{{ $staff->kitchen_tier ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Allowed open groups</dt>
                    <dd class="font-semibold text-gray-800 mt-0.5">
                        {{ $staff->allowed_open_groups !== null ? $staff->allowed_open_groups : '—' }}
                    </dd>
                </div>
            @endif
        </dl>
    </div>

    @if($staffRole === 'kitchen')
        @if($this->canEditKitchenHours())
            <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-4">
                <div>
                    <h2 class="text-lg font-bold text-middo-dark">Weekly hours</h2>
                    <p class="text-sm text-gray-500 mt-1">Ops can update kitchen operating hours without waiting on the kitchen login.</p>
                </div>
                @if($hoursStatusMessage)
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ $hoursStatusMessage }}</div>
                @endif
                @if($hoursErrorMessage)
                    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $hoursErrorMessage }}</div>
                @endif
                <div class="space-y-2">
                    @foreach($dayLabels as $day => $label)
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 items-center text-sm">
                            <div class="font-semibold text-gray-700">{{ $label }}</div>
                            <label class="inline-flex items-center gap-2 text-xs font-semibold text-gray-500">
                                <input type="checkbox" wire:model.live="hours.{{ $day }}.is_closed" class="rounded border-gray-300 text-middo-orange focus:ring-middo-orange">
                                Closed
                            </label>
                            <input type="time" wire:model="hours.{{ $day }}.opens_at"
                                   @disabled(!empty($hours[$day]['is_closed']))
                                   class="rounded-xl border border-gray-200 px-3 py-2 text-sm disabled:opacity-40">
                            <input type="time" wire:model="hours.{{ $day }}.closes_at"
                                   @disabled(!empty($hours[$day]['is_closed']))
                                   class="rounded-xl border border-gray-200 px-3 py-2 text-sm disabled:opacity-40">
                        </div>
                    @endforeach
                </div>
                <button type="button" wire:click="saveKitchenHours"
                        class="inline-flex px-4 py-2 rounded-xl bg-middo-orange text-white text-xs font-bold hover:bg-[#733614] transition">
                    Save hours
                </button>
            </div>
        @else
            <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
                <h2 class="text-lg font-bold text-middo-dark mb-4">Weekly hours</h2>
                @if($kitchenHours->isEmpty())
                    <p class="text-sm text-gray-400 italic">No hours set yet.</p>
                @else
                    <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-sm">
                        @foreach($kitchenHours as $hour)
                            <div>
                                <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">{{ $hour->dayLabel() }}</dt>
                                <dd class="font-semibold text-gray-800 mt-0.5">{{ $hour->hoursLabel() }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>
        @endif
    @endif

    @if($staffRole === 'kitchen' && $this->canEditKitchenCapacity())
        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-4">
            <div>
                <h2 class="text-lg font-bold text-middo-dark">Kitchen capacity</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Tier defaults come from Settings on activation. Ops can override allowed open groups for this kitchen anytime.
                </p>
            </div>
            <form wire:submit="saveKitchenCapacity" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Tier</label>
                    <select wire:model="edit_kitchen_tier"
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-middo-orange focus:ring-middo-orange">
                        <option value="silver">Silver</option>
                        <option value="gold">Gold</option>
                        <option value="platinum">Platinum</option>
                    </select>
                    @error('edit_kitchen_tier') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Allowed open groups</label>
                    <input type="number" min="0" max="100" wire:model="edit_allowed_open_groups"
                           class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-middo-orange focus:ring-middo-orange">
                    @error('edit_allowed_open_groups') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="submit"
                            class="inline-flex px-4 py-2 rounded-xl bg-middo-orange text-white text-xs font-bold hover:bg-[#733614] transition">
                        Save
                    </button>
                    <button type="button"
                            wire:click="resetAllowedToTierDefault"
                            wire:confirm="Reset allowed open groups to the current Settings default for this tier?"
                            class="inline-flex px-4 py-2 rounded-xl border border-gray-200 text-xs font-bold text-middo-dark hover:border-middo-orange transition">
                        Reset to tier default
                    </button>
                </div>
            </form>
        </div>
    @endif

    @if($this->canEditRiderAreas())
        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-4">
            <div>
                <h2 class="text-lg font-bold text-middo-dark">Service areas</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Attach areas this rider can serve. First selected area becomes the primary profile location.
                </p>
            </div>
            @if($areasStatusMessage)
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ $areasStatusMessage }}</div>
            @endif
            @if($areasErrorMessage)
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $areasErrorMessage }}</div>
            @endif
            <div class="space-y-4 max-h-80 overflow-y-auto">
                @forelse($areaOptions as $city)
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2">{{ $city->name }}</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($city->areas as $area)
                                <label class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border border-gray-200 text-sm cursor-pointer hover:border-middo-orange">
                                    <input type="checkbox" value="{{ $area->id }}" wire:model="selectedAreaIds"
                                           class="rounded border-gray-300 text-middo-orange focus:ring-middo-orange">
                                    {{ $area->name }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400 italic">No areas configured yet.</p>
                @endforelse
            </div>
            <button type="button" wire:click="saveRiderAreas"
                    class="inline-flex px-4 py-2 rounded-xl bg-middo-orange text-white text-xs font-bold hover:bg-[#733614] transition">
                Save service areas
            </button>
        </div>
    @endif

    <div class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold text-middo-dark">
                    {{ $staffRole === 'kitchen' ? 'Kitchen orders' : 'Delivery orders' }}
                </h2>
                <p class="text-xs font-semibold text-gray-400">Newest first</p>
            </div>
            <x-orders.view-mode-toggle :view-mode="$viewMode" :exportable="true" />
        </div>
        @if($viewMode === 'list')
            <x-operation.orders.table
                :orders="$orderRows"
                :show-group="$staffRole === 'kitchen'"
                empty-message="No orders linked to this profile yet." />
        @else
            <x-operation.orders.cards
                :orders="$orderRows"
                :show-group="$staffRole === 'kitchen'"
                empty-message="No orders linked to this profile yet." />
        @endif
        @if($orders->hasPages())
            <div class="px-1">
                {{ $orders->links() }}
            </div>
        @endif
    </div>
</div>
