<section class="py-16 bg-[#F5F2E9]">
    @props(['menu'])
    <div class="max-w-7xl mx-auto px-6">
        <h2 class="text-4xl font-extrabold text-center text-middo-dark mb-12">EXPLORE TODAY'S CURATED MENU</h2>

        <!-- FIXED: Replaced static foreach loop block with the custom inline Livewire handler -->
        <livewire:public.menu-display :menu="$menu" />

        @guest
            <div class="text-center mt-16">
                <a href="{{ route('register') }}" class="bg-middo-orange text-white px-12 py-4 rounded-full font-bold text-lg hover:bg-[#4E342E] transition shadow-xl">
                    Signup To See The Full Menu
                </a>
            </div>
        @endguest
    </div>
</section>