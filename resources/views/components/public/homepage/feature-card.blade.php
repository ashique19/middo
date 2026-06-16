@props(['icon', 'title', 'description'])

<div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition duration-300 h-full flex flex-col">
    <div class="mb-6 h-20 flex items-center justify-start">
        <img src="{{ asset('img/home/' . $icon . '.png') }}" 
             alt="{{ $title }} icon" 
             class="w-20 h-20 object-contain">
    </div>
    
    <h3 class="text-xl font-bold mb-3 text-middo-dark">{{ $title }}</h3>
    <p class="text-gray-600 leading-relaxed flex-1">{{ $description }}</p>
</div>