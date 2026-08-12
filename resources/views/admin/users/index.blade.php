<div>
    @php
        $isDeliveryRiders = $roleType === 'delivery';
        $isKitchenUsers = $roleType === 'kitchen';
        $pageTitle = match ($roleType) {
            'delivery' => 'Delivery Riders',
            'kitchen' => 'Middo Kitchens',
            default => $roleType ? $roleType.' Users' : 'User Management',
        };
        $showAreas = $isDeliveryRiders;
        $showArea = $isKitchenUsers;
        $hideRole = $isDeliveryRiders || $isKitchenUsers;
    @endphp
    <div class="block w-full max-w-6xl mx-auto py-8 px-4 sm:px-6 overflow-hidden">

        <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
            <h1 class="text-3xl font-bold {{ $isKitchenUsers ? '' : 'capitalize' }}">
                {{ $pageTitle }}
            </h1>

            <div class="flex items-center gap-3 w-full sm:w-auto flex-wrap sm:flex-nowrap justify-end">
                <div class="relative w-full sm:w-64">
                    <input wire:model.live.debounce.300ms="search"
                        type="text"
                        placeholder="{{ $isDeliveryRiders ? 'Search riders...' : ($isKitchenUsers ? 'Search kitchens...' : 'Search users...') }}"
                        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition">
                    <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <button
                    type="button"
                    wire:click="exportExcel"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold transition disabled:opacity-60 whitespace-nowrap">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M7.5 12l4.5 4.5L16.5 12M12 3v13.5" />
                    </svg>
                    <span wire:loading.remove wire:target="exportExcel">Export Excel</span>
                    <span wire:loading wire:target="exportExcel">Exporting…</span>
                </button>
                <livewire:admin.user-create-modal :locked-role="$roleType" />
            </div>
        </div>

        <div class="w-full bg-white shadow-sm border border-gray-100 rounded-2xl overflow-hidden">
            <div class="overflow-x-auto w-full">
                @if (session()->has('message'))
                    <div class="bg-blue-500 text-white p-2 rounded mb-4">
                        {{ session('message') }}
                    </div>
                @endif
                <table class="w-full text-left border-collapse">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="p-4 font-semibold text-gray-600 whitespace-nowrap">Name</th>
                            <th class="p-4 font-semibold text-gray-600 whitespace-nowrap">Phone</th>
                            @if($showAreas)
                                <th class="p-4 font-semibold text-gray-600 whitespace-nowrap">Areas</th>
                            @elseif($showArea)
                                <th class="p-4 font-semibold text-gray-600 whitespace-nowrap">Area</th>
                            @elseif(! $hideRole)
                                <th class="p-4 font-semibold text-gray-600 whitespace-nowrap">Role</th>
                            @endif
                            <th class="p-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($users as $user)
                            <tr class="hover:bg-gray-50" wire:key="user-{{ $user->id }}">
                                <td class="p-4 font-medium whitespace-nowrap">
                                    <a href="{{ route('admin.users.show', $user) }}"
                                       class="text-middo-dark hover:text-middo-orange transition">
                                        {{ $user->first_name }} {{ $user->last_name }}
                                    </a>
                                    <div class="text-[11px] font-semibold text-middo-orange mt-0.5">
                                        <a href="{{ route('admin.users.show', $user) }}" class="hover:underline">View details →</a>
                                    </div>
                                </td>
                                <td class="p-4 text-gray-500 whitespace-nowrap">
                                    @if(filled($user->mobile))
                                        <button
                                            type="button"
                                            x-data="{ copied: false, phone: @js($user->mobile) }"
                                            x-on:click="
                                                const done = () => { copied = true; setTimeout(() => copied = false, 1500); };
                                                if (navigator.clipboard && window.isSecureContext) {
                                                    navigator.clipboard.writeText(phone).then(done).catch(() => {
                                                        const el = document.createElement('textarea');
                                                        el.value = phone;
                                                        document.body.appendChild(el);
                                                        el.select();
                                                        document.execCommand('copy');
                                                        document.body.removeChild(el);
                                                        done();
                                                    });
                                                } else {
                                                    const el = document.createElement('textarea');
                                                    el.value = phone;
                                                    document.body.appendChild(el);
                                                    el.select();
                                                    document.execCommand('copy');
                                                    document.body.removeChild(el);
                                                    done();
                                                }
                                            "
                                            class="inline-flex items-center font-mono text-sm text-middo-dark hover:text-middo-orange transition cursor-pointer"
                                            title="Copy phone number"
                                        >
                                            <span x-text="copied ? 'copied' : phone"
                                                  :class="copied ? 'text-emerald-600 font-semibold not-italic' : ''"></span>
                                        </button>
                                    @else
                                        <span class="text-gray-400">N/A</span>
                                    @endif
                                </td>
                                @if($showAreas)
                                    <td class="p-4 text-sm text-gray-600">
                                        @php
                                            $areaNames = $user->areas->isNotEmpty()
                                                ? $user->areas->pluck('name')->all()
                                                : array_filter([$user->area?->name]);
                                        @endphp
                                        @if($areaNames !== [])
                                            <div class="flex flex-wrap gap-1.5 max-w-xs">
                                                @foreach($areaNames as $areaName)
                                                    <span class="inline-flex rounded-lg bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">{{ $areaName }}</span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                @elseif($showArea)
                                    <td class="p-4 text-sm text-gray-600 whitespace-nowrap">
                                        {{ $user->area_name ?: ($user->area?->name ?: '—') }}
                                    </td>
                                @elseif(! $hideRole)
                                    <td class="p-4 whitespace-nowrap">
                                        <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded-lg text-xs font-bold uppercase truncate block max-w-[150px]">
                                            {{ $user->role->name ?? 'No Role' }}
                                        </span>
                                    </td>
                                @endif
                                <td class="p-4 text-right whitespace-nowrap flex justify-end gap-3">
                                    <button wire:click="toggleStatus({{ $user->id }})"
                                            class="px-2 py-1 rounded text-xs font-bold uppercase
                                            {{ $user->status == 'active' ? 'bg-green-100 text-green-700' :
                                            ($user->status == 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700') }}">
                                        {{ $user->status }}
                                    </button>

                                    <button wire:click="resetUserPassword({{ $user->id }})"
                                            wire:confirm="Reset password for {{ $user->first_name }} to '12345678'?"
                                            class="text-blue-600 hover:text-blue-900 text-sm">
                                        Reset Pass
                                    </button>

                                    <livewire:admin.user-edit-modal :user="$user" :key="'edit-'.$user->id" />

                                    <button wire:click="deleteUser({{ $user->id }})"
                                            wire:confirm="Are you sure?"
                                            class="text-red-600 hover:text-red-900 text-sm">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="p-8 text-center text-gray-500">
                                    @if($isDeliveryRiders)
                                        No riders found.
                                    @elseif($isKitchenUsers)
                                        No kitchens found.
                                    @else
                                        No users found.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t">
                {{ $users->links() }}
            </div>
        </div>
    </div>
</div>
