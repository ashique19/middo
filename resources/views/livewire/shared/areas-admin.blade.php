<div class="max-w-7xl mx-auto py-10 px-6 space-y-6">
    <div class="space-y-1">
        <h1 class="text-3xl font-bold text-middo-dark">Areas & cities</h1>
        <p class="text-sm font-semibold text-gray-500">
            Manage delivery geography. Rider multi-area coverage is edited on each rider profile.
        </p>
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ $statusMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errorMessage }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm space-y-3">
            <h2 class="text-sm font-bold uppercase tracking-wider text-gray-400">Add city</h2>
            <div class="flex flex-wrap gap-2">
                <input type="text" wire:model="newCityName" maxlength="120" placeholder="City name"
                       class="flex-1 min-w-[160px] rounded-xl border border-gray-200 px-3 py-2 text-sm" />
                <button type="button" wire:click="createCity"
                        class="inline-flex px-4 py-2 rounded-xl bg-middo-orange text-white text-sm font-bold">
                    Add city
                </button>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm space-y-3">
            <h2 class="text-sm font-bold uppercase tracking-wider text-gray-400">Add area</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                <select wire:model="newAreaCityId" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    <option value="">City…</option>
                    @foreach($cities as $city)
                        <option value="{{ $city->id }}">{{ $city->name }}</option>
                    @endforeach
                </select>
                <input type="text" wire:model="newAreaName" maxlength="120" placeholder="Area name"
                       class="sm:col-span-1 rounded-xl border border-gray-200 px-3 py-2 text-sm" />
                <button type="button" wire:click="createArea"
                        class="inline-flex px-4 py-2 rounded-xl bg-middo-orange text-white text-sm font-bold justify-center">
                    Add area
                </button>
            </div>
        </div>
    </div>

    <div class="space-y-4">
        @forelse($cities as $city)
            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
                    @if($editingCityId === $city->id)
                        <div class="flex flex-wrap items-center gap-2 flex-1">
                            <input type="text" wire:model="editingCityName"
                                   class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold min-w-[180px]" />
                            <button type="button" wire:click="saveCity" class="px-3 py-1.5 rounded-xl bg-middo-orange text-white text-xs font-bold">Save</button>
                            <button type="button" wire:click="cancelEditCity" class="px-3 py-1.5 rounded-xl border border-gray-200 text-xs font-bold">Cancel</button>
                        </div>
                    @else
                        <div>
                            <h2 class="text-lg font-bold text-middo-dark">{{ $city->name }}</h2>
                            <p class="text-xs text-gray-400 font-semibold">{{ $city->areas->count() }} area(s)</p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" wire:click="startEditCity({{ $city->id }})"
                                    class="px-3 py-1.5 rounded-xl border border-gray-200 text-xs font-bold text-middo-dark hover:border-middo-orange">
                                Rename
                            </button>
                            <button type="button" wire:click="deleteCity({{ $city->id }})"
                                    wire:confirm="Delete city {{ $city->name }}? Only empty cities can be removed."
                                    class="px-3 py-1.5 rounded-xl border border-red-200 text-xs font-bold text-red-600 hover:bg-red-50">
                                Delete
                            </button>
                        </div>
                    @endif
                </div>

                <div class="divide-y divide-gray-50">
                    @forelse($city->areas as $area)
                        <div class="px-5 py-3 flex flex-wrap items-center justify-between gap-3">
                            @if($editingAreaId === $area->id)
                                <div class="flex flex-wrap items-center gap-2 flex-1">
                                    <select wire:model="editingAreaCityId" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
                                        @foreach($cities as $c)
                                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" wire:model="editingAreaName"
                                           class="rounded-xl border border-gray-200 px-3 py-2 text-sm min-w-[160px]" />
                                    <button type="button" wire:click="saveArea" class="px-3 py-1.5 rounded-xl bg-middo-orange text-white text-xs font-bold">Save</button>
                                    <button type="button" wire:click="cancelEditArea" class="px-3 py-1.5 rounded-xl border border-gray-200 text-xs font-bold">Cancel</button>
                                </div>
                            @else
                                <div class="text-sm font-semibold text-gray-800">{{ $area->name }}</div>
                                <div class="flex gap-2">
                                    <button type="button" wire:click="startEditArea({{ $area->id }})"
                                            class="px-3 py-1.5 rounded-xl border border-gray-200 text-xs font-bold text-middo-dark hover:border-middo-orange">
                                        Edit
                                    </button>
                                    <button type="button" wire:click="deleteArea({{ $area->id }})"
                                            wire:confirm="Delete area {{ $area->name }}?"
                                            class="px-3 py-1.5 rounded-xl border border-red-200 text-xs font-bold text-red-600 hover:bg-red-50">
                                        Delete
                                    </button>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="px-5 py-8 text-sm text-gray-400 italic text-center">No areas in this city yet.</div>
                    @endforelse
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-gray-200 bg-white px-5 py-12 text-center text-sm text-gray-400 italic">
                No cities yet — add one above.
            </div>
        @endforelse
    </div>
</div>
