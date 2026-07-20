<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="space-y-1">
            <h1 class="text-3xl font-bold text-middo-dark">Corporates</h1>
            <p class="text-sm font-semibold text-gray-500">
                Search companies and open a corporate profile for details, balance, and order history.
            </p>
        </div>

        <div class="flex items-center gap-3 w-full sm:w-auto">
            <div class="relative w-full sm:w-72">
                <input
                    wire:model.live.debounce.300ms="search"
                    type="text"
                    placeholder="Search company, name, mobile…"
                    class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-middo-orange focus:border-middo-orange outline-none transition text-sm">
                <svg class="absolute left-3 top-3 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
            @if($canManage)
                <livewire:admin.user-create-modal locked-role="corporate" />
            @endif
        </div>
    </div>

    @if (session()->has('message'))
        <div class="rounded-xl bg-sky-50 border border-sky-200 text-sky-800 px-4 py-3 text-sm font-semibold">
            {{ session('message') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm font-semibold">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white shadow-sm border border-gray-100 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[880px]">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr class="text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <th class="p-4">Company</th>
                        <th class="p-4">Contact</th>
                        <th class="p-4">Mobile</th>
                        <th class="p-4">Area</th>
                        <th class="p-4 text-right">Balance</th>
                        <th class="p-4">Status</th>
                        @if($canManage)
                            <th class="p-4 text-right">Manage</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($corporates as $corporate)
                        <tr class="hover:bg-gray-50/80 transition" wire:key="corporate-{{ $corporate->id }}">
                            <td class="p-4">
                                <a href="{{ $this->showRoute($corporate) }}" class="group inline-flex flex-col min-w-0">
                                    <span class="font-bold text-middo-dark group-hover:text-middo-orange transition truncate">
                                        {{ $corporate->company_name ?: '—' }}
                                    </span>
                                    <span class="text-[11px] font-semibold text-middo-orange group-hover:underline mt-0.5">
                                        View profile →
                                    </span>
                                </a>
                            </td>
                            <td class="p-4">
                                <a href="{{ $this->showRoute($corporate) }}" class="font-medium text-gray-800 hover:text-middo-orange transition">
                                    {{ $corporate->first_name }} {{ $corporate->last_name }}
                                </a>
                                @if($corporate->email)
                                    <div class="text-xs text-gray-400 mt-0.5 truncate max-w-[180px]">{{ $corporate->email }}</div>
                                @endif
                            </td>
                            <td class="p-4 font-mono text-gray-700 whitespace-nowrap">{{ $corporate->mobile }}</td>
                            <td class="p-4 text-gray-600 whitespace-nowrap">
                                {{ $corporate->area_name ?: '—' }}@if($corporate->city_name), {{ $corporate->city_name }}@endif
                            </td>
                            <td class="p-4 text-right font-mono font-bold text-middo-dark whitespace-nowrap">
                                ৳{{ number_format((int) $corporate->balance) }}
                            </td>
                            <td class="p-4">
                                @if($canManage)
                                    <button
                                        type="button"
                                        wire:click="toggleStatus({{ $corporate->id }})"
                                        class="px-2 py-1 rounded text-xs font-bold uppercase
                                            {{ $corporate->status === 'active' ? 'bg-green-100 text-green-700' :
                                            ($corporate->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700') }}">
                                        {{ $corporate->status }}
                                    </button>
                                @else
                                    <span class="px-2 py-1 rounded text-xs font-bold uppercase
                                        {{ $corporate->status === 'active' ? 'bg-green-100 text-green-700' :
                                        ($corporate->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700') }}">
                                        {{ $corporate->status }}
                                    </span>
                                @endif
                            </td>
                            @if($canManage)
                                <td class="p-4 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center justify-end gap-3">
                                        <button
                                            type="button"
                                            wire:click="resetUserPassword({{ $corporate->id }})"
                                            wire:confirm="Reset password for {{ $corporate->first_name }} to '12345678'?"
                                            class="text-sky-600 hover:text-sky-900 text-sm font-semibold">
                                            Reset Pass
                                        </button>
                                        <livewire:admin.user-edit-modal :user="$corporate" :key="'edit-'.$corporate->id" />
                                        <button
                                            type="button"
                                            wire:click="deleteUser({{ $corporate->id }})"
                                            wire:confirm="Are you sure?"
                                            class="text-red-600 hover:text-red-900 text-sm font-semibold">
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canManage ? 7 : 6 }}" class="p-12 text-center text-sm font-semibold text-gray-400 italic">
                                No corporates found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-100">
            {{ $corporates->links() }}
        </div>
    </div>
</div>
