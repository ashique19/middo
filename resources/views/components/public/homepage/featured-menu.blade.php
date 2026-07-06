@props(['menu'])

<section class="py-16 bg-white">
    <div class="max-w-7xl mx-auto px-6">
        <h2 class="text-3xl md:text-4xl font-bold text-center mb-4 text-middo-dark">Today's Featured Dishes</h2>
        
        <div class="text-center mb-12">
            <p class="text-gray-600 mb-4">
                Want to see the full variety? 
                <a href="{{ route('login') }}" class="text-[#C9621F] font-bold hover:underline">Sign in</a>
            </p>
        </div>

        <livewire:public.menu-display :menu="$menu" />

        <div class="text-center mt-12">
            <a href="{{ route('menu') }}" class="inline-flex items-center px-8 py-3 bg-white border-2 border-middo-orange text-middo-orange font-bold rounded-lg hover:bg-middo-orange hover:text-white transition shadow-sm">
                See More Dishes
                <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </a>
        </div>
    </div>
</section>
