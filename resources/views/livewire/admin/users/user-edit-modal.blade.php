<div>
    <button wire:click="$set('showModal', true)" 
            class="text-blue-600 hover:text-blue-900 font-bold text-sm transition">
        Edit
    </button>

    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-0 sm:p-4">
            <div class="bg-white w-full h-full sm:h-auto sm:max-h-[90vh] sm:rounded-2xl shadow-2xl border border-gray-100 flex flex-col min-w-0 max-w-lg">
                
                <div class="px-6 py-4 border-b border-gray-100 shrink-0">
                    <h2 class="text-lg font-extrabold text-gray-900">Edit User: {{ $user->first_name }}</h2>
                </div>

                <div class="p-6 overflow-y-auto flex-1 space-y-6">
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">First Name</label>
                                <input wire:model="first_name" class="w-full border-gray-300 border rounded-xl p-3 text-sm" placeholder="First Name">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Last Name</label>
                                <input wire:model="last_name" class="w-full border-gray-300 border rounded-xl p-3 text-sm" placeholder="Last Name">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Mobile</label>
                            <input wire:model="mobile" class="w-full border-gray-300 border rounded-xl p-3 text-sm" placeholder="01710123456">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Role</label>
                            <select wire:model="role_id" class="w-full border-gray-300 border rounded-xl p-3 text-sm">
                                @foreach($roles as $role) 
                                    <option value="{{ (string)$role->id }}">{{ $role->name }}</option> 
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div x-data="{ expanded: false }" class="border-t border-gray-100 pt-4">
                        <button @click="expanded = !expanded" type="button" class="flex items-center text-xs font-bold text-blue-600 uppercase tracking-widest">
                            <span x-text="expanded ? 'Hide Details' : 'Show Optional Fields'"></span>
                            <svg class="w-4 h-4 ml-2 transition" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"></path></svg>
                        </button>

                        <div x-show="expanded" x-cloak class="mt-4 space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Email</label>
                                <input wire:model="email" class="w-full border-gray-300 border rounded-xl p-3 text-sm" placeholder="Email Address">
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">City</label>
                                    <select wire:model.live="city_id" class="w-full border-gray-300 border rounded-xl p-3 text-sm">
                                        <option value="">Select City</option>
                                        @foreach($cities as $city) 
                                            <option value="{{ (string)$city->id }}">{{ $city->name }}</option> 
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Area</label>
                                    <select wire:model="area_id" class="w-full border-gray-300 border rounded-xl p-3 text-sm" @if(!$city_id) disabled @endif>
                                        <option value="">Select Area</option>
                                        @foreach($areas as $area) 
                                            <option value="{{ (string)$area->id }}" wire:key="area-{{ $area->id }}">{{ $area->name }}</option> 
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Full Address</label>
                                <textarea wire:model="address" class="w-full border-gray-300 border rounded-xl p-3 text-sm" placeholder="Detailed Address"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 sm:rounded-b-2xl flex justify-end gap-3 shrink-0">
                    <button wire:click="$set('showModal', false)" class="text-gray-600 text-sm font-medium px-4 py-2">Cancel</button>
                    <button wire:click="save" class="bg-blue-600 text-white text-sm font-bold px-6 py-2 rounded-xl hover:bg-blue-700 transition">Save Changes</button>
                </div>
            </div>
        </div>
    @endif
</div>