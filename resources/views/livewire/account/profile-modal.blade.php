<div>
    @if($showModal)
        <div wire:key="profile-modal-root" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" wire:click="closeModal"></div>

            <div class="relative bg-white w-full max-w-lg rounded-2xl shadow-2xl border border-[#EBE3D3] max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between p-5 border-b border-[#EBE3D3] sticky top-0 bg-white rounded-t-2xl">
                    <div>
                        <h2 class="text-xl font-black tracking-tight text-[#2B1A11]">My Profile</h2>
                        <p class="text-xs font-semibold text-[#635347] mt-0.5">Your account and delivery details.</p>
                    </div>
                    <button type="button" wire:click="closeModal" class="p-1.5 rounded-lg text-gray-400 hover:text-[#2B1A11] hover:bg-[#F7F4EB] transition" aria-label="Close">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-5 space-y-4">
                    <div class="space-y-3">
                        <div class="rounded-xl border border-[#EBE3D3] bg-[#FDFBF7] px-4 py-3">
                            <p class="text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-1">Name</p>
                            <p class="text-sm font-bold text-[#2B1A11]">{{ $name ?: '—' }}</p>
                        </div>

                        <div class="rounded-xl border border-[#EBE3D3] bg-[#FDFBF7] px-4 py-3">
                            <p class="text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-1">Phone Number</p>
                            <p class="text-sm font-bold text-[#2B1A11]">{{ $mobile ?: '—' }}</p>
                        </div>

                        <div class="rounded-xl border border-[#EBE3D3] bg-[#FDFBF7] px-4 py-3">
                            <p class="text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-1">Address</p>
                            <p class="text-sm font-semibold text-[#2B1A11] whitespace-pre-line">{{ $address ?: '—' }}</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="rounded-xl border border-[#EBE3D3] bg-[#FDFBF7] px-4 py-3">
                                <p class="text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-1">City</p>
                                <p class="text-sm font-bold text-[#2B1A11]">{{ $cityName }}</p>
                            </div>

                            <div class="rounded-xl border border-[#EBE3D3] bg-[#FDFBF7] px-4 py-3">
                                <p class="text-[11px] font-bold text-gray-500 uppercase tracking-tight mb-1">Area</p>
                                <p class="text-sm font-bold text-[#2B1A11]">{{ $areaName }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-3 pt-3 border-t border-dashed border-gray-100">
                        <button type="button" wire:click="$dispatch('open-change-password-modal')"
                                class="text-xs font-black text-[#635347] hover:text-[#2B1A11] bg-[#F7F4EB] hover:bg-[#EFE9DC] px-4 py-2.5 rounded-xl transition shadow-sm border border-[#EBE3D3]">
                            Change Password
                        </button>
                        <button type="button" wire:click="openEditModal"
                                class="bg-middo-orange hover:bg-[#733614] text-white font-black text-xs uppercase tracking-wider px-6 py-2.5 rounded-xl shadow-sm transition-colors">
                            Edit Profile
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
