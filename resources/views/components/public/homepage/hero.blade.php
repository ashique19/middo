<section class="bg-[#1A1C19] text-white py-12 md:py-20 overflow-hidden" x-data="{ activeSlide: 1, autoSlide() { this.activeSlide = (this.activeSlide % 3) + 1; } }" x-init="setInterval(() => autoSlide(), 5000)">
    <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-12 items-center">
        <div class="order-2 md:order-1 text-center md:text-left">
            <h1 class="text-4xl md:text-6xl font-extrabold leading-tight mb-6">
                Lunch You Actually <span class="text-[#C9621F]">Look Forward To.</span>
            </h1>
            <p class="text-lg md:text-xl text-gray-400 mb-8 max-w-md mx-auto md:mx-0">
                Fresh, curated local meals, delivered straight to your desk. Fuel your afternoon with food that tastes like home.
            </p>
            <div class="flex flex-col sm:flex-row justify-center md:justify-start gap-4 mb-8">
                <a href="{{ route('menu') }}" class="bg-[#C9621F] hover:bg-orange-700 transition px-8 py-3 rounded-full font-bold text-lg shadow-lg">See Today's Menu</a>
                <a href="{{ route('how-it-works-corporates') }}" class="border border-gray-600 hover:border-white transition px-8 py-3 rounded-full font-semibold">Benefits for Corporates</a>
            </div>
            <p class="text-sm text-gray-300">✓ Rated 4.8/5 by Dhaka corporate teams</p>
        </div>
        <div class="order-1 md:order-2 relative h-[300px] md:h-[450px] w-full rounded-3xl overflow-hidden shadow-2xl">
            <template x-for="i in 3" :key="i">
                <div x-show="activeSlide === i" class="absolute inset-0">
                     <img :src="'{{ asset('img/home/') }}/' + (i + 7) + '.jpg'" class="w-full h-full object-cover">
                </div>
            </template>
        </div>
    </div>
</section>