<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('marketing.dashboard') }}" class="text-sm font-semibold text-middo-orange hover:underline">← Dashboard</a>
            <h1 class="text-3xl font-bold text-middo-dark mt-1">Companies</h1>
            <p class="text-sm text-gray-500">Leads for field marketing — not login accounts.</p>
        </div>
        <button type="button" wire:click="openCreate"
                class="px-4 py-2 rounded-xl bg-middo-orange text-white text-sm font-bold">New company lead</button>
    </div>

    @if($statusMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ $statusMessage }}</div>
    @endif
    @if($errorMessage)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errorMessage }}</div>
    @endif

    @if($showCreate)
        <form wire:submit="createCompany" class="bg-white border border-gray-100 rounded-2xl shadow-sm p-5 space-y-4">
            <h2 class="text-lg font-bold text-middo-dark">New company lead</h2>
            <div>
                <label class="block text-xs font-bold uppercase text-gray-400 mb-1">Company name</label>
                <input type="text" wire:model="name" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-bold uppercase text-gray-400 mb-1">Office address</label>
                <textarea wire:model="address" rows="2" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"></textarea>
                @error('address') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-400 mb-1">City</label>
                    <select wire:model.live="cityId" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                        <option value="">Select…</option>
                        @foreach($cities as $city)
                            <option value="{{ $city->id }}">{{ $city->name }}</option>
                        @endforeach
                    </select>
                    @error('cityId') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-400 mb-1">Area</label>
                    <select wire:model="areaId" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" @disabled(! $cityId)>
                        <option value="">Select…</option>
                        @foreach($areas as $area)
                            <option value="{{ $area->id }}">{{ $area->name }}</option>
                        @endforeach
                    </select>
                    @error('areaId') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-400 mb-1">HR name</label>
                    <input type="text" wire:model="hrName" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-400 mb-1">HR mobile</label>
                    <input type="text" wire:model="hrMobile" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" placeholder="01XXXXXXXXX">
                    @error('hrMobile') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase text-gray-400 mb-1">Notes</label>
                <textarea wire:model="notes" rows="2" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"></textarea>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 rounded-xl bg-middo-orange text-white text-sm font-bold">Save lead</button>
                <button type="button" wire:click="$set('showCreate', false)" class="px-4 py-2 rounded-xl border border-gray-200 text-sm font-bold">Cancel</button>
            </div>
        </form>
    @endif

    <div class="flex flex-wrap gap-3">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name / HR…"
               class="rounded-xl border border-gray-200 px-3 py-2 text-sm flex-1 min-w-[180px]">
        <select wire:model.live="statusFilter" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
            <option value="">All statuses</option>
            @foreach(\App\Models\Company::statuses() as $status)
                <option value="{{ $status }}">{{ \App\Models\Company::statusLabel($status) }}</option>
            @endforeach
        </select>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <table class="w-full text-sm min-w-[640px]">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500 font-semibold">
                <tr>
                    <th class="p-3 text-left">Company</th>
                    <th class="p-3 text-left">Area</th>
                    <th class="p-3 text-left">Status</th>
                    <th class="p-3 text-left">HR</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($companies as $c)
                    <tr class="hover:bg-gray-50">
                        <td class="p-3">
                            <a href="{{ route('marketing.companies.show', $c) }}" class="font-bold text-middo-orange hover:underline">{{ $c->name }}</a>
                            <p class="text-xs text-gray-500 truncate max-w-xs">{{ $c->address }}</p>
                        </td>
                        <td class="p-3 text-gray-600">{{ $c->area?->name ?: '—' }}</td>
                        <td class="p-3">{{ \App\Models\Company::statusLabel($c->status) }}</td>
                        <td class="p-3 text-gray-600">
                            {{ $c->hr_name ?: '—' }}
                            @if($c->hr_mobile)<div class="text-xs font-mono">{{ $c->hr_mobile }}</div>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-10 text-center text-gray-400 italic">No company leads yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($companies->hasPages()) <div class="p-3">{{ $companies->links() }}</div> @endif
    </div>
</div>
