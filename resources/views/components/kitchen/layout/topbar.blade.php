<header class="bg-white shadow-sm py-4 px-8 flex justify-between items-center sticky top-0 z-40">
    <div class="text-middo-dark font-semibold">
        Welcome back, {{ Auth::user()->first_name }}
    </div>

    <div class="flex items-center gap-6">
        <a href="{{ route('kitchen.cash-handovers') }}"
           class="inline-flex items-center px-3.5 py-2 rounded-xl bg-middo-orange hover:bg-[#733614] text-white text-sm font-bold transition">
            Cash Management
        </a>

        {{-- ACCOUNT DROPDOWN (Profile / Change Password / Logout) --}}
        <div class="relative" x-data="{ accountOpen: false }">
            <button type="button" @click="accountOpen = !accountOpen"
                    class="flex items-center gap-2 text-sm font-semibold text-middo-dark hover:text-middo-orange transition focus:outline-none">
                <span class="w-8 h-8 rounded-full bg-middo-orange/10 text-middo-orange flex items-center justify-center font-black">
                    {{ strtoupper(substr(Auth::user()->first_name ?? 'U', 0, 1)) }}
                </span>
                <span class="hidden sm:inline">Account</span>
                <svg class="w-4 h-4 transition-transform" :class="accountOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
            </button>

            <div x-show="accountOpen" x-cloak @click.away="accountOpen = false"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="absolute right-0 mt-2 w-52 bg-white border border-gray-100 rounded-xl shadow-lg py-1.5 z-50">

                <button type="button" @click="$dispatch('open-profile-modal'); accountOpen = false"
                        class="w-full text-left px-4 py-2 text-sm font-semibold text-middo-dark hover:bg-[#F6F2E8] hover:text-middo-orange transition flex items-center gap-2.5">
                    <svg class="w-4 h-4 opacity-75" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                    <span>Profile</span>
                </button>

                <button type="button" @click="$dispatch('open-change-password-modal'); accountOpen = false"
                        class="w-full text-left px-4 py-2 text-sm font-semibold text-middo-dark hover:bg-[#F6F2E8] hover:text-middo-orange transition flex items-center gap-2.5">
                    <svg class="w-4 h-4 opacity-75" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                    </svg>
                    <span>Change Password</span>
                </button>

                <div class="border-t border-gray-100 my-1"></div>

                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full text-left px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50 transition flex items-center gap-2.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M19.5 12l-3-3m3 3l-3 3m3-3H9" />
                        </svg>
                        <span>Logout</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
