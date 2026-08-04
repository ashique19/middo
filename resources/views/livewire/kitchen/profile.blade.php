<div class="max-w-3xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="space-y-2">
        <a href="{{ route('kitchen.dashboard') }}" class="text-sm font-semibold text-middo-orange hover:underline">← Dashboard</a>
        <h1 class="text-3xl font-bold text-middo-dark">Kitchen profile</h1>
        <p class="text-sm font-semibold text-gray-500">
            Contact details and weekly operating hours. Tier and capacity are managed by Middo.
        </p>
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

    <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm text-sm text-gray-600 flex flex-wrap gap-4">
        <p><span class="font-bold text-middo-dark">Status:</span> {{ $status }}</p>
        <p><span class="font-bold text-middo-dark">Tier:</span> <span class="capitalize">{{ $tier ?: '—' }}</span></p>
        <p><span class="font-bold text-middo-dark">Open group slots:</span> {{ $allowedOpenGroups !== null ? $allowedOpenGroups : '—' }}</p>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-4">
            <h2 class="text-lg font-bold text-middo-dark">Contact</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">First name</label>
                    <input type="text" wire:model="first_name" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    @error('first_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Last name</label>
                    <input type="text" wire:model="last_name" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    @error('last_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Mobile</label>
                    <input type="text" wire:model="mobile" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    @error('mobile') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Email</label>
                    <input type="email" wire:model="email" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Address</label>
                    <input type="text" wire:model="address" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    @error('address') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">City</label>
                    <select wire:model.live="city_id" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                        <option value="">Select city</option>
                        @foreach($cities as $city)
                            <option value="{{ $city->id }}">{{ $city->name }}</option>
                        @endforeach
                    </select>
                    @error('city_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Area</label>
                    <select wire:model="area_id" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                        <option value="">Select area</option>
                        @foreach($areas as $area)
                            <option value="{{ $area->id }}">{{ $area->name }}</option>
                        @endforeach
                    </select>
                    @error('area_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm space-y-4">
            <h2 class="text-lg font-bold text-middo-dark">Weekly hours</h2>
            <div class="space-y-3">
                @foreach($dayLabels as $day => $label)
                    <div wire:key="hour-{{ $day }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-center border-b border-gray-50 pb-3">
                        <p class="text-sm font-bold text-middo-dark">{{ $label }}</p>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                            <input type="checkbox" wire:model.live="hours.{{ $day }}.is_closed" class="rounded border-gray-300 text-middo-orange focus:ring-middo-orange">
                            Closed
                        </label>
                        <input type="time" wire:model="hours.{{ $day }}.opens_at"
                               @disabled(!empty($hours[$day]['is_closed']))
                               class="rounded-xl border border-gray-200 px-3 py-2 text-sm disabled:opacity-50">
                        <input type="time" wire:model="hours.{{ $day }}.closes_at"
                               @disabled(!empty($hours[$day]['is_closed']))
                               class="rounded-xl border border-gray-200 px-3 py-2 text-sm disabled:opacity-50">
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                    class="inline-flex px-5 py-2.5 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-sm font-bold transition">
                Save profile
            </button>
        </div>
    </form>
</div>
