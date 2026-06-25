<div>
    <button wire:click="$set('showModal', true)" 
            class="text-blue-600 hover:text-blue-800 font-medium text-sm transition">
        Edit
    </button>

    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm">
            <div class="bg-white rounded-2xl p-8 w-full max-w-md shadow-2xl border border-gray-100">
                <h2 class="text-xl font-extrabold text-gray-900 mb-6">Edit: {{ $nav->title }}</h2>
                
                <div class="space-y-5">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Title</label>
                        <input wire:model="title" class="w-full border-gray-300 border rounded-xl p-3 focus:ring-2 focus:ring-blue-500 outline-none transition" placeholder="e.g. Dashboard">
                    </div>

                    <div x-data="{ 
                        open: false, 
                        search: @entangle('route_name'),
                        routes: {{ json_encode($availableRoutes) }},
                        get filteredRoutes() { return this.routes.filter(i => i.toLowerCase().includes(this.search.toLowerCase())); }
                    }">
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Search Route</label>
                        <div class="relative">
                            <input type="text" x-model="search" @focus="open = true" @click.away="open = false"
                                   class="w-full border-gray-300 border rounded-xl p-3 focus:ring-2 focus:ring-blue-500 outline-none transition" 
                                   placeholder="Type to search routes...">
                            
                            <div x-show="open" class="absolute z-10 w-full bg-white border border-gray-200 mt-2 max-h-48 overflow-y-auto shadow-xl rounded-xl">
                                <template x-for="route in filteredRoutes" :key="route">
                                    <div @click="search = route; open = false; $wire.set('route_name', route)" 
                                         class="p-3 cursor-pointer hover:bg-blue-50 text-sm transition" x-text="route"></div>
                                </template>
                                <div x-show="filteredRoutes.length === 0" class="p-3 text-sm text-gray-400">No routes found</div>
                            </div>
                        </div>
                    </div>
                    
                    <label class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl cursor-pointer">
                        <input type="checkbox" wire:model.live="isChild" class="h-5 w-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm font-semibold text-gray-700">Is Child Navigation?</span>
                    </label>

                    @if($isChild)
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Parent Item</label>
                            <select wire:model="parent_id" class="w-full border-gray-300 border rounded-xl p-3 focus:ring-2 focus:ring-blue-500 outline-none">
                                <option value="">Select Parent</option>
                                @foreach($parents as $p)
                                    <option value="{{ $p->id }}">{{ $p->title }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Assign Role</label>
                        <select wire:model="role_id" class="w-full border-gray-300 border rounded-xl p-3 focus:ring-2 focus:ring-blue-500 outline-none">
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}">{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex justify-between items-center mt-8 pt-6 border-t border-gray-100">
                    <button wire:click="delete" 
                            wire:confirm="Are you sure you want to delete this navigation item?"
                            class="text-red-500 hover:text-red-700 font-bold text-sm px-4 py-2 hover:bg-red-50 rounded-lg transition">
                        Delete
                    </button>

                    <div class="flex gap-3">
                        <button wire:click="$set('showModal', false)" class="text-gray-600 hover:text-gray-900 font-medium px-4 py-2 transition">Cancel</button>
                        <button wire:click="save" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-2 rounded-xl shadow-lg shadow-blue-200 transition active:scale-95">
                            Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>