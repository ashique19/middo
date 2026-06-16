<section class="py-16 bg-white">
    <div class="max-w-7xl mx-auto px-6">
        <h2 class="text-3xl md:text-4xl font-bold text-center mb-4 text-middo-dark">Today's Featured Dishes</h2>
        
        <div class="text-center mb-12">
            <p class="text-gray-600 mb-4">
                Want to see the full variety? 
                <a href="{{ route('login') }}" class="text-[#C9621F] font-bold hover:underline">Sign in to see all menus</a>
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            @php
                $dishes = [
                    ['title' => 'Chef\'s Special 1', 'img' => 'https://thumbs.dreamstime.com/b/traditional-bangladeshi-food-bamboo-plate-authentic-spread-traditional-bangladeshi-dishes-beautifully-presented-394540962.jpg'],
                    ['title' => 'Chef\'s Special 2', 'img' => 'https://media.istockphoto.com/id/2255636078/video/traditional-bengali-food-platter-with-rice-chicken-lentils-served-in-poila-boisakh-in-kolkata.jpg?s=640x640&k=20&c=vZIUBMULNq1j2aSdSbXg0NVWDQGf6G6JVpriN5E2qcc='],
                    ['title' => 'Chef\'s Special 3', 'img' => 'https://www.travelandexplorebd.com/storage/app/public/posts/February2020/y5HiFSE13x2VNi0cdRhx.jpg']
                ];
            @endphp

            @foreach($dishes as $dish)
                <div class="rounded-2xl overflow-hidden shadow-lg border border-gray-100 hover:shadow-xl transition flex flex-col">
                    <div class="bg-gray-50 h-48 overflow-hidden">
                        <img src="{{ $dish['img'] }}" class="w-full h-full object-cover" alt="{{ $dish['title'] }}">
                    </div>
                    <div class="p-6 flex-1">
                        <h3 class="text-xl font-bold mb-2">{{ $dish['title'] }}</h3>
                        <p class="text-gray-600 mb-4 text-sm">Freshly prepared, balanced nutrition for your afternoon.</p>
                        <button class="w-full bg-middo-orange text-white py-2 rounded-lg font-semibold hover:bg-orange-700 transition">Order Now</button>
                    </div>
                </div>
            @endforeach
        </div>

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