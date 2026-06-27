{{-- VISIBLE MOBILE HEADER TOP-BAR ROW --}}
<nav class="md:hidden max-w-7xl mx-auto px-6 py-4 flex justify-between items-center bg-[#FDFBF7] border-b border-amber-900/5">
    {{-- HAMBURGER TRIGGER BUTTON ON THE LEFT --}}
    <button class="p-2 text-amber-950 focus:outline-none transition-transform active:scale-95" 
            @click="mobileMenuOpen = true" 
            aria-label="Open Menu">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
    </button>

    {{-- BRAND LOGO IDENTITY --}}
    <a href="{{ route('home') }}" class="shrink-0 flex items-center gap-2">
        <img src="{{ asset('img/settings/logo.png') }}" alt="Middo Logo" class="h-8 w-auto">
    </a>
</nav>

{{-- NATIVE MOBILE APP SLIDE-FROM-LEFT DRAWER OVERLAY --}}
<div x-show="mobileMenuOpen" 
     x-cloak
     class="fixed inset-0 z-50 md:hidden"
     role="dialog" 
     aria-modal="true">
    
    {{-- Backdrop Shade Blur Overlay --}}
    <div x-show="mobileMenuOpen"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="mobileMenuOpen = false"
         class="fixed inset-0 bg-amber-950/30 backdrop-blur-sm"></div>
    
    {{-- DRAWER CONTENT PANEL --}}
    <div x-show="mobileMenuOpen"
         x-transition:enter="transition ease-out duration-300 transform"
         x-transition:enter-start="-translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200 transform"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="-translate-x-full"
         class="fixed inset-y-0 left-0 w-full max-w-[300px] bg-[#FDFBF7] shadow-2xl flex flex-col justify-between overflow-y-auto">
        
        <div>
            {{-- Header Row: Brand & Close Action Button --}}
            <div class="flex justify-between items-center px-5 pt-5 pb-4 border-b border-amber-900/5 bg-[#FDFBF7]">
                <img src="{{ asset('img/settings/logo.png') }}" alt="Middo Logo" class="h-7 w-auto">
                <button @click="mobileMenuOpen = false" class="p-2 text-amber-950 hover:text-amber-700 transition focus:outline-none" aria-label="Close Menu">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            {{-- TOP SEGMENT: USER PROFILE & WALLET CARD --}}
            <div class="bg-[#F6F2E8] p-5 border-b border-amber-900/5 text-left">
                @if(auth()->user())
                    <div class="space-y-4">
                        <div class="flex items-center gap-3.5">
                            <div class="w-12 h-12 rounded-full border-2 border-middo-orange bg-amber-100 flex items-center justify-center font-black text-lg text-middo-orange shadow-sm">
                                {{ substr(auth()->user()->name ?? 'U', 0, 1) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="text-sm font-black text-amber-950 tracking-tight truncate">{{ auth()->user()->name ?? 'Corporate Partner' }}</h4>
                                <p class="text-[11px] font-bold text-[#635347] mt-0.5">
                                    Balance: <span class="text-[#1E4630] font-mono font-black">৳{{ number_format(auth()->user()->balance ?? 0, 2) }}</span>
                                </p>
                            </div>
                        </div>

                        {{-- PROFILE ACTION BUTTON ROWS --}}
                        <div class="grid grid-cols-2 gap-2 w-full">
                            <a href="#" @click="mobileMenuOpen = false" class="text-xs font-bold text-[#2B1A11] bg-white border border-[#EBE3D3] py-2 px-2 rounded-xl transition flex items-center justify-center gap-1.5 shadow-sm active:bg-gray-50">
                                <svg class="w-3.5 h-3.5 text-[#635347]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                </svg>
                                <span>Profile</span>
                            </a>
                            <a href="#" @click="mobileMenuOpen = false" class="text-xs font-bold text-white bg-middo-orange py-2 px-2 rounded-xl transition flex items-center justify-center gap-1.5 shadow-sm active:bg-[#733614]">
                                <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                <span>Add Money</span>
                            </a>
                        </div>
                        {{-- PROFILE ACTION BUTTON ROWS --}}
                        <div class="grid grid-cols-1 gap-2 w-full">
                            <a href="#" class="text-[14px] font-extrabold text-[#2B1A11] hover:text-middo-orange transition py-2.5 px-1.5 flex items-center gap-3 rounded-xl hover:bg-[#F6F2E8]" @click="mobileMenuOpen = false">
                                <svg class="w-4 h-4 text-middo-orange opacity-85" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                </svg>
                                <span>Change Password</span>
                            </a>
                        </div>
                    </div>
                @else
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-amber-200/60 flex items-center justify-center text-amber-950 shadow-inner">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-xs font-black text-amber-950 uppercase tracking-wide">Welcome to Middo</h4>
                            <a href="{{ route('login') }}" @click="mobileMenuOpen = false" class="text-xs font-bold text-middo-orange underline">Sign in to your pool</a>
                        </div>
                    </div>
                @endif
            </div>

            {{-- DRAWER LINKS NAVIGATION LIST --}}
            <div class="px-5 py-5 space-y-5 text-left">
                
                @if(!auth()->user())
                {{-- Navigation Core Links Segment --}}
                <div class="space-y-1">
                    <span class="text-[10px] font-black uppercase tracking-wider text-[#A69988] block mb-2 px-1">Navigation</span>
                    
                    <a href="{{ route('menu') }}" class="text-[14px] font-extrabold text-[#2B1A11] hover:text-middo-orange transition py-2.5 px-1.5 flex items-center gap-3 rounded-xl hover:bg-[#F6F2E8]" @click="mobileMenuOpen = false">
                        <svg class="w-4 h-4 text-middo-orange opacity-85" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                        </svg>
                        <span>Menu Selection</span>
                    </a>
                    
                    <a href="{{ route('how-it-works-corporates') }}" class="text-[14px] font-extrabold text-[#2B1A11] hover:text-middo-orange transition py-2.5 px-1.5 flex items-center gap-3 rounded-xl hover:bg-[#F6F2E8]" @click="mobileMenuOpen = false">
                        <svg class="w-4 h-4 text-middo-orange opacity-85" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 .596-.482 1.079-1.08 1.079H4.83c-.598 0-1.08-.483-1.08-1.08V14.15m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.077-.49-2.137-1.32-2.388L14.07 3.5c-.43-.13-.89-.13-1.32 0L6.07 6.318c-.83.25-1.32 1.31-1.32 2.388v3.783c0 .63.285 1.229.75 1.661m16.5 0a2.18 2.18 0 01-.75 1.661m-16.5 0a2.18 2.18 0 00-.75 1.661m1.5-1.661a2.18 2.18 0 01.75-1.661M12 11.25a3.75 3.75 0 110-7.5 3.75 3.75 0 010 7.5z" />
                        </svg>
                        <span>For Corporates</span>
                    </a>
                    
                    <a href="{{ route('how-it-works-kitchen') }}" class="text-[14px] font-extrabold text-[#2B1A11] hover:text-middo-orange transition py-2.5 px-1.5 flex items-center gap-3 rounded-xl hover:bg-[#F6F2E8]" @click="mobileMenuOpen = false">
                        <svg class="w-4 h-4 text-middo-orange opacity-85" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 18a3.75 3.75 0 00.495-7.467 5.99 5.99 0 00-1.925 3.546 5.974 5.974 0 01-2.133-1A3.75 3.75 0 0012 18z" />
                        </svg>
                        <span>For Kitchens</span>
                    </a>
                </div>
                
                <div class="border-t border-dashed border-amber-900/10 my-2"></div>

                @endif

                {{-- Management Panel Links Segment --}}
                <div class="space-y-1">
                    <span class="text-[10px] font-black uppercase tracking-wider text-[#A69988] block mb-2 px-1">Management Hub</span>
                    
                    @if(auth()->user())
                        <a href="{{ route('dashboard.redirect') }}" class="text-[14px] font-extrabold text-[#2B1A11] hover:text-middo-orange transition py-2.5 px-1.5 flex items-center gap-3 rounded-xl hover:bg-[#F6F2E8]" @click="mobileMenuOpen = false">
                            <svg class="w-4 h-4 text-middo-orange opacity-85" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z" />
                            </svg>
                            <span>Dashboard</span>
                        </a>

                        <a href="#" class="text-[14px] font-extrabold text-[#2B1A11] hover:text-middo-orange transition py-2.5 px-1.5 flex items-center gap-3 rounded-xl hover:bg-[#F6F2E8]" @click="mobileMenuOpen = false">
                            <svg class="w-4 h-4 text-middo-orange opacity-85" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                            <span>Orders</span>
                        </a>

                        <a href="#" class="text-[14px] font-extrabold text-[#2B1A11] hover:text-middo-orange transition py-2.5 px-1.5 flex items-center gap-3 rounded-xl hover:bg-[#F6F2E8]" @click="mobileMenuOpen = false">
                            <svg class="w-4 h-4 text-middo-orange opacity-85" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                            <span>Menu</span>
                        </a>

                        <form method="POST" action="{{ route('logout') }}" class="w-full pt-1.5 border-t border-dashed border-amber-900/10 mt-2">
                            @csrf
                            <button type="submit" class="w-full flex items-center gap-3 py-2.5 px-1.5 text-[14px] font-extrabold text-red-800 hover:bg-red-50/40 rounded-xl transition text-left focus:outline-none">
                                <svg class="w-4 h-4 text-red-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M19.5 12l-3-3m3 3l-3 3m3-3H9" />
                                </svg>
                                <span>Logout</span>
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="text-[14px] font-extrabold text-[#2B1A11] hover:text-middo-orange transition py-2.5 px-1.5 flex items-center gap-3 rounded-xl hover:bg-[#F6F2E8]" @click="mobileMenuOpen = false">
                            <svg class="w-4 h-4 text-middo-orange" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 119 0v3.75M3.75 21.75h16.5a1.5 1.5 0 001.5-1.5V12a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 12v8.25a1.5 1.5 0 001.5 1.5z" />
                            </svg>
                            <span>Portal Login</span>
                        </a>
                    @endif
                </div>
            </div>
        </div>
        
        {{-- BOTTOM STICKY ACTION FOOTER --}}
        <div class="p-4 border-t border-amber-900/5 bg-[#FDFBF7]">
            <a href="{{ route('menu') }}" class="block w-full bg-middo-orange text-white py-3.5 rounded-2xl font-black text-xs uppercase tracking-wider shadow-lg hover:bg-[#733614] active:scale-[0.99] transition text-center" @click="mobileMenuOpen = false">
                Track Today's Lunch
            </a>
        </div>
    </div>
</div>