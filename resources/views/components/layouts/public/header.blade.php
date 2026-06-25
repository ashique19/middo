<header class="bg-middo-cream shadow-sm sticky top-0 z-50" x-data="{ mobileMenuOpen: false }">
    <nav class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
        
        <a href="{{ route('home') }}" class="shrink-0">
            <img src="{{ asset('img/settings/logo.png') }}" alt="Middo Logo" class="h-8 md:h-10 w-auto">
        </a>

        <div class="hidden md:flex space-x-6 lg:space-x-8 items-center">
            <a href="{{ route('menu') }}" class="text-middo-dark font-medium hover:text-middo-orange transition">Menu</a>
            <a href="{{ route('how-it-works-corporates') }}" class="text-middo-dark font-medium hover:text-middo-orange transition">For Corporates</a>
            <a href="{{ route('how-it-works-kitchen') }}" class="text-middo-dark font-medium hover:text-middo-orange transition">For Kitchens</a>
        </div>

        <div class="hidden md:flex items-center gap-4 lg:gap-6">
            @if(auth()->user())
                <a href="{{ route('dashboard.redirect') }}" class="text-lg font-semibold text-middo-dark flex items-center justify-center gap-2" @click="mobileMenuOpen = false">
                    <svg class="w-5 h-5 text-middo-orange" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                    <span>Dashboard</span>
                </a>
                @else
                <a href="{{ route('login') }}" class="text-lg font-semibold text-middo-dark flex items-center justify-center gap-2" @click="mobileMenuOpen = false">
                    <svg class="w-5 h-5 text-middo-orange" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                    <span>Login</span>
                </a>
                @endif
            <a href="{{ route('menu') }}" class="bg-middo-orange text-white px-5 py-2.5 rounded-full font-bold hover:bg-orange-800 transition shadow-lg">
                See Today's Lunch
            </a>
        </div>

        <button class="md:hidden p-2 text-middo-dark focus:outline-none" @click="mobileMenuOpen = !mobileMenuOpen">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path x-show="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                <path x-show="mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </nav>

    <div x-show="mobileMenuOpen" 
         x-cloak 
         class="md:hidden absolute top-full left-0 w-full bg-white border-b border-gray-100 shadow-xl p-6 space-y-6 text-center">
        
        <a href="{{ route('menu') }}" class="block text-lg font-semibold text-middo-dark" @click="mobileMenuOpen = false">Menu</a>
        <a href="{{ route('how-it-works-corporates') }}" class="block text-lg font-semibold text-middo-dark" @click="mobileMenuOpen = false">For Corporates</a>
        <a href="{{ route('how-it-works-kitchen') }}" class="block text-lg font-semibold text-middo-dark" @click="mobileMenuOpen = false">For Kitchens</a>
        
        @if(auth()->user())
        <a href="{{ route('dashboard.redirect') }}" class="text-lg font-semibold text-middo-dark flex items-center justify-center gap-2" @click="mobileMenuOpen = false">
            <svg class="w-5 h-5 text-middo-orange" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
            </svg>
            <span>Dashboard</span>
        </a>
        @else
        <a href="{{ route('login') }}" class="text-lg font-semibold text-middo-dark flex items-center justify-center gap-2" @click="mobileMenuOpen = false">
            <svg class="w-5 h-5 text-middo-orange" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
            </svg>
            <span>Login</span>
        </a>
        @endif
        
        <div class="pt-4">
            <a href="{{ route('menu') }}" class="block w-full bg-middo-orange text-white py-3 rounded-full font-bold">
                See Today's Lunch
            </a>
        </div>
    </div>
</header>