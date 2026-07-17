<div>
    @if($showModal)
        <div wire:key="profile-edit-modal-root" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" wire:click="closeModal"></div>

            <div class="relative bg-white w-full max-w-lg rounded-2xl shadow-2xl border border-[#EBE3D3] max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between p-5 border-b border-[#EBE3D3] sticky top-0 bg-white rounded-t-2xl">
                    <div>
                        <h2 class="text-xl font-black tracking-tight text-[#2B1A11]">Edit Profile</h2>
                        <p class="text-xs font-semibold text-[#635347] mt-0.5">Update your account and delivery details.</p>
                    </div>
                    <button type="button" wire:click="closeModal" class="p-1.5 rounded-lg text-gray-400 hover:text-[#2B1A11] hover:bg-[#F7F4EB] transition" aria-label="Close">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form wire:submit="save" class="p-5 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-1">First Name <span class="text-gray-400 normal-case font-medium">(your name)</span></label>
                            <input type="text" wire:model="first_name"
                                   class="w-full border border-gray-200 bg-white rounded-xl text-sm p-2.5 shadow-sm focus:ring-2 focus:ring-middo-orange focus:border-middo-orange outline-none">
                            @error('first_name') <span class="text-red-500 text-xs font-semibold mt-0.5 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-1">Last Name</label>
                            <input type="text" wire:model="last_name"
                                   class="w-full border border-gray-200 bg-white rounded-xl text-sm p-2.5 shadow-sm focus:ring-2 focus:ring-middo-orange focus:border-middo-orange outline-none">
                            @error('last_name') <span class="text-red-500 text-xs font-semibold mt-0.5 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-1">Phone Number</label>
                            <input type="text" wire:model="mobile" placeholder="01XXXXXXXXX"
                                   class="w-full border border-gray-200 bg-white rounded-xl text-sm p-2.5 shadow-sm focus:ring-2 focus:ring-middo-orange focus:border-middo-orange outline-none">
                            @error('mobile') <span class="text-red-500 text-xs font-semibold mt-0.5 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-1">Email <span class="text-gray-300 normal-case">(optional)</span></label>
                            <input type="email" wire:model="email" placeholder="you@example.com"
                                   class="w-full border border-gray-200 bg-white rounded-xl text-sm p-2.5 shadow-sm focus:ring-2 focus:ring-middo-orange focus:border-middo-orange outline-none">
                            @error('email') <span class="text-red-500 text-xs font-semibold mt-0.5 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-1">Company Name <span class="text-gray-300 normal-case">(optional)</span></label>
                        <input type="text" wire:model="company_name" placeholder="Your company or organisation"
                               class="w-full border border-gray-200 bg-white rounded-xl text-sm p-2.5 shadow-sm focus:ring-2 focus:ring-middo-orange focus:border-middo-orange outline-none">
                        @error('company_name') <span class="text-red-500 text-xs font-semibold mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-1">Address</label>
                        <textarea wire:model="address" rows="2" placeholder="Office / building, street, area"
                                  class="w-full border border-gray-200 bg-white rounded-xl text-sm p-2.5 shadow-sm focus:ring-2 focus:ring-middo-orange focus:border-middo-orange outline-none"></textarea>
                        @error('address') <span class="text-red-500 text-xs font-semibold mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-1">City</label>
                            <select wire:model.live="city_id"
                                    class="w-full border border-gray-200 bg-white rounded-xl text-sm p-2.5 shadow-sm focus:ring-2 focus:ring-middo-orange focus:border-middo-orange outline-none">
                                <option value="">Select City</option>
                                @foreach($cities as $city)
                                    <option value="{{ (string) $city->id }}">{{ $city->name }}</option>
                                @endforeach
                            </select>
                            @error('city_id') <span class="text-red-500 text-xs font-semibold mt-0.5 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-1">Area</label>
                            <select wire:model="area_id" @disabled(! $city_id)
                                    class="w-full border border-gray-200 bg-white rounded-xl text-sm p-2.5 shadow-sm focus:ring-2 focus:ring-middo-orange focus:border-middo-orange outline-none disabled:bg-gray-50 disabled:text-gray-400">
                                <option value="">Select Area</option>
                                @foreach($areas as $area)
                                    <option value="{{ (string) $area->id }}" wire:key="profile-area-{{ $area->id }}">{{ $area->name }}</option>
                                @endforeach
                            </select>
                            @error('area_id') <span class="text-red-500 text-xs font-semibold mt-0.5 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-dashed border-gray-100">
                        <button type="button" wire:click="closeModal"
                                class="text-xs font-black text-[#635347] hover:text-[#2B1A11] px-4 py-2.5 rounded-xl transition">
                            Cancel
                        </button>
                        <button type="submit"
                                class="bg-middo-orange hover:bg-[#733614] text-white font-black text-xs uppercase tracking-wider px-6 py-2.5 rounded-xl shadow-sm transition-colors">
                            <span wire:loading.remove wire:target="save">Save Changes</span>
                            <span wire:loading wire:target="save">Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
