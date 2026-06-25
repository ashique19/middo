<div>
    <button wire:click="$set('showModal', true)" 
            class="bg-blue-600 hover:bg-blue-700 text-sm px-4 py-2 rounded-xl text-white font-bold transition active:scale-95 shadow-lg shadow-blue-200">
        Add New User
    </button>

    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-0 sm:p-4">
            <div class="bg-white w-full h-full sm:h-auto sm:max-h-[90vh] sm:rounded-2xl shadow-2xl border border-gray-100 flex flex-col min-w-0 max-w-lg">
                
                <div class="px-6 py-4 border-b border-gray-100 shrink-0">
                    <h2 class="text-lg font-extrabold text-gray-900">Create New User</h2>
                </div>

                <div class="p-6 overflow-y-auto flex-1 space-y-6" x-data="{ mobileError: '' }">
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <input wire:model="first_name" class="w-full border-gray-300 border rounded-xl p-3 text-sm" placeholder="First Name">
                            <input wire:model="last_name" class="w-full border-gray-300 border rounded-xl p-3 text-sm" placeholder="Last Name">
                        </div>
                        
                        <div>
                            <input wire:model.live.debounce.500ms="mobile" 
                                x-on:input="mobileError = /^01[3-9]\d{8}$/.test($el.value) ? '' : 'Invalid format (e.g. 01710123456)'"
                                class="w-full border rounded-xl p-3 text-sm {{ $mobileExists || ($errors->has('mobile')) ? 'border-red-500' : 'border-gray-300' }}" 
                                placeholder="Mobile: 01710123456">
                            
                            <p x-show="mobileError" x-text="mobileError" class="text-red-500 text-xs mt-1"></p>
                            
                            @if($mobileExists) 
                                <p class="text-red-500 text-xs mt-1">This number is already registered.</p> 
                            @endif
                        </div>
                        
                        <input wire:model="password" type="password" class="w-full border-gray-300 border rounded-xl p-3 text-sm" placeholder="Password">
                        
                        <select wire:model="role_id" class="w-full border-gray-300 border rounded-xl p-3 text-sm">
                            @foreach($roles as $role) <option value="{{ $role->id }}">{{ $role->name }}</option> @endforeach
                        </select>
                    </div>

                    <div x-data="{ expanded: false }" class="border-t border-gray-100 pt-4">
                        <button @click="expanded = !expanded" type="button" class="flex items-center text-xs font-bold text-blue-600 uppercase tracking-widest">
                            <span x-text="expanded ? 'Hide Details' : 'Show Optional Fields'"></span>
                            <svg class="w-4 h-4 ml-2 transition" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"></path></svg>
                        </button>

                        <div x-show="expanded" x-cloak class="mt-4 space-y-4">
                            <input wire:model="email" class="w-full border-gray-300 border rounded-xl p-3 text-sm" placeholder="Email (Optional)">
                            <div class="grid grid-cols-2 gap-4">
                                <select wire:model.live="selectedCity" class="w-full border-gray-300 border rounded-xl p-3 text-sm">
                                    <option value="">Select City</option>
                                    @foreach($cities as $city) 
                                        <option value="{{ $city->id }}">{{ $city->name }}</option> 
                                    @endforeach
                                </select>

                                <select wire:model="area_id" class="w-full border-gray-300 border rounded-xl p-3 text-sm" @if(!$selectedCity) disabled @endif>
                                    <option value="">Select Area</option>
                                    @foreach($areas as $area) 
                                        <option value="{{ $area->id }}" wire:key="area-{{ $area->id }}">{{ $area->name }}</option> 
                                    @endforeach
                                </select>
                            </div>
                            <textarea wire:model="address" class="w-full border-gray-300 border rounded-xl p-3 text-sm" placeholder="Full Address"></textarea>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 sm:rounded-b-2xl flex justify-end gap-3 shrink-0">
                    <button wire:click="$set('showModal', false)" class="text-gray-600 text-sm font-medium px-4 py-2">Cancel</button>
                    <button wire:click="save" class="bg-blue-600 text-white text-sm font-bold px-6 py-2 rounded-xl hover:bg-blue-700 transition">
                        Create User
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>