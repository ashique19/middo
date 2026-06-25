@props(['dish'])

<div class="bg-white rounded-3xl p-5 shadow-sm border border-gray-100 flex flex-col h-full">
    <img src="{{ $dish['image'] }}" alt="{{ $dish['name'] }}" class="w-full h-48 object-cover rounded-2xl mb-4">
    
    <div class="mb-2">
        <h3 class="text-xl font-bold text-middo-dark">{{ $dish['name'] }}</h3>
    </div>

    <p class="text-gray-600 text-sm mb-4 flex-grow">{{ $dish['description'] }}</p>

    <div class="flex justify-between items-center mb-4">
        <span class="text-xl font-extrabold text-middo-dark">৳{{ number_format($dish['price'], 2) }}</span>
        
        <div class="flex items-center gap-2">
            @if(isset($dish['rating']))
                <span class="bg-[#F8E298] text-[#8C6D1F] text-xs font-bold px-2 py-1 rounded-full flex items-center gap-1">
                    ★ {{ $dish['rating'] }}
                </span>
            @endif
        </div>
    </div>

    <div class="space-y-2">
        @auth
            {{-- Authenticated corporate user: Open checkout modal instantly --}}
            <button 
                @click="$dispatch('openOrderModal', { dishId: {{ $dish['id'] }} })"
                type="button"
                class="w-full bg-transparent border-2 border-middo-orange text-[#5D4037] py-2.5 rounded-xl font-bold hover:bg-[#5D4037] hover:text-white transition"
            >
                Order Now
            </button>
        @endauth

        @guest
            {{-- Unauthenticated Guest: Simple, clean redirect to the login screen --}}
            <a 
                href="{{ route('login') }}"
                class="w-full inline-flex items-center justify-center bg-transparent border-2 border-middo-orange text-[#5D4037] py-2.5 rounded-xl font-bold hover:bg-[#5D4037] hover:text-white transition text-center"
            >
                Order Now
            </a>
        @endguest
    </div>

</div>