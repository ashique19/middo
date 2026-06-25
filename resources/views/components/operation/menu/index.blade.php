<div>
    <div class="block w-full max-w-6xl mx-auto py-8 px-4 sm:px-6">
        
        <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Menu Management</h1>
                <p class="text-sm text-gray-500 mt-1">Manage food listings, pricing variants, and kitchen parameters.</p>
            </div>
            <div class="flex items-center gap-3 self-end md:self-auto">
                <div class="relative w-64">
                    <input 
                        wire:model.live.debounce.300ms="search" 
                        type="text" 
                        placeholder="Search menu items..." 
                        class="w-full pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 transition"
                    >
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.603 10.601z" />
                        </svg>
                    </div>
                </div>
                <livewire:operation.menu-create-modal />
            </div>
        </div>

        <div class="bg-white shadow-md border border-gray-100 rounded-2xl overflow-hidden mb-4">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <th class="p-4 w-20">Image</th>
                        <th class="p-4">Item Details</th>
                        <th class="p-4">Price</th>
                        <th class="p-4">Kitchen Comm.</th>
                        <th class="p-4 text-center w-48">Status / Flags</th>
                        <th class="p-4 text-right w-40">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($items as $item)
                        <tr wire:key="menu-row-{{ $item->id }}" class="hover:bg-gray-50/70 transition">
                            {{-- Thumbnail --}}
                            <td class="p-4">
                                @if($item->thumbnail)
                                    <img src="{{ asset($item->thumbnail) }}" 
                                        alt="{{ $item->name }}" 
                                        class="w-12 h-12 rounded-xl object-cover shadow-sm border border-gray-100">
                                @else
                                    <div class="w-12 h-12 rounded-xl bg-gray-50 border border-dashed border-gray-200 flex items-center justify-center text-[10px] font-medium text-gray-400">
                                        No Img
                                    </div>
                                @endif
                            </td>

                            {{-- Name/Details --}}
                            <td class="p-4">
                                <div class="font-semibold text-gray-800">{{ $item->name }}</div>
                                @if($item->summary)
                                    <div class="text-xs text-gray-400 max-w-xs truncate">{{ $item->summary }}</div>
                                @endif
                            </td>

                            {{-- Price --}}
                            <td class="p-4 font-medium text-gray-900">৳{{ number_format($item->price, 2) }}</td>

                            {{-- Commission --}}
                            <td class="p-4 text-gray-600 font-medium">৳{{ number_format($item->kitchen_commission, 2) }}</td>

                            {{-- Badges / Flags --}}
                            <td class="p-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <button wire:click="toggleFlag({{ $item->id }}, 'is_featured')"
                                        class="px-2.5 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider transition {{ $item->is_featured ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-gray-50 text-gray-400 border border-gray-200 hover:bg-gray-100' }}">
                                        Featured
                                    </button>
                                    <button wire:click="toggleFlag({{ $item->id }}, 'is_homepage')"
                                        class="px-2.5 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider transition {{ $item->is_homepage ? 'bg-blue-100 text-blue-800 border border-blue-200' : 'bg-gray-50 text-gray-400 border border-gray-200 hover:bg-gray-100' }}">
                                        Home
                                    </button>
                                </div>
                            </td>

                            {{-- Actions --}}
                            <td class="p-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    {{-- Edit Button --}}
                                    <button 
                                        wire:click="$dispatch('editMenuItem', { id: {{ $item->id }} })" 
                                        type="button"
                                        class="inline-flex items-center gap-1.5 bg-gray-100 hover:bg-middo-orange hover:text-white text-gray-700 px-3 py-1.5 rounded-lg text-xs font-semibold transition border border-gray-200 shadow-sm"
                                    >
                                        Edit
                                    </button>

                                    {{-- Safe Native Confirmation Delete Button --}}
                                    <button 
                                        wire:click="deleteItem({{ $item->id }})" 
                                        wire:confirm="Are you sure you want to delete this item?"
                                        type="button"
                                        class="inline-flex items-center justify-center bg-gray-50 hover:bg-red-50 hover:text-red-600 text-gray-400 p-1.5 rounded-lg transition border border-gray-200 shadow-sm"
                                        title="Delete Item"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-12 text-center text-gray-400 font-medium">
                                No menu items found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 px-1">
            {{ $items->links() }}
        </div>
    </div>

    <livewire:operation.menu-edit-modal />
</div>