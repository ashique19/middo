<x-layouts.public.app>
    <x-public.about.passion-promise />
    <x-public.about.values />
    <x-public.about.certification />
    <x-public.about.team />
    
    <section class="py-20 bg-[#2D2D2D] text-white">
        <div class="max-w-7xl mx-auto px-6 text-center">
            <h2 class="text-3xl font-bold mb-12">The Impact We Make</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                @foreach([['n' => '500+', 'l' => 'Meals Delivered'], ['n' => '50+', 'l' => 'Local Kitchens'], ['n' => '100+', 'l' => 'Office Partners'], ['n' => '98%', 'l' => 'Rating']] as $stat)
                    <div class="p-6 border border-white/10 rounded-2xl">
                        <div class="text-4xl font-extrabold text-[#C9621F] mb-2">{{ $stat['n'] }}</div>
                        <div class="text-sm uppercase tracking-widest opacity-80">{{ $stat['l'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
</x-layouts.public.app>