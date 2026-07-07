<div class="min-h-screen bg-[#F7F4EB] text-[#2B1A11] antialiased font-sans p-4 md:p-8">
    <div class="max-w-lg mx-auto space-y-6">

        {{-- HEADER --}}
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-3xl font-black tracking-tight text-[#2B1A11]">Change Password</h1>
                <p class="text-sm font-semibold text-[#635347] mt-0.5">Keep your account secure with a strong password.</p>
            </div>
            <a href="{{ route('corporates.profile') }}"
               class="shrink-0 text-xs font-black text-[#8A441B] hover:text-[#733614] bg-[#EFE9DC] hover:bg-[#E5DCB9] px-3 py-2 rounded-xl transition flex items-center gap-1.5 shadow-sm">
                <span>&#8592;</span>
                <span>Profile</span>
            </a>
        </div>

        {{-- SUCCESS FLASH --}}
        @if (session('status'))
            <div class="bg-[#1E4630] text-white text-sm font-bold px-4 py-3 rounded-2xl shadow-sm border border-[#143021]">
                {{ session('status') }}
            </div>
        @endif

        {{-- CHANGE PASSWORD FORM --}}
        <form wire:submit="updatePassword" class="bg-white border border-[#EBE3D3] rounded-2xl p-6 shadow-sm space-y-5">

            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-1">Current Password</label>
                <input type="password" wire:model="current_password" autocomplete="current-password"
                       class="w-full border border-gray-200 bg-white rounded-xl text-sm p-2.5 shadow-sm focus:ring-2 focus:ring-middo-orange focus:border-middo-orange outline-none">
                @error('current_password') <span class="text-red-500 text-xs font-semibold mt-0.5 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-1">New Password</label>
                <input type="password" wire:model="password" autocomplete="new-password"
                       class="w-full border border-gray-200 bg-white rounded-xl text-sm p-2.5 shadow-sm focus:ring-2 focus:ring-middo-orange focus:border-middo-orange outline-none">
                @error('password') <span class="text-red-500 text-xs font-semibold mt-0.5 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-1">Confirm New Password</label>
                <input type="password" wire:model="password_confirmation" autocomplete="new-password"
                       class="w-full border border-gray-200 bg-white rounded-xl text-sm p-2.5 shadow-sm focus:ring-2 focus:ring-middo-orange focus:border-middo-orange outline-none">
            </div>

            <div class="flex items-center justify-end pt-2 border-t border-dashed border-gray-100">
                <button type="submit"
                        class="bg-middo-orange hover:bg-[#733614] text-white font-black text-xs uppercase tracking-wider px-6 py-2.5 rounded-xl shadow-sm transition-colors">
                    <span wire:loading.remove wire:target="updatePassword">Update Password</span>
                    <span wire:loading wire:target="updatePassword">Updating...</span>
                </button>
            </div>
        </form>
    </div>
</div>
