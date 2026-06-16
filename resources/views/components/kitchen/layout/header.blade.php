<header class="bg-middo-cream shadow-sm sticky top-0 z-50" x-data="{ mobileMenuOpen: false }">
    <nav class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center relative">
        
        <button class="md:hidden p-1 text-middo-dark" @click="mobileMenuOpen = true">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
        </button>

        <a href="{{ route('home') }}" class="shrink-0 md:mr-auto">
            <img src="{{ asset('img/settings/logo.png') }}" alt="Middo Logo" class="h-8 md:h-10 w-auto">
        </a>

        <div class="hidden md:flex items-center gap-6">
            <a href="#" class="text-middo-dark font-medium hover:text-middo-orange transition">Menu</a>
            <a href="{{ route('corporates.dashboard') }}" class="text-middo-dark font-medium hover:text-middo-orange transition">Dashboard</a>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="text-middo-dark font-medium hover:text-middo-orange transition">Logout</button>
            </form>
            <a href="#" class="bg-middo-orange text-white px-5 py-2.5 rounded-full font-bold hover:bg-orange-800 transition shadow-lg">
                See Today's Lunch
            </a>
        </div>

        <div x-show="mobileMenuOpen" 
             x-transition:enter="transition-opacity ease-linear duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="mobileMenuOpen = false"
             class="fixed inset-0 bg-black bg-opacity-50 z-40 md:hidden">
        </div>

        <div x-show="mobileMenuOpen"
             x-cloak
             x-transition:enter="transition ease-out duration-300 transform"
             x-transition:enter-start="-translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-300 transform"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="-translate-x-full"
             class="fixed top-0 left-0 h-full w-[80%] max-w-sm bg-white z-50 shadow-2xl flex flex-col p-6">
             
             <button @click="mobileMenuOpen = false" class="self-end mb-6 text-middo-dark">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
             </button>

             <div class="flex items-center gap-4 mb-6">
                 <div class="w-12 h-12 bg-middo-orange rounded-full flex items-center justify-center text-white font-bold text-xl">
                     {{ substr(Auth::user()->first_name, 0, 1) }}
                 </div>
                 <div>
                     <p class="font-bold text-middo-dark">
                        <a href="#" @click="mobileMenuOpen = false">
                        {{ Auth::user()->first_name }} {{ Auth::user()->last_name }}
                        </a>
                     </p>
                     <p class="text-sm text-gray-500">Balance: 
                        <span class="font-bold text-middo-orange">৳ 0.00</span>
                        <a href="#" class="text-xs" @click="mobileMenuOpen = false">(Add/Withdraw)</a>
                    </p>
                 </div>
             </div>

             <hr class="border-gray-200 mb-6">

             <div class="space-y-6 flex flex-col items-start">
                 <a href="#" class="text-lg font-semibold text-middo-dark" @click="mobileMenuOpen = false">Menu</a>
                 <a href="{{ route('corporates.dashboard') }}" class="text-lg font-semibold text-middo-dark" @click="mobileMenuOpen = false">Dashboard</a>
                 <form action="{{ route('logout') }}" method="POST">
                     @csrf
                     <button type="submit" class="text-lg font-semibold text-red-600">Logout</button>
                 </form>
             </div>
        </div>
    </nav>
</header>