<div class="max-w-7xl mx-auto py-10 px-6 space-y-6">
    <div class="space-y-1">
        <h1 class="text-3xl font-bold text-middo-dark">Payout bank branches</h1>
        <p class="text-sm font-semibold text-gray-500">
            Bangladesh bank / city / branch catalog used on kitchen and delivery payout methods. Seeded from the BD bank list; edit or add branches here.
        </p>
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ $statusMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errorMessage }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm space-y-3">
            <h2 class="text-sm font-bold uppercase tracking-wider text-gray-400">Add bank</h2>
            <div class="flex flex-wrap gap-2">
                <input type="text" wire:model="newBankName" maxlength="120" placeholder="Bank name"
                       class="flex-1 min-w-[140px] rounded-xl border border-gray-200 px-3 py-2 text-sm">
                <button type="button" wire:click="createBank"
                        class="px-4 py-2 rounded-xl bg-middo-orange text-white text-sm font-bold">Add</button>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm space-y-3">
            <h2 class="text-sm font-bold uppercase tracking-wider text-gray-400">Add city</h2>
            <div class="flex flex-wrap gap-2">
                <input type="text" wire:model="newCityName" maxlength="120" placeholder="City / district"
                       class="flex-1 min-w-[140px] rounded-xl border border-gray-200 px-3 py-2 text-sm" @disabled(! $filterBankId)>
                <button type="button" wire:click="createCity"
                        class="px-4 py-2 rounded-xl bg-middo-orange text-white text-sm font-bold" @disabled(! $filterBankId)>Add</button>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm space-y-3">
            <h2 class="text-sm font-bold uppercase tracking-wider text-gray-400">Add branch</h2>
            <div class="grid grid-cols-1 gap-2">
                <select wire:model="newBranchCityId" class="rounded-xl border border-gray-200 px-3 py-2 text-sm" @disabled(! $filterBankId)>
                    <option value="">City…</option>
                    @foreach($allCitiesForBank as $city)
                        <option value="{{ $city->id }}">{{ $city->name }}</option>
                    @endforeach
                </select>
                <div class="flex flex-wrap gap-2">
                    <input type="text" wire:model="newBranchName" maxlength="120" placeholder="Branch name"
                           class="flex-1 min-w-[140px] rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    <button type="button" wire:click="createBranch"
                            class="px-4 py-2 rounded-xl bg-middo-orange text-white text-sm font-bold">Add</button>
                </div>
            </div>
        </div>
    </div>

    <div class="flex flex-col sm:flex-row gap-3 sm:items-end">
        <div class="flex-1">
            <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Bank</label>
            <select wire:model.live="filterBankId" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                <option value="">Select bank…</option>
                @foreach($banks as $bank)
                    <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1">
            <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Search city / branch</label>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Filter…"
                   class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
        </div>
    </div>

    @if(! $filterBankId)
        <p class="text-sm text-gray-500">Select a bank to manage its cities and branches. Run <code class="text-xs bg-gray-100 px-1 rounded">php artisan db:seed --class=BdBankSeeder</code> if the list is empty.</p>
    @else
        <div class="space-y-4">
            @forelse($cities as $city)
                <div wire:key="bd-city-{{ $city->id }}" class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
                    <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-middo-dark">{{ $city->name }}</h2>
                            <p class="text-xs text-gray-400 font-semibold">{{ $city->branches->count() }} branch(es)</p>
                        </div>
                    </div>
                    <div class="divide-y divide-gray-50">
                        @forelse($city->branches as $branch)
                            <div wire:key="bd-branch-{{ $branch->id }}" class="px-5 py-3 flex flex-wrap items-center justify-between gap-3">
                                @if($editingBranchId === $branch->id)
                                    <div class="flex flex-wrap items-center gap-2 flex-1">
                                        <input type="text" wire:model="editingBranchName"
                                               class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold min-w-[200px]">
                                        <button type="button" wire:click="saveBranch" class="px-3 py-1.5 rounded-xl bg-middo-orange text-white text-xs font-bold">Save</button>
                                        <button type="button" wire:click="cancelEditBranch" class="px-3 py-1.5 rounded-xl border border-gray-200 text-xs font-bold">Cancel</button>
                                    </div>
                                @else
                                    <div class="min-w-0">
                                        <p @class(['text-sm font-semibold', 'text-middo-dark' => $branch->is_active, 'text-gray-400 line-through' => ! $branch->is_active])>
                                            {{ $branch->name }}
                                        </p>
                                        @unless($branch->is_active)
                                            <p class="text-[11px] font-bold uppercase text-gray-400">Inactive</p>
                                        @endunless
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" wire:click="startEditBranch({{ $branch->id }})"
                                                class="px-3 py-1.5 rounded-xl border border-gray-200 text-xs font-bold text-middo-dark hover:border-middo-orange">
                                            Rename
                                        </button>
                                        <button type="button" wire:click="toggleBranchActive({{ $branch->id }})"
                                                class="px-3 py-1.5 rounded-xl border border-gray-200 text-xs font-bold text-middo-dark">
                                            {{ $branch->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                        <button type="button" wire:click="deleteBranch({{ $branch->id }})"
                                                wire:confirm="Delete branch {{ $branch->name }}?"
                                                class="px-3 py-1.5 rounded-xl border border-red-200 text-xs font-bold text-red-600 hover:bg-red-50">
                                            Delete
                                        </button>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="px-5 py-4 text-sm text-gray-400 italic">No branches in this city yet.</p>
                        @endforelse
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500">No cities for this bank{{ $search ? ' matching your search' : '' }}.</p>
            @endforelse
        </div>
    @endif
</div>
