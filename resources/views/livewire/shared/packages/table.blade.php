<div class="block w-full max-w-6xl mx-auto py-8 px-4 sm:px-6">
    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Packages{{ $canManage ? ' Management' : '' }}</h1>
            <p class="text-sm text-gray-500 mt-1">
                {{ $canCreate ? 'Create 30-day meal packages with a menu for each date.' : 'View meal packages and assigned daily menus.' }}
                @if($canCreate && ! $canPublish) Operation can save drafts; only admins publish. @endif
            </p>
        </div>
        <div class="flex items-center gap-3 self-end md:self-auto">
            <div class="relative w-64">
                <input
                    wire:model.live.debounce.300ms="search"
                    type="text"
                    placeholder="Search packages..."
                    class="w-full pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-xl shadow-sm focus:ring-middo-orange focus:border-middo-orange transition"
                >
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.603 10.601z" />
                    </svg>
                </div>
            </div>
            @if($canCreate)
                <a href="{{ $canPublish ? route('admin.packages.create') : route('operation.packages.create') }}"
                   class="inline-flex items-center gap-2 bg-middo-orange hover:bg-[#733614] text-white font-bold text-sm px-4 py-2.5 rounded-xl shadow-sm transition">
                    + New Package
                </a>
            @endif
        </div>
    </div>

    @if (session('package_error'))
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 text-red-800 text-sm px-4 py-3">
            {{ session('package_error') }}
        </div>
    @endif

    <div class="bg-white shadow-md border border-gray-100 rounded-2xl overflow-hidden mb-4">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[900px]">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <th class="p-4 w-20">Image</th>
                        <th class="p-4">Package</th>
                        <th class="p-4">৳/day</th>
                        <th class="p-4">Window</th>
                        <th class="p-4 text-center">Days filled</th>
                        <th class="p-4 text-center">Status</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($packages as $package)
                        <tr wire:key="package-row-{{ $package->id }}" class="hover:bg-gray-50/70 transition">
                            <td class="p-4">
                                @if($package->thumbnail)
                                    <img src="{{ asset($package->thumbnail) }}" alt="{{ $package->name }}" class="w-12 h-12 rounded-xl object-cover border border-gray-100">
                                @else
                                    <div class="w-12 h-12 rounded-xl bg-gray-50 border border-dashed border-gray-200 flex items-center justify-center text-[10px] text-gray-400">No Img</div>
                                @endif
                            </td>
                            <td class="p-4">
                                <div class="font-semibold text-gray-800">{{ $package->name }}</div>
                                <div class="text-xs text-gray-400 capitalize">{{ $package->diet_tag }} · {{ $package->duration_days }} days</div>
                            </td>
                            <td class="p-4 font-medium">৳{{ number_format($package->price_per_day) }}</td>
                            <td class="p-4 text-gray-700 text-xs">
                                {{ $package->start_date->format('M d') }} – {{ $package->end_date->format('M d, Y') }}
                            </td>
                            <td class="p-4 text-center font-bold text-middo-orange">
                                {{ $package->days_count }} / {{ $package->expectedDaysCount() }}
                            </td>
                            <td class="p-4 text-center">
                                <span @class([
                                    'px-2.5 py-1 rounded-full text-[11px] font-bold uppercase',
                                    'bg-emerald-100 text-emerald-800 border border-emerald-200' => $package->status === 'published',
                                    'bg-amber-50 text-amber-800 border border-amber-200' => $package->status === 'draft',
                                    'bg-gray-100 text-gray-500 border border-gray-200' => $package->status === 'archived',
                                ])>
                                    {{ $package->status }}
                                </span>
                            </td>
                            <td class="p-4 text-right">
                                <div class="inline-flex items-center gap-2 flex-wrap justify-end">
                                    <a href="{{ $canPublish ? route('admin.packages.edit', $package) : route('operation.packages.edit', $package) }}"
                                       class="px-3 py-1.5 rounded-lg text-xs font-bold border border-gray-200 hover:bg-gray-50">
                                        {{ ($canPublish || ($canCreate && $package->status === 'draft')) ? 'Edit' : 'View' }}
                                    </a>
                                    @if($canCreate)
                                        <button type="button" wire:click="clonePackage({{ $package->id }})"
                                            class="px-3 py-1.5 rounded-lg text-xs font-bold border border-sky-200 text-sky-800 hover:bg-sky-50">
                                            Clone
                                        </button>
                                    @endif
                                    @if($canPublish)
                                        <button type="button" wire:click="togglePublish({{ $package->id }})"
                                            class="px-3 py-1.5 rounded-lg text-xs font-bold border border-emerald-200 text-emerald-800 hover:bg-emerald-50">
                                            {{ $package->status === 'published' ? 'Unpublish' : 'Publish' }}
                                        </button>
                                        <button type="button" wire:click="deletePackage({{ $package->id }})"
                                            wire:confirm="Archive or delete this package?"
                                            class="px-3 py-1.5 rounded-lg text-xs font-bold border border-red-200 text-red-700 hover:bg-red-50">
                                            Delete
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-10 text-center text-gray-400">No packages yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>{{ $packages->links() }}</div>
</div>
