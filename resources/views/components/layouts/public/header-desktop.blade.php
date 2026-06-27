<nav class="hidden md:flex max-w-7xl mx-auto px-6 py-4 justify-between items-center" x-data="{ userDropdownOpen: false }">
    
    {{-- BRAND LOGO IDENTITY --}}
    <a href="{{ route('home') }}" class="shrink-0 flex items-center gap-2 transition hover:opacity-90">
        <img src="{{ asset('img/settings/logo.png') }}" alt="Middo Logo" class="h-10 w-auto">
    </a>

    {{-- DESKTOP LINKS NAVIGATION --}}
    <div class="flex space-x-8 lg:space-x-10 items-center">
        <a href="{{ route('menu') }}" class="text-[#2B1A11] font-extrabold tracking-tight text-sm hover:text-middo-orange transition-colors relative py-1 group">
            <span>Menu</span>
            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-middo-orange transition-all group-hover:w-full"></span>
        </a>
        <a href="{{ route('how-it-works-corporates') }}" class="text-[#2B1A11] font-extrabold tracking-tight text-sm hover:text-middo-orange transition-colors relative py-1 group">
            <span>For Corporates</span>
            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-middo-orange transition-all group-hover:w-full"></span>
        </a>
        <a href="{{ route('how-it-works-kitchen') }}" class="text-[#2B1A11] font-extrabold tracking-tight text-sm hover:text-middo-orange transition-colors relative py-1 group">
            <span>For Kitchens</span>
            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-middo-orange transition-all group-hover:w-full"></span>
        </a>
    </div>

    {{-- DESKTOP ACTIONS USER HUB --}}
    <div class="flex items-center gap-5 lg:gap-6">
        @if(auth()->user())
            {{-- DROPDOWN TRIGGER HUB --}}
            <div class="relative">
                <button @click="userDropdownOpen = !userDropdownOpen" 
                        @click.away="userDropdownOpen = false"
                        class="text-sm font-extrabold tracking-tight text-[#2B1A11] flex items-center justify-center gap-2 hover:text-middo-orange transition-colors focus:outline-none select-none py-1.5 px-3 rounded-xl hover:bg-[#F6F2E8]">
                    <svg class="w-4 h-4 text-current" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                    <span>Dashboard</span>
                    <svg class="w-3 h-3 text-[#2B1A11]/50 transition-transform duration-200" :class="userDropdownOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>

                {{-- RICH USER MICRO-DASHBOARD DROPDOWN PANEL --}}
                <div x-show="userDropdownOpen"
                     x-cloak
                     x-transition:enter="transition ease-out duration-100 transform"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75 transform"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="absolute right-0 mt-3 w-64 bg-[#FDFBF7] border border-[#EBE3D3] rounded-2xl shadow-2xl z-50 overflow-hidden origin-top-right p-4 space-y-3.5">
                    
                    {{-- MINI INTEGRATED WALLET & USER CARD --}}
                    <div class="flex flex-col items-center text-center pb-3 border-b border-gray-100">
                        <div class="w-12 h-12 rounded-full border-2 border-middo-orange bg-amber-100 flex items-center justify-center font-black text-lg text-middo-orange shadow-sm mb-2">
                            {{ substr(auth()->user()->name ?? 'U', 0, 1) }}
                        </div>
                        <h4 class="text-sm font-black text-[#2B1A11] tracking-tight leading-tight">{{ auth()->user()->name ?? 'Corporate User' }}</h4>
                        <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mt-1">
                            Balance: 
                            <span class="text-[#1E4630] font-mono font-black">
                                ৳{{ number_format(auth()->user()->balance ?? 0, 2) }}
                            </span>
                        </p>
                        
                        {{-- QUICK DUAL BALANCE/PROFILE ACTION BUTTONS --}}
                        <div class="grid grid-cols-2 gap-2 w-full mt-3">
                            <a href="#" class="text-[11px] font-extrabold text-[#635347] bg-white hover:bg-[#F6F2E8] py-2 px-1.5 rounded-xl transition flex items-center justify-center gap-1.5 border border-[#EBE3D3] shadow-sm group">
                                <svg class="w-3.5 h-3.5 text-[#635347] transition-colors group-hover:text-[#2B1A11]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                </svg>
                                <span>Profile</span>
                            </a>
                            <a href="#" class="text-[11px] font-extrabold text-white bg-middo-orange hover:bg-[#733614] py-2 px-1.5 rounded-xl transition flex items-center justify-center gap-1.5 shadow-sm">
                                <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                <span>Add Money</span>
                            </a>
                        </div>
                        <div class="grid grid-cols-1 w-full mt-3">
                            <a href="#" class="flex items-center gap-2.5 px-2.5 py-2 text-xs font-extrabold text-[#2B1A11] hover:bg-[#F6F2E8] hover:text-middo-orange rounded-xl transition text-left">
                                <svg class="w-4 h-4 opacity-75" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                </svg>
                                <span>Change Password</span>
                            </a>
                        </div>
                    </div>

                    {{-- SECURITY & ACCOUNT LINKS LIST --}}
                    <div class="space-y-0.5">
                        <a href="{{ route('dashboard.redirect') }}" class="flex items-center gap-2.5 px-2.5 py-2 text-xs font-extrabold text-[#2B1A11] hover:bg-[#F6F2E8] hover:text-middo-orange rounded-xl transition text-left">
                            <svg class="w-4 h-4 opacity-75" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                            <span>Dashboard</span>
                        </a>

                        <a href="#" class="flex items-center gap-2.5 px-2.5 py-2 text-xs font-extrabold text-[#2B1A11] hover:bg-[#F6F2E8] hover:text-middo-orange rounded-xl transition text-left">
                            <svg class="w-4 h-4 opacity-75" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                            <span>Orders</span>
                        </a>
                    </div>

                    <div class="space-y-0.5  border-b border-gray-100 pb-3">

                        {{-- APPLICATION DISCONNECT SESSION ROUTE --}}
                        <form method="POST" action="{{ route('logout') }}" class="w-full pt-1.5 border-t border-dashed border-gray-200 mt-1.5">
                            @csrf
                            <button type="submit" class="w-full flex items-center gap-2.5 px-2.5 py-2 text-xs font-extrabold text-red-800 hover:bg-red-50 rounded-xl transition text-left focus:outline-none">
                                <svg class="w-4 h-4 text-red-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M19.5 12l-3-3m3 3l-3 3m3-3H9" />
                                </svg>
                                <span>Logout</span>
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        @else
            <a href="{{ route('login') }}" class="text-sm font-extrabold tracking-tight text-[#2B1A11] flex items-center justify-center gap-2 hover:text-middo-orange transition-colors py-1.5 px-3 rounded-xl hover:bg-[#F6F2E8]">
                <svg class="w-4 h-4 text-current" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                </svg>
                <span>Login</span>
            </a>
        @endif
        
        <a href="{{ route('menu') }}" class="bg-middo-orange text-white px-5 py-2.5 rounded-full font-extrabold text-xs uppercase tracking-wider hover:bg-[#733614] transition shadow-md active:scale-[0.98]">
            Track Today's Lunch
        </a>
    </div>
</nav>