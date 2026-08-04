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
                        
                        @if($lockedRole)
                            <div class="w-full border border-gray-200 bg-gray-50 rounded-xl p-3 text-sm font-semibold text-gray-700 capitalize">
                                Role: {{ $lockedRole }}
                            </div>
                        @else
                            <select wire:model.live="role_id" class="w-full border-gray-300 border rounded-xl p-3 text-sm">
                                @foreach($roles as $role) <option value="{{ $role->id }}">{{ $role->name }}</option> @endforeach
                            </select>
                        @endif
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

                                @if($this->isDeliveryForm)
                                    <div class="col-span-2 sm:col-span-1">
                                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Service areas</label>
                                        <select wire:model="selectedAreaIds" multiple
                                                class="w-full border-gray-300 border rounded-xl p-3 text-sm min-h-[6rem]"
                                                @if(!$selectedCity) disabled @endif>
                                            @foreach($areas as $area)
                                                <option value="{{ $area->id }}" wire:key="area-{{ $area->id }}">{{ $area->name }}</option>
                                            @endforeach
                                        </select>
                                        <p class="text-[11px] text-gray-400 mt-1">Hold Ctrl/Cmd to select multiple areas.</p>
                                        @error('selectedAreaIds') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                    </div>
                                @else
                                    <select wire:model="area_id" class="w-full border-gray-300 border rounded-xl p-3 text-sm" @if(!$selectedCity) disabled @endif>
                                        <option value="">Select Area</option>
                                        @foreach($areas as $area)
                                            <option value="{{ $area->id }}" wire:key="area-{{ $area->id }}">{{ $area->name }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </div>
                            <textarea wire:model="address" class="w-full border-gray-300 border rounded-xl p-3 text-sm" placeholder="Full Address"></textarea>

                            @if($this->isDeliveryForm)
                                <div class="border-t border-gray-100 pt-4 space-y-3">
                                    <div>
                                        <p class="text-xs font-bold text-gray-500 uppercase tracking-widest">Commission overrides (optional)</p>
                                        <p class="text-[11px] text-gray-400 mt-1">Leave blank to use menu (lunch) or Settings defaults. Absolute ৳ per order/run.</p>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        @foreach($runTypes as $type)
                                            <div>
                                                <label class="block text-[10px] font-semibold text-gray-500 uppercase mb-1">{{ \App\Support\DeliveryRunType::label($type) }}</label>
                                                <input type="number" min="0" wire:model="commissionOverrides.{{ $type }}"
                                                       class="w-full border-gray-300 border rounded-xl p-2.5 text-sm" placeholder="Default">
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
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