<section class="py-16 bg-[#F5F2E9]">
    <div class="max-w-7xl mx-auto px-6">
        <h2 class="text-4xl font-extrabold text-center text-middo-dark mb-12">EXPLORE TODAY'S CURATED MENU</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            @php
                $menu = [
                    ['name' => 'SEAFOOD PASTA', 'price' => 140, 'rating' => 4.8, 'description' => 'High-resto, shots, smroto, propressnation, shai glutenin, to find perfect daily meal.', 'image' => 'https://thumbs.dreamstime.com/b/traditional-bangladeshi-food-bamboo-plate-authentic-spread-traditional-bangladeshi-dishes-beautifully-presented-394540962.jpg'],
                    ['name' => 'MEDITERRANEAN BOWL', 'price' => 125, 'description' => 'Mouthwatering description, mediterranean and bowl.', 'image' => 'https://media.istockphoto.com/id/2255636078/video/traditional-bengali-food-platter-with-rice-chicken-lentils-served-in-poila-boisakh-in-kolkata.jpg?s=640x640&k=20&c=vZIUBMULNq1j2aSdSbXg0NVWDQGf6G6JVpriN5E2qcc='],
                    ['name' => 'KETO SALMON', 'price' => 1200, 'description' => 'Mouthwatering betor, salmon, main salmon exallabce and Middo balance.', 'image' => 'https://www.travelandexplorebd.com/storage/app/public/posts/February2020/y5HiFSE13x2VNi0cdRhx.jpg'],
                    ['name' => 'SEAFOOD PASTA', 'price' => 1400, 'rating' => 4.8, 'description' => 'High-resto, shots, smroto, propressnation, shai glutenin, to find perfect daily meal.', 'image' => 'https://thumbs.dreamstime.com/b/traditional-bangladeshi-food-bamboo-plate-authentic-spread-traditional-bangladeshi-dishes-beautifully-presented-394540962.jpg'],
                    ['name' => 'MEDITERRANEAN BOWL', 'price' => 1250, 'description' => 'Mouthwatering description, mediterranean and bowl.', 'image' => 'https://media.istockphoto.com/id/2255636078/video/traditional-bengali-food-platter-with-rice-chicken-lentils-served-in-poila-boisakh-in-kolkata.jpg?s=640x640&k=20&c=vZIUBMULNq1j2aSdSbXg0NVWDQGf6G6JVpriN5E2qcc='],
                    ['name' => 'KETO SALMON', 'price' => 1200, 'description' => 'Mouthwatering betor, salmon, main salmon exallabce and Middo balance.', 'image' => 'https://www.travelandexplorebd.com/storage/app/public/posts/February2020/y5HiFSE13x2VNi0cdRhx.jpg'],
                    ['name' => 'SEAFOOD PASTA', 'price' => 1400, 'rating' => 4.8, 'description' => 'High-resto, shots, smroto, propressnation, shai glutenin, to find perfect daily meal.', 'image' => 'https://thumbs.dreamstime.com/b/traditional-bangladeshi-food-bamboo-plate-authentic-spread-traditional-bangladeshi-dishes-beautifully-presented-394540962.jpg'],
                    ['name' => 'MEDITERRANEAN BOWL', 'price' => 1250, 'description' => 'Mouthwatering description, mediterranean and bowl.', 'image' => 'https://media.istockphoto.com/id/2255636078/video/traditional-bengali-food-platter-with-rice-chicken-lentils-served-in-poila-boisakh-in-kolkata.jpg?s=640x640&k=20&c=vZIUBMULNq1j2aSdSbXg0NVWDQGf6G6JVpriN5E2qcc='],
                    ['name' => 'KETO SALMON', 'price' => 1200, 'description' => 'Mouthwatering betor, salmon, main salmon exallabce and Middo balance.', 'image' => 'https://www.travelandexplorebd.com/storage/app/public/posts/February2020/y5HiFSE13x2VNi0cdRhx.jpg']
                ];
            @endphp

            @foreach($menu as $dish)
                <x-public.menu.meal-card :dish="$dish" />
            @endforeach
        </div>

        <div class="text-center mt-16">
            <a href="{{ route('register') }}" class="bg-middo-orange text-white px-12 py-4 rounded-full font-bold text-lg hover:bg-[#4E342E] transition shadow-xl">
                CREATE MY ACCOUNT
            </a>
        </div>
    </div>
</section>