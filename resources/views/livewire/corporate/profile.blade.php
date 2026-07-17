<div class="min-h-screen bg-[#F7F4EB] text-[#2B1A11] antialiased font-sans p-4 md:p-8">
    <div class="max-w-3xl mx-auto space-y-6">

        {{-- HEADER --}}
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-3xl font-black tracking-tight text-[#2B1A11]">My Profile</h1>
                <p class="text-sm font-semibold text-[#635347] mt-0.5">Update your account details and delivery information.</p>
            </div>
            <a href="{{ route('corporates.dashboard') }}"
               class="shrink-0 text-xs font-black text-[#8A441B] hover:text-[#733614] bg-[#EFE9DC] hover:bg-[#E5DCB9] px-3 py-2 rounded-xl transition flex items-center gap-1.5 shadow-sm">
                <span>&#8592;</span>
                <span>Dashboard</span>
            </a>
        </div>

        {{-- SUCCESS FLASH --}}
        @if (session('status'))
            <div class="bg-[#1E4630] text-white text-sm font-bold px-4 py-3 rounded-2xl shadow-sm border border-[#143021]">
                {{ session('status') }}
            </div>
        @endif

        {{-- PROFILE FORM --}}
        <form wire:submit="save" class="bg-white border border-[#EBE3D3] rounded-2xl p-6 shadow-sm space-y-5">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                    <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-1">Mobile Number</label>
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
                <p class="text-[10px] text-gray-400 mt-0.5">Your first + last name identifies you as the buyer. Company name is shown on order receipts.</p>
                @error('company_name') <span class="text-red-500 text-xs font-semibold mt-0.5 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-1">Delivery Address</label>
                <textarea wire:model="address" rows="2" placeholder="Office / building, street, area"
                          class="w-full border border-gray-200 bg-white rounded-xl text-sm p-2.5 shadow-sm focus:ring-2 focus:ring-middo-orange focus:border-middo-orange outline-none"></textarea>
                @error('address') <span class="text-red-500 text-xs font-semibold mt-0.5 block">{{ $message }}</span> @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-1">City</label>
                    <select wire:model.live="city_id"
                            class="w-full border border-gray-200 bg-white rounded-xl text-sm p-2.5 shadow-sm focus:ring-2 focus:ring-middo-orange focus:border-middo-orange outline-none">
                        <option value="">Select a city</option>
                        @foreach($citiesList as $cityOption)
                            <option value="{{ $cityOption['id'] }}">{{ $cityOption['name'] }}</option>
                        @endforeach
                    </select>
                    @error('city_id') <span class="text-red-500 text-xs font-semibold mt-0.5 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-1">Area</label>
                    <select wire:model="area_id"
                            class="w-full border border-gray-200 bg-white rounded-xl text-sm p-2.5 shadow-sm focus:ring-2 focus:ring-middo-orange focus:border-middo-orange outline-none">
                        <option value="">Select an area</option>
                        @foreach($areasList as $areaOption)
                            <option value="{{ $areaOption['id'] }}">{{ $areaOption['name'] }}</option>
                        @endforeach
                    </select>
                    @error('area_id') <span class="text-red-500 text-xs font-semibold mt-0.5 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-dashed border-gray-100">
                <a href="{{ route('corporates.change-password') }}"
                   class="text-xs font-black text-[#635347] hover:text-[#2B1A11] bg-[#F7F4EB] hover:bg-[#EFE9DC] px-4 py-2.5 rounded-xl transition shadow-sm border border-[#EBE3D3]">
                    Change Password
                </a>
                <button type="submit"
                        class="bg-middo-orange hover:bg-[#733614] text-white font-black text-xs uppercase tracking-wider px-6 py-2.5 rounded-xl shadow-sm transition-colors">
                    <span wire:loading.remove wire:target="save">Save Changes</span>
                    <span wire:loading wire:target="save">Saving...</span>
                </button>
            </div>
        </form>
    </div>
</div>
