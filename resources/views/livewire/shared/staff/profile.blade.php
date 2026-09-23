<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="space-y-2">
        <a href="{{ $this->backRoute() }}" class="text-sm font-semibold text-middo-orange hover:underline">
            ← Back
        </a>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-center gap-4">
                @if($staffRole === 'kitchen')
                    <x-kitchen.avatar :url="$staff->profilePhotoUrl()" :name="$staff->name" class="h-16 w-16 text-xl" />
                @endif
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

    @if($staffRole === 'kitchen' && $this->canEditKitchenIdentity())
        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-4">
            <div>
                <h2 class="text-lg font-bold text-middo-dark">Identity</h2>
                <p class="text-sm text-gray-500 mt-1">Admin can change the kitchen name, phone, and address. Kitchens cannot.</p>
            </div>
            <form wire:submit="saveKitchenIdentity" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">First name</label>
                    <input type="text" wire:model="edit_first_name" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    @error('edit_first_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Last name</label>
                    <input type="text" wire:model="edit_last_name" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    @error('edit_last_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Phone</label>
                    <input type="text" wire:model="edit_mobile" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    @error('edit_mobile') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Email</label>
                    <input type="email" wire:model="edit_email" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    @error('edit_email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Address</label>
                    <input type="text" wire:model="edit_address" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    @error('edit_address') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">City</label>
                    <select wire:model.live="edit_city_id" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                        <option value="">Select city</option>
                        @foreach(\App\Models\City::query()->orderBy('name')->get() as $city)
                            <option value="{{ $city->id }}">{{ $city->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Area</label>
                    <select wire:model="edit_area_id" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                        <option value="">Select area</option>
                        @foreach($identityAreas as $area)
                            <option value="{{ $area->id }}">{{ $area->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" class="inline-flex px-4 py-2 rounded-xl bg-middo-orange text-white text-xs font-bold hover:bg-[#733614] transition">
                        Save identity
                    </button>
                </div>
            </form>
        </div>
    @endif

    @if($staffRole === 'kitchen' && $this->canManageKitchenRating())
        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-4">
            <div>
                <h2 class="text-lg font-bold text-middo-dark">Rating</h2>
                <p class="text-sm text-gray-500 mt-1">Admin only, on a 0–10 scale. Kitchens, operations, and riders do not see this.</p>
            </div>
            <form wire:submit="saveKitchenRating" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Score</label>
                        <input type="number" min="0" max="10" step="1" wire:model="kitchen_rating" placeholder="—" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                        @error('kitchen_rating') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-3">
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Note</label>
                        <textarea wire:model="kitchen_rating_note" rows="2" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" placeholder="Optional context for this score"></textarea>
                        @error('kitchen_rating_note') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
                <button type="submit" class="inline-flex px-4 py-2 rounded-xl bg-middo-orange text-white text-xs font-bold hover:bg-[#733614] transition">
                    Save rating
                </button>
            </form>
            <div class="border-t border-gray-100 pt-4 space-y-2">
                <h3 class="text-sm font-bold text-middo-dark">History</h3>
                @forelse($ratingHistory as $entry)
                    <div class="flex flex-wrap items-baseline justify-between gap-2 text-sm border border-gray-100 rounded-xl px-3 py-2">
                        <div>
                            <span class="font-semibold text-gray-800">
                                {{ $entry->old_rating === null ? '—' : $entry->old_rating }}
                                →
                                {{ $entry->new_rating === null ? '—' : $entry->new_rating }}
                            </span>
                            @if($entry->note)
                                <span class="text-gray-600">· {{ $entry->note }}</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-500">
                            {{ $entry->actor?->name ?: 'Admin' }}
                            · {{ $entry->created_at?->timezone('Asia/Dhaka')->format('M d, Y g:i A') }}
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-gray-400 italic">No rating changes yet.</p>
                @endforelse
            </div>
        </div>
    @endif

    @if($staffRole === 'kitchen' && $this->canManageKitchenVerification())
        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-4">
            <div>
                <h2 class="text-lg font-bold text-middo-dark">Verification</h2>
                <p class="text-sm text-gray-500 mt-1">NID photos, NID number, and chef selfie. The selfie is the kitchen profile photo. Uploads are compressed.</p>
            </div>
            <form wire:submit="saveKitchenVerification" class="space-y-4">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[12rem]">
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">NID number</label>
                        <input type="text" wire:model="nid_number" inputmode="numeric" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" placeholder="10–17 digits">
                        @error('nid_number') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    @if($staff->nid_number)
                        <button type="button" wire:click="clearKitchenNidNumber" wire:confirm="Remove this NID number?"
                                class="inline-flex px-3 py-2 rounded-xl border border-red-200 text-xs font-bold text-red-600 hover:bg-red-50">
                            Delete number
                        </button>
                    @endif
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    @foreach([
                        'nid_front' => ['label' => 'NID front', 'url' => $staff->nidFrontUrl()],
                        'nid_back' => ['label' => 'NID back', 'url' => $staff->nidBackUrl()],
                        'selfie' => ['label' => 'Chef selfie', 'url' => $staff->profilePhotoUrl()],
                    ] as $slot => $photo)
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">{{ $photo['label'] }}</p>
                            @if($photo['url'])
                                <img src="{{ $photo['url'] }}" alt="{{ $photo['label'] }}" class="mb-2 h-28 w-full rounded-xl object-cover border border-gray-200">
                                <button type="button" wire:click="deleteKitchenVerificationImage('{{ $slot }}')" wire:confirm="Delete this photo?"
                                        class="mb-2 text-xs font-bold text-red-600 hover:underline">
                                    Delete photo
                                </button>
                            @else
                                <p class="mb-2 text-xs text-gray-400 italic">No photo yet.</p>
                            @endif
                            <input type="file" wire:model="{{ $slot }}" accept="image/*" class="block w-full text-xs">
                            @error($slot) <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
                <button type="submit" class="inline-flex px-4 py-2 rounded-xl bg-middo-orange text-white text-xs font-bold hover:bg-[#733614] transition">
                    Save verification
                </button>
            </form>
        </div>
    @elseif($staffRole === 'kitchen' && $staff->profilePhotoUrl())
        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
            <h2 class="text-lg font-bold text-middo-dark mb-3">Profile photo</h2>
            <img src="{{ $staff->profilePhotoUrl() }}" alt="{{ $staff->name }}" class="h-28 w-28 rounded-2xl object-cover border border-gray-200">
        </div>
    @endif

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
